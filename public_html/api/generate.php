<?php
/**
 * Page Generation API - FIXED
 * Main logic:
 * 1. Check if exact page exists - Return from cache
 * 2. Check if similar page exists - Return cached similar page
 * 3. If page doesn't exist:
 *    a. Call Gemini API with user request
 *    b. Get HTML code response from Gemini
 *    c. Send/Store HTML to database
 *    d. Call/Retrieve it back from database
 *    e. Return to frontend
 * 4. Update page views and timestamps
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/AIService.php';

// Handle CORS preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if database is available
if (!$pdo) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection unavailable',
        'details' => 'The database server is not accessible or PDO MySQL extension is missing',
        'troubleshooting' => [
            'Check if MySQL/MariaDB server is running',
            'Verify PDO MySQL PHP extension is installed: php8.0-mysql',
            'Check database credentials in /public_html/includes/config.php',
            'Check network connectivity to database server'
        ],
        'diagnostic_url' => '/diagnose-api.php',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'generate';
    $query = $_GET['query'] ?? $_POST['query'] ?? '';
    
    if (!$query) {
        throw new Exception('Search query is required');
    }
    
    // Sanitize query - keep original text
    $originalQuery = trim($query);
    $query = $originalQuery;
    
    if (strlen($query) < 2) {
        throw new Exception('Query must be at least 2 characters');
    }
    
    switch ($action) {
        case 'generate':
            handleSearch($pdo, $query);
            break;
        case 'check':
            checkPageExists($pdo, $query);
            break;
        case 'get':
            getPageBySlug($pdo, $query);
            break;
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/**
 * Main search handler - implements the FIXED core logic
 * 1. Check for exact match (case-insensitive)
 * 2. Check for similar pages (Levenshtein similarity)
 * 3. Call Gemini API if nothing found
 * 4. Store in database
 * 5. Return from database
 */
function handleSearch($pdo, $query) {
    // Validate $pdo is available
    if (!$pdo) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'Database unavailable',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Step 1: Check for exact match (case-insensitive)
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count, 
               created_at, updated_at
        FROM pages 
        WHERE LOWER(TRIM(query)) = LOWER(TRIM(:query))
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->execute(['query' => $query]);
    $existingPage = $stmt->fetch();
    
    if ($existingPage) {
        // Exact match found - return cached page
        updatePageViews($pdo, $existingPage['id']);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'found_type' => 'exact_match',
            'page' => [
                'id' => (int)$existingPage['id'],
                'query' => $existingPage['query'],
                'slug' => $existingPage['slug'],
                'title' => $existingPage['title'],
                'description' => $existingPage['description'],
                'content' => $existingPage['html_content'],
                'views' => (int)$existingPage['view_count'],
                'createdAt' => $existingPage['created_at'],
                'updatedAt' => $existingPage['updated_at']
            ],
            'message' => 'Found exact match in database',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Step 2: Check for similar pages
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count,
               created_at, updated_at
        FROM pages 
        WHERE status = 'active'
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    
    $stmt->execute();
    $allPages = $stmt->fetchAll();
    
    $bestMatch = null;
    $bestSimilarity = SIMILARITY_THRESHOLD;
    
    foreach ($allPages as $page) {
        $similarity = levenshteinSimilarity($query, $page['query']);
        
        if ($similarity > $bestSimilarity) {
            $bestSimilarity = $similarity;
            $bestMatch = $page;
        }
    }
    
    if ($bestMatch) {
        // Similar page found - return it
        updatePageViews($pdo, $bestMatch['id']);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'found_type' => 'similar_match',
            'similarity_score' => round($bestSimilarity, 3),
            'page' => [
                'id' => (int)$bestMatch['id'],
                'query' => $bestMatch['query'],
                'slug' => $bestMatch['slug'],
                'title' => $bestMatch['title'],
                'description' => $bestMatch['description'],
                'content' => $bestMatch['html_content'],
                'views' => (int)$bestMatch['view_count'],
                'createdAt' => $bestMatch['created_at'],
                'updatedAt' => $bestMatch['updated_at']
            ],
            'message' => 'Found similar page: "' . $bestMatch['query'] . '"',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Step 3: No page found - Call Gemini API to generate new page
    error_log("=== STARTING NEW PAGE GENERATION FOR QUERY: " . $query);
    createNewPageWithGemini($pdo, $query);
    exit; // Important: prevent further code execution
}

/**
 * Create new page using Gemini API
 * FIXED: This now calls Gemini API, stores HTML in database, and retrieves it back
 * 
 * Workflow:
 * 1. Call Gemini API with user query
 * 2. Get HTML response from Gemini
 * 3. Send HTML to database
 * 4. Retrieve page from database by ID
 * 5. Return database response to frontend
 */
function createNewPageWithGemini($pdo, $query) {
    try {
        error_log("Step 1: Preparing to call Gemini API for query: $query");
        
        // Initialize AI Service with Gemini
        $aiService = new AIService(AI_PROVIDER, GEMINI_API_KEY);
        
        // Create the prompt for Gemini
        $prompt = buildGeminiPrompt($query);
        
        error_log("Step 2: Calling Gemini API with prompt of " . strlen($prompt) . " characters");
        
        // Step 1: Call Gemini API and get HTML response
        error_log("🔄 STEP 1: Calling Gemini API for query: $query");
        $htmlContent = $aiService->generatePageContent($query, []);
        
        if (!$htmlContent || strlen($htmlContent) < 100) {
            error_log("❌ STEP 1 FAILED: Gemini API returned empty or invalid content");
            error_log("   Returned: " . (is_null($htmlContent) ? "NULL" : strlen($htmlContent) . " bytes"));
            throw new Exception('Gemini API did not return valid content. Check: 1) API key 2) API status 3) Network connection');
        }
        
        error_log("✓ STEP 1 COMPLETE: Received HTML content from Gemini (" . strlen($htmlContent) . " characters)");
        
        // Wrap raw Gemini content in proper HTML structure if needed
        if (stripos($htmlContent, '<html') === false && stripos($htmlContent, '<!DOCTYPE') === false) {
            $htmlContent = wrapHTMLContent($htmlContent);
            error_log("✓ STEP 1b: Wrapped content in HTML structure");
        }
        
        // Create slug and title
        $slug = createSlug($query);
        
        // Check if slug already exists and make it unique
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pages WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $slug = $slug . '-' . time();
            error_log("   Slug conflict resolved: $slug");
        }
        
        // Generate description
        $descriptions = [
            "Complete guide to {$query}",
            "Everything you need to know about {$query}",
            "In-depth overview of {$query}",
            "The ultimate resource for {$query}",
            "Comprehensive information on {$query}",
            "Expert insights on {$query}",
            "Detailed analysis of {$query}"
        ];
        $description = $descriptions[array_rand($descriptions)];
        
        error_log("🔄 STEP 2: Storing HTML content in database");
        
        // Step 2: Store HTML in database
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pages (
                    query, slug, title, description, html_content, 
                    ai_provider, ai_model, status, view_count, created_at, updated_at
                ) VALUES (
                    :query, :slug, :title, :description, :html_content,
                    :ai_provider, :ai_model, 'active', 1, NOW(), NOW()
                )
            ");
            
            $success = $stmt->execute([
                'query' => $query,
                'slug' => $slug,
                'title' => $query,
                'description' => $description,
                'html_content' => $htmlContent,
                'ai_provider' => AI_PROVIDER,
                'ai_model' => 'gemini-2.5-flash'
            ]);
            
            if (!$success) {
                throw new Exception('Database INSERT failed: ' . implode(', ', $stmt->errorInfo()));
            }
            
            $pageId = $pdo->lastInsertId();
            error_log("✓ STEP 2 COMPLETE: Page stored in database with ID: $pageId");
        } catch (Exception $dbError) {
            error_log("❌ STEP 2 FAILED: Database error");
            error_log("   Details: " . $dbError->getMessage());
            throw $dbError;
        }
        
        // Step 3: Retrieve page from database
        error_log("🔄 STEP 3: Retrieving page from database");
        
        try {
            $stmt = $pdo->prepare("
                SELECT id, query, slug, title, description, html_content, view_count,
                       created_at, updated_at
                FROM pages 
                WHERE id = :id
            ");
            
            $stmt->execute(['id' => $pageId]);
            $retrievedPage = $stmt->fetch();
            
            if (!$retrievedPage) {
                throw new Exception('Page not found in database after insertion (ID: ' . $pageId . ')');
            }
            
            error_log("✓ STEP 3 COMPLETE: Successfully retrieved page from database");
        } catch (Exception $dbError) {
            error_log("❌ STEP 3 FAILED: Failed to retrieve page");
            error_log("   Details: " . $dbError->getMessage());
            throw $dbError;
        }
        
        error_log("✓ All steps complete! Returning page to frontend");
        
        // Step 4: Return the page from database to frontend
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'cached' => false,
            'found_type' => 'newly_generated',
            'generation_method' => 'gemini_api',
            'page' => [
                'id' => (int)$retrievedPage['id'],
                'query' => $retrievedPage['query'],
                'slug' => $retrievedPage['slug'],
                'title' => $retrievedPage['title'],
                'description' => $retrievedPage['description'],
                'content' => $retrievedPage['html_content'],
                'views' => (int)$retrievedPage['view_count'],
                'createdAt' => $retrievedPage['created_at'],
                'updatedAt' => $retrievedPage['updated_at']
            ],
            'message' => 'New page generated with Gemini API and stored in database',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("❌ GENERATION FAILED: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        
        http_response_code(500);
        
        $errorMsg = $e->getMessage();
        $hints = [];
        
        // Provide helpful hints based on error message
        if (strpos($errorMsg, 'API key') !== false || strpos($errorMsg, 'invalid') !== false) {
            $hints[] = "Check your Gemini API key in /public_html/includes/apikeys.php";
            $hints[] = "Run: php /public_html/diagnose-api.php";
        } 
        
        if (strpos($errorMsg, 'returned empty') !== false) {
            $hints[] = "Gemini API returned no content - check API status";
            $hints[] = "Try a simpler query (fewer words)";
        }
        
        if (strpos($errorMsg, 'Database') !== false || strpos($errorMsg, 'database') !== false) {
            $hints[] = "Database connection issue";
            $hints[] = "Verify database credentials in config.php";
        }
        
        if (strpos($errorMsg, 'timed out') !== false) {
            $hints[] = "Request timeout - API is slow or unreachable";
            $hints[] = "Check your internet connection";
            $hints[] = "Try again in a moment";
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Page generation failed: ' . $errorMsg,
            'hints' => !empty($hints) ? $hints : null,
            'diagnostic_url' => '/diagnose-api.php',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

/**
 * Build a detailed prompt for Gemini API to create comprehensive HTML content
 */
function buildGeminiPrompt($query) {
    $prompt = <<<PROMPT
You are an expert web content creator and HTML developer. Your task is to create a comprehensive, beautiful, and well-structured HTML landing page about: "$query"

REQUIREMENTS:
1. Return ONLY valid HTML code (starting with <!DOCTYPE html> or <html>)
2. Include embedded CSS styling within <style> tags
3. Create a complete, self-contained HTML document
4. Use modern, professional design with:
   - Clean typography
   - Professional color scheme (blues, greys)
   - Responsive grid layouts
   - Clear section organization
   - Navigation and headers
5. Include these sections in the HTML:
   - What is "$query"? (Introduction with 2-3 paragraphs)
   - Key Concepts (5-7 important concepts with explanations)
   - Historical Context (Brief history or background)
   - Current State & Trends (Modern developments)
   - Real-World Applications (Practical use cases)
   - Benefits & Advantages (Why it matters)
   - Challenges & Considerations (Important caveats)
   - Getting Started (How to learn more)
   - Conclusion

6. Use proper HTML semantic elements:
   - <header>, <main>, <section>, <article>, <footer>
   - <h1>, <h2>, <h3> for hierarchy
   - <p>, <ul>, <ol> for content
   - <table> for structured data where appropriate
   - <figure> and <figcaption> for visuals

7. Include CSS styling that makes the page beautiful:
   - Gradient backgrounds
   - Proper spacing and padding
   - Professional fonts (use Google Fonts if needed)
   - Nice colors and visual hierarchy
   - Tables with proper styling
   - Cards or containers for sections

8. Add interactive elements where appropriate:
   - Hover effects on buttons/links
   - Smooth transitions
   - Visual separators between sections

9. Make it informative and accurate:
   - Include relevant facts and statistics
   - Provide actual useful information
   - Be comprehensive but concise
   - Use professional language

10. The HTML must be:
    - Valid and well-formed
    - Self-contained (no external dependencies except Google Fonts if needed)
    - Mobile-responsive
    - Accessible
    - Complete with a proper footer

Start your response with <!DOCTYPE html> and include everything needed for a complete landing page.
PROMPT;
    
    return $prompt;
}

/**
 * Wrap plain content in HTML structure if needed
 */
function wrapHTMLContent($content) {
    $styling = <<<CSS
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    :root {
        --primary: #2563eb;
        --secondary: #7c3aed;
        --dark: #1e293b;
        --light: #f8fafc;
        --border: #e2e8f0;
        --text: #334155;
        --text-light: #64748b;
    }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        line-height: 1.8;
        color: var(--text);
        background: linear-gradient(135deg, var(--light) 0%, #e0e7ff 100%);
        padding: 20px;
    }
    
    .container {
        max-width: 1000px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 60px 40px;
        text-align: center;
    }
    
    header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    main {
        padding: 40px;
    }
    
    section {
        margin-bottom: 40px;
    }
    
    h2 {
        color: var(--dark);
        font-size: 1.8rem;
        margin-bottom: 15px;
        border-bottom: 3px solid var(--primary);
        padding-bottom: 10px;
    }
    
    h3 {
        color: var(--dark);
        font-size: 1.3rem;
        margin-top: 25px;
        margin-bottom: 12px;
    }
    
    p {
        color: var(--text-light);
        margin-bottom: 15px;
        line-height: 1.8;
    }
    
    ul, ol {
        margin-left: 20px;
        margin-bottom: 20px;
    }
    
    li {
        margin-bottom: 10px;
        color: var(--text-light);
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        background: white;
        border-radius: 8px;
        overflow: hidden;
    }
    
    th {
        background: var(--primary);
        color: white;
        padding: 12px;
        text-align: left;
        font-weight: 700;
    }
    
    td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
    }
    
    footer {
        background: var(--dark);
        color: white;
        padding: 30px 40px;
        text-align: center;
    }
    
    footer a {
        color: var(--primary);
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        header {
            padding: 40px 20px;
        }
        
        header h1 {
            font-size: 1.8rem;
        }
        
        main {
            padding: 20px;
        }
        
        h2 {
            font-size: 1.4rem;
        }
    }
</style>
CSS;
    
    return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Generated Page</title>
    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap\" rel=\"stylesheet\">
    $styling
</head>
<body>
    <div class=\"container\">
        <main>
            $content
        </main>
        <footer>
            <p>&copy; 2026 Intebwio - AI-Powered Web Browser. Generated with Gemini API.</p>
        </footer>
    </div>
</body>
</html>";
}

/**
 * DEPRECATED: Old createNewPage function - replaced by createNewPageWithGemini
 */
function createNewPage($pdo, $query) {
    // This function is deprecated and replaced by createNewPageWithGemini
    // Kept for reference only
    error_log("WARNING: createNewPage() called - should use createNewPageWithGemini() instead");
    createNewPageWithGemini($pdo, $query);
}

/**
 * Check if page exists
 */
function checkPageExists($pdo, $query) {
    $stmt = $pdo->prepare("
        SELECT id, slug, title, query, created_at
        FROM pages 
        WHERE LOWER(TRIM(query)) = LOWER(TRIM(:query))
        AND status = 'active'
        LIMIT 1
    ");
    
    $stmt->execute(['query' => $query]);
    $page = $stmt->fetch();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'exists' => !empty($page),
        'page' => $page ?: null,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get page by slug
 */
function getPageBySlug($pdo, $slug) {
    $stmt = $pdo->prepare("
        SELECT id, query, slug, title, description, html_content, view_count,
               created_at, updated_at
        FROM pages 
        WHERE slug = :slug 
        AND status = 'active'
    ");
    
    $stmt->execute(['slug' => $slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Page not found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Update views
    updatePageViews($pdo, $page['id']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'page' => [
            'id' => (int)$page['id'],
            'query' => $page['query'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'description' => $page['description'],
            'content' => $page['html_content'],
            'views' => (int)$page['view_count'],
            'createdAt' => $page['created_at'],
            'updatedAt' => $page['updated_at']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update page view count
 */
function updatePageViews($pdo, $pageId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE pages 
            SET view_count = view_count + 1, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $pageId]);
    } catch (Exception $e) {
        // Log error but don't fail
        error_log('Error updating page views: ' . $e->getMessage());
    }
}

/**
 * Calculate Levenshtein similarity (0-1 score)
 */
function levenshteinSimilarity($str1, $str2) {
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    if ($str1 === $str2) return 1.0;
    
    if (strlen($str1) === 0 || strlen($str2) === 0) return 0.0;
    
    $distance = levenshtein($str1, $str2);
    $longer = strlen($str1) > strlen($str2) ? strlen($str1) : strlen($str2);
    
    return (double)(($longer - $distance) / $longer);
}

/**
 * Create URL slug from text
 */
function createSlug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 100);
}