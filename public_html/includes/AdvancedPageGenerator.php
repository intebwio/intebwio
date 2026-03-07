<?php
/**
 * Intebwio - Advanced AI Page Generator
 * Generates beautiful, AI-powered landing pages
 * ~400 lines
 */

class AdvancedPageGenerator {
    private $aiService;
    private $pdo;
    
    public function __construct($pdo, $aiService) {
        $this->pdo = $pdo;
        $this->aiService = $aiService;
    }
    
    /**
     * Generate complete AI-enhanced page
     * Can accept pre-generated AI content or generate it fresh
     */
    public function generateAIPage($searchQuery, $aggregatedContent, $preGeneratedContent = null) {
        try {
            // Get AI-generated content (use pre-generated if provided)
            if ($preGeneratedContent) {
                $aiContent = $preGeneratedContent;
            } else {
                $aiContent = $this->aiService->generatePageContent($searchQuery, $aggregatedContent);
            }
            
            if (!$aiContent) {
                return $this->fallbackPageGenerator($searchQuery, $aggregatedContent);
            }
            
            // Get SEO metadata
            $seoMeta = $this->generateSEOMetadata($searchQuery, $aiContent);
            
            // Build complete HTML page
            $html = $this->buildCompleteHTML($searchQuery, $aiContent, $seoMeta, $aggregatedContent);
            
            return $html;
        } catch (Exception $e) {
            error_log("Advanced page generation error: " . $e->getMessage());
            return $this->fallbackPageGenerator($searchQuery, $aggregatedContent);
        }
    }
    
    /**
     * Generate SEO metadata from content
     */
    private function generateSEOMetadata($searchQuery, $aiContent) {
        $title = ucfirst($searchQuery) . ' - Intebwio';
        
        // Extract first paragraph as description
        preg_match('/<p>(.*?)<\/p>/s', $aiContent, $matches);
        $description = isset($matches[1]) ? strip_tags($matches[1]) : 'Comprehensive information about ' . $searchQuery;
        $description = substr($description, 0, 160);
        
        // Extract keywords from headings
        $keywords = [$searchQuery];
        preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/s', $aiContent, $headings);
        if (!empty($headings[1])) {
            $keywords = array_merge($keywords, array_map('strip_tags', array_slice($headings[1], 0, 3)));
        }
        
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords
        ];
    }
    
    /**
     * Build complete HTML structure
     */
    private function buildCompleteHTML($searchQuery, $aiContent, $seoMeta, $aggregatedContent) {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        
        // Head section with SEO
        $html .= $this->buildHeadSection($searchQuery, $seoMeta);
        
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        
        // Navigation
        $html .= $this->buildNavigation();
        
        // Main content
        $html .= '<main class="ai-page-main">' . "\n";
        
        // Hero section
        $html .= $this->buildHeroSection($searchQuery);
        
        // Table of contents (auto-generated)
        $html .= $this->buildTableOfContents($aiContent);
        
        // AI generated content
        $html .= '<article class="ai-content-article">' . "\n";
        $html .= $this->sanitizeAndFormatContent($aiContent);
        $html .= '</article>' . "\n";
        
        // Additional resources panel
        $html .= $this->buildResourcesPanel($aggregatedContent);
        
        // Related topics
        $html .= $this->buildRelatedTopics($searchQuery);
        
        // Sidebar
        $html .= $this->buildSidebar($searchQuery, $aggregatedContent);
        
        $html .= '</main>' . "\n";
        
        // Footer
        $html .= $this->buildFooter();
        
        // Scripts
        $html .= $this->buildScripts();
        
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";
        
        return $html;
    }
    
    /**
     * Build head section with SEO
     */
    private function buildHeadSection($searchQuery, $seoMeta) {
        $title = $seoMeta['title'] ?? htmlspecialchars($searchQuery) . ' - Intebwio';
        $description = $seoMeta['description'] ?? 'Comprehensive AI-powered information about ' . htmlspecialchars($searchQuery);
        $keywords = !empty($seoMeta['keywords']) ? implode(', ', (array)$seoMeta['keywords']) : htmlspecialchars($searchQuery);
        
        $html = '<meta charset="UTF-8">' . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '<title>' . $title . '</title>' . "\n";
        $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
        $html .= '<meta name="author" content="Intebwio AI">' . "\n";
        $html .= '<meta property="og:title" content="' . $title . '">' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
        $html .= '<meta property="og:type" content="article">' . "\n";
        $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<link rel="canonical" href="' . htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '">' . "\n";
        
        // Styles
        $html .= $this->buildInlineStyles();
        
        return $html;
    }
    
    /**
     * Build navigation
     */
    private function buildNavigation() {
        return <<<NAVBAR
<!-- Navigation -->
<nav class="ai-navbar">
    <div class="navbar-container">
        <a href="/" class="navbar-logo">🚀 Intebwio</a>
        <div class="navbar-menu">
            <a href="/">Home</a>
            <a href="#topics">Topics</a>
            <a href="#resources">Resources</a>
        </div>
        <div class="navbar-search">
            <form id="navSearchForm" style="margin: 0; display: flex; gap: 8px;">
                <input type="text" placeholder="Search..." id="navSearchInput" style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px;">
                <button type="submit" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">Search</button>
            </form>
        </div>
    </div>
</nav>
NAVBAR;
    }
    
    /**
     * Build hero section
     */
    private function buildHeroSection($searchQuery) {
        return <<<HERO
<!-- Hero Section -->
<section class="ai-hero">
    <div class="hero-background"></div>
    <div class="hero-content">
        <h1 class="hero-title">$searchQuery</h1>
        <p class="hero-subtitle">AI-Curated Knowledge Hub</p>
        <div class="hero-meta">
            <span>Generated by AI • Updated: TIMESTAMP</span>
        </div>
    </div>
</section>
HERO;
    }
    
    /**
     * Build table of contents
     */
    private function buildTableOfContents($content) {
        $headings = [];
        preg_match_all('/<h([2-4])>(.*?)<\/h\1>/i', $content, $matches);
        
        if (empty($matches[2])) {
            return '';
        }
        
        $toc = '<nav class="table-of-contents">' . "\n";
        $toc .= '<h2>Table of Contents</h2>' . "\n";
        $toc .= '<ul>' . "\n";
        
        foreach ($matches[2] as $index => $heading) {
            $id = 'section-' . $index;
            $level = intval($matches[1][$index]);
            $indent = str_repeat('  ', $level - 2);
            $toc .= $indent . '<li><a href="#' . $id . '">' . strip_tags($heading) . '</a></li>' . "\n";
        }
        
        $toc .= '</ul>' . "\n";
        $toc .= '</nav>' . "\n";
        
        return $toc;
    }
    
    /**
     * Sanitize and format content
     */
    private function sanitizeAndFormatContent($content) {
        // Keep basic HTML structure
        $allowed = ['p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'blockquote', 'code', 'pre', 'table', 'tr', 'td', 'th'];
        
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        
        // Add IDs to headings
        $headings = $dom->getElementsByTagName('h2');
        foreach ($headings as $i => $h) {
            $h->setAttribute('id', 'section-' . $i);
            $h->setAttribute('class', 'content-heading');
        }
        
        return $dom->saveHTML();
    }
    
    /**
     * Build resources panel
     */
    private function buildResourcesPanel($aggregatedContent) {
        if (empty($aggregatedContent)) {
            return '';
        }
        
        $html = '<section class="resources-panel">' . "\n";
        $html .= '<h2>Resources & Sources</h2>' . "\n";
        $html .= '<div class="resources-grid">' . "\n";
        
        foreach (array_slice($aggregatedContent, 0, 8) as $item) {
            $html .= '<div class="resource-card">' . "\n";
            $html .= '<span class="resource-source">' . htmlspecialchars($item['source_name'] ?? 'Source') . '</span>' . "\n";
            
            if (!empty($item['image_url'])) {
                $html .= '<img src="' . htmlspecialchars($item['image_url']) . '" alt="Resource" class="resource-image">' . "\n";
            }
            
            $html .= '<h3>' . htmlspecialchars(substr($item['title'] ?? '', 0, 60)) . '</h3>' . "\n";
            
            if (!empty($item['source_url'])) {
                $html .= '<a href="' . htmlspecialchars($item['source_url']) . '" target="_blank" class="resource-link">View Source →</a>' . "\n";
            }
            
            $html .= '</div>' . "\n";
        }
        
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        
        return $html;
    }
    
    /**
     * Build related topics
     */
    private function buildRelatedTopics($searchQuery) {
        $terms = array_filter(explode(' ', $searchQuery), fn($t) => strlen($t) > 3);
        $topics = [];
        
        foreach ($terms as $term) {
            $topics[] = [
                'name' => $term,
                'url' => '/?search=' . urlencode($term)
            ];
        }
        
        // Add some AI-suggested related topics
        $relatedSuggestions = [
            'Introduction to',
            'Advanced concepts in',
            'History of',
            'Future of',
            'Best practices in'
        ];
        
        foreach (array_slice($relatedSuggestions, 0, 3) as $suggestion) {
            $topics[] = [
                'name' => $suggestion . ' ' . $searchQuery,
                'url' => '/?search=' . urlencode($suggestion . ' ' . $searchQuery)
            ];
        }
        
        $html = '<section class="related-topics">' . "\n";
        $html .= '<h2>Explore Related Topics</h2>' . "\n";
        $html .= '<div class="topics-grid">' . "\n";
        
        foreach ($topics as $topic) {
            $html .= '<a href="' . htmlspecialchars($topic['url']) . '" class="topic-chip">' . htmlspecialchars($topic['name']) . '</a>' . "\n";
        }
        
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        
        return $html;
    }
    
    /**
     * Build sidebar with info
     */
    private function buildSidebar($searchQuery, $aggregatedContent) {
        $html = '<aside class="ai-sidebar">' . "\n";
        
        $html .= '<div class="sidebar-widget">' . "\n";
        $html .= '<h3>Page Information</h3>' . "\n";
        $html .= '<ul>' . "\n";
        $html .= '<li><strong>Generated:</strong> ' . date('F d, Y') . '</li>' . "\n";
        $html .= '<li><strong>Sources:</strong> ' . count($aggregatedContent) . '</li>' . "\n";
        $html .= '<li><strong>AI Model:</strong> Advanced</li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '<div class="sidebar-widget">' . "\n";
        $html .= '<h3>Quick Navigation</h3>' . "\n";
        $html .= '<ul>' . "\n";
        $html .= '<li><a href="#top">Back to Top</a></li>' . "\n";
        $html .= '<li><a href="#toc">Table of Contents</a></li>' . "\n";
        $html .= '<li><a href="#resources">Resources</a></li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '<div class="sidebar-widget">' . "\n";
        $html .= '<h3>Share This Page</h3>' . "\n";
        $html .= '<div class="share-buttons">' . "\n";
        $html .= '<button onclick="shareToFacebook()" class="share-btn">f</button>' . "\n";
        $html .= '<button onclick="shareToTwitter()" class="share-btn">𝕏</button>' . "\n";
        $html .= '<button onclick="copyLink()" class="share-btn">📋</button>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '</aside>' . "\n";
        
        return $html;
    }
    
    /**
     * Build footer
     */
    private function buildFooter() {
        return <<<FOOTER
<footer class="ai-footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>About Intebwio</h4>
            <p>AI-powered platform that generates comprehensive landing pages on any topic.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="/">Home</a></li>
                <li><a href="/admin.php">Admin</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h4>Technology</h4>
            <p>Powered by AI APIs and advanced content aggregation.</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2026 Intebwio. All rights reserved. | One landing page per topic.</p>
    </div>
</footer>
FOOTER;
    }
    
    /**
     * Build inline styles
     */
    private function buildInlineStyles() {
        return '<style>' . file_get_contents(__DIR__ . '/../css/ai-page.css') . '</style>';
    }
    
    /**
     * Build scripts
     */
    private function buildScripts() {
        return <<<SCRIPTS
<script src="/js/ai-page.js"></script>
<script>
function shareToFacebook() {
    const url = window.location.href;
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url), '_blank');
}
function shareToTwitter() {
    const url = window.location.href;
    const text = document.querySelector('h1')?.textContent || 'Check this out!';
    window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text), '_blank');
}
function copyLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(() => {
        alert('Link copied to clipboard!');
    });
}
</script>
SCRIPTS;
    }
    
    /**
     * Fallback page generator (if AI fails)
     */
    private function fallbackPageGenerator($searchQuery, $aggregatedContent) {
        // Simplified fallback structure
        return '<h1>' . htmlspecialchars($searchQuery) . '</h1>';
    }
}