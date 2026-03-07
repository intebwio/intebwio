<?php
/**
 * Intebwio - AI Integration Service
 * Integrates with OpenAI, Google Gemini, or other AI APIs for page generation
 * ~300 lines
 */

class AIService {
    private $apiProvider;
    private $apiKey;
    private $model;
    private $maxTokens = 4000;
    
    public function __construct($provider = 'openai', $apiKey = '') {
        $this->apiProvider = $provider;
        $this->apiKey = $apiKey ?? getenv('AI_API_KEY');
        $this->model = $this->getModelForProvider($provider);
    }
    
    /**
     * Generate comprehensive page content using AI
     */
    public function generatePageContent($searchQuery, $aggregatedContent = []) {
        try {
            error_log("=== AI Content Generation started for: " . $searchQuery);
            error_log("Using AI Provider: " . $this->apiProvider);
            error_log("Available aggregated content items: " . count($aggregatedContent));
            
            $startTime = microtime(true);
            $prompt = $this->buildPrompt($searchQuery, $aggregatedContent);
            $promptTime = microtime(true) - $startTime;
            
            error_log("Prompt built in " . round($promptTime, 2) . " seconds (" . strlen($prompt) . " chars)");
            
            switch ($this->apiProvider) {
                case 'openai':
                    error_log("Calling OpenAI API...");
                    $result = $this->callOpenAI($prompt);
                    break;
                case 'gemini':
                    error_log("Calling Gemini API...");
                    $result = $this->callGemini($prompt);
                    break;
                case 'anthropic':
                    error_log("Calling Anthropic API...");
                    $result = $this->callAnthropic($prompt);
                    break;
                default:
                    error_log("Unknown AI provider: " . $this->apiProvider . ", using fallback");
                    $result = $this->generateFallbackContent($searchQuery, $aggregatedContent);
            }
            
            $totalTime = microtime(true) - $startTime;
            error_log("AI Content Generation completed in " . round($totalTime, 2) . " seconds");
            
            return $result;
            
        } catch (Exception $e) {
            error_log("AI Generation error: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * Build comprehensive prompt for AI
     */
    private function buildPrompt($searchQuery, $aggregatedContent = []) {
        $contentSummary = '';
        if (!empty($aggregatedContent)) {
            $contentSummary = "Based on this research:\n";
            foreach (array_slice($aggregatedContent, 0, 5) as $item) {
                $contentSummary .= "- " . ($item['title'] ?? 'Info') . ": " . substr($item['description'] ?? '', 0, 200) . "\n";
            }
        }
        
        $prompt = <<<PROMPT
You are an expert content curator creating a comprehensive, professional landing page about: "$searchQuery"

$contentSummary

Create a detailed, well-structured HTML landing page that includes:

1. EXECUTIVE SUMMARY: 2-3 paragraphs explaining what "$searchQuery" is
2. KEY CONCEPTS: 5-7 important concepts with explanations
3. HISTORICAL CONTEXT: Brief history or background
4. CURRENT STATE: Modern developments and trends
5. APPLICATIONS/USE CASES: Real-world applications
6. FUTURE PROSPECTS: What's ahead
7. RESOURCES & LEARNING: Where to learn more
8. FAQ SECTION: 5-7 common questions

Format with proper HTML structure, include nice formatting with sections, lists, and emphasis.
Make it professional, accurate, and engaging. Include relevant statistics and facts where appropriate.
PROMPT;
        
        return $prompt;
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt) {
        if (!$this->apiKey) {
            error_log("OpenAI API Error: No API key provided");
            return null;
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional content curator and web designer. Create comprehensive, well-formatted landing pages with detailed information.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 300,  // Increased to 300 seconds for API processing
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log curl errors
        if ($curlError) {
            error_log("OpenAI API cURL Error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices']) && !empty($result['choices'])) {
                $text = $result['choices'][0]['message']['content'] ?? null;
                if ($text) {
                    return $text;
                }
            }
            error_log("OpenAI API: Invalid response structure - " . substr($response, 0, 500));
            return null;
        }
        
        // Log error responses
        error_log("OpenAI API Error - HTTP $httpCode: " . substr($response, 0, 500));
        return null;
    }
    
    /**
     * Call Google Gemini API
     */
    private function callGemini($prompt) {
        if (!$this->apiKey) {
            error_log("❌ Gemini API Error: No API key provided");
            error_log("   Check config.php - GEMINI_API_KEY is not defined");
            return null;
        }
        
        // Check for placeholder or invalid key
        if (strpos($this->apiKey, 'YOUR_') !== false) {
            error_log("❌ Gemini API Error: API key is a PLACEHOLDER");
            error_log("   Current value: " . $this->apiKey);
            error_log("   Action: Set actual API key in apikeys.php");
            error_log("   Get key from: https://aistudio.google.com/apikey");
            return null;
        }
        
        if (strlen($this->apiKey) < 10) {
            error_log("❌ Gemini API Error: API key too short (" . strlen($this->apiKey) . " chars)");
            error_log("   Valid keys should be 40+ characters");
            return null;
        }
        
        // Use the model from configuration
        $model = $this->model ?: 'gemini-2.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $this->apiKey;
        
        // Limit prompt size for API
        $maxPromptChars = 30000;
        if (strlen($prompt) > $maxPromptChars) {
            error_log("⚠️  Prompt size (" . strlen($prompt) . ") > max (" . $maxPromptChars . "). Truncating.");
            $prompt = substr($prompt, 0, $maxPromptChars);
        }
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 300,  // 5 minutes for API to process
            CURLOPT_CONNECTTIMEOUT => 30,  // 30 seconds to connect
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        error_log("📤 Gemini API: Sending request to $model model...");
        error_log("   Prompt size: " . strlen($prompt) . " characters");
        error_log("   Request timeout: 300s | Connection timeout: 30s");
        
        $startTime = microtime(true);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $elapsedTime = microtime(true) - $startTime;
        
        curl_close($ch);
        
        error_log("📥 Gemini API: Response received in " . round($elapsedTime, 2) . "s (HTTP $httpCode)");
        
        // Log curl errors
        if ($curlError) {
            error_log("❌ Gemini API cURL Error: " . $curlError);
            if (strpos($curlError, 'timed out') !== false) {
                error_log("   --> Connection timed out. API server may be slow or unreachable.");
                error_log("   --> Check your internet connection and try again.");
            }
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates']) && !empty($result['candidates'])) {
                $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($text) {
                    error_log("✓ Gemini API: Successfully generated content (" . strlen($text) . " characters)");
                    return $text;
                }
            }
            error_log("❌ Gemini API: Invalid response structure");
            error_log("   Response start: " . substr($response, 0, 200));
            return null;
        }
        
        // Log error responses with more detail
        $responseData = json_decode($response, true);
        $errorMsg = "❌ Gemini API Error - HTTP $httpCode";
        
        if (isset($responseData['error']['message'])) {
            $errorMsg .= ": " . $responseData['error']['message'];
        }
        error_log($errorMsg);
        
        // Provide specific help for common errors
        if ($httpCode === 400) {
            error_log("   Problem: Bad Request - Invalid prompt or parameters");
            error_log("   Solution: Try a simpler query (shorter text)");
        } else if ($httpCode === 401) {
            error_log("   Problem: Unauthorized - API key issue");
            error_log("   Solution: Verify API key is correct");
        } else if ($httpCode === 403) {
            error_log("   Problem: Forbidden - Access denied");
            if (isset($responseData['error']['message']) && strpos($responseData['error']['message'], 'leaked') !== false) {
                error_log("   --> API key has been reported as leaked!");
                error_log("   --> Get a new key: https://aistudio.google.com/apikey");
            } else {
                error_log("   Solution: Check API quota or key permissions");
            }
        } else if ($httpCode === 429) {
            error_log("   Problem: Rate Limited - Too many requests");
            error_log("   Solution: Wait a moment and try again");
        } else if ($httpCode === 500 || $httpCode === 503) {
            error_log("   Problem: Server Error - Google API may be down");
            error_log("   Solution: Try again in a few moments");
        }
        
        error_log("Full response: " . substr(json_encode($responseData), 0, 500));
        
        return null;
    }
    
    /**
     * Call Anthropic Claude API
     */
    private function callAnthropic($prompt) {
        if (!$this->apiKey) {
            error_log("Anthropic API Error: No API key provided");
            return null;
        }
        
        $url = 'https://api.anthropic.com/v1/messages';
        
        $data = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 4000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 300,  // Increased to 300 seconds for API processing
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log curl errors
        if ($curlError) {
            error_log("Anthropic API cURL Error: " . $curlError);
            return null;
        }
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['content']) && !empty($result['content'])) {
                $text = $result['content'][0]['text'] ?? null;
                if ($text) {
                    return $text;
                }
            }
            error_log("Anthropic API: Invalid response structure - " . substr($response, 0, 500));
            return null;
        }
        
        // Log error responses
        error_log("Anthropic API Error - HTTP $httpCode: " . substr($response, 0, 500));
        return null;
    }
    
    /**
     * Generate fallback content without AI
     */
    private function generateFallbackContent($searchQuery, $aggregatedContent = []) {
        $html = "<section class='ai-generated-content'>";
        $html .= "<h2>Comprehensive Guide to " . htmlspecialchars($searchQuery) . "</h2>";
        
        if (!empty($aggregatedContent)) {
            $html .= "<h3>Overview</h3>";
            $html .= "<p>" . htmlspecialchars($aggregatedContent[0]['description'] ?? 'Information about ' . $searchQuery) . "</p>";
            
            $html .= "<h3>Key Sources</h3>";
            $html .= "<ul>";
            foreach (array_slice($aggregatedContent, 0, 10) as $item) {
                $html .= "<li>";
                if (!empty($item['source_url'])) {
                    $html .= "<a href='" . htmlspecialchars($item['source_url']) . "' target='_blank'>";
                }
                $html .= htmlspecialchars($item['title'] ?? 'Information');
                if (!empty($item['source_url'])) {
                    $html .= "</a>";
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        }
        
        $html .= "</section>";
        return $html;
    }
    
    /**
     * Get appropriate model for provider
     */
    private function getModelForProvider($provider) {
        $models = [
            'openai' => 'gpt-4-turbo',
            'gemini' => 'gemini-2.5-flash',  // Latest Gemini model with best performance
            'anthropic' => 'claude-3-sonnet-20240229'
        ];
        return $models[$provider] ?? 'gpt-4-turbo';
    }
    
    /**
     * Analyze content relevance using AI
     */
    public function analyzeRelevance($query, $content) {
        $prompt = "Rate the relevance of this content to the query '$query' on a scale of 0-1:\n\n$content\n\nRespond with just a number.";
        
        $response = match($this->apiProvider) {
            'openai' => $this->callOpenAI($prompt),
            'gemini' => $this->callGemini($prompt),
            'anthropic' => $this->callAnthropic($prompt),
            default => '0.5'
        };
        
        preg_match('/\d+\.?\d*/', $response, $matches);
        return floatval($matches[0] ?? 0.5);
    }
    
    /**
     * Generate SEO metadata
     */
    public function generateSEOMetadata($searchQuery, $content) {
        $prompt = "Generate SEO metadata for content about '$searchQuery'. Provide JSON format: {\"title\": \"\", \"description\": \"\", \"keywords\": []}";
        
        $response = match($this->apiProvider) {
            'openai' => $this->callOpenAI($prompt),
            'gemini' => $this->callGemini($prompt),
            'anthropic' => $this->callAnthropic($prompt),
            default => '{}'
        };
        
        $json = json_decode($response, true);
        return $json ?: [];
    }
}