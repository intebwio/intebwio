<?php
/**
 * Intebwio - Page Generator
 * Generates HTML pages with graphics, tables, diagrams, etc.
 */

class PageGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate complete HTML page
     */
    public function generatePage($searchQuery, $results) {
        $htmlContent = $this->buildHTMLStructure($searchQuery, $results);
        return $htmlContent;
    }
    
    /**
     * Build HTML structure with all elements
     */
    private function buildHTMLStructure($searchQuery, $results) {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '<meta charset="UTF-8">' . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '<title>' . htmlspecialchars($searchQuery) . ' - Intebwio</title>' . "\n";
        $html .= '<meta name="description" content="Comprehensive information about ' . htmlspecialchars($searchQuery) . '">' . "\n";
        $html .= $this->getInlineStyles();
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        
        // Header
        $html .= $this->buildHeader($searchQuery);
        
        // Main content
        $html .= '<main class="intebwio-main">' . "\n";
        
        // Hero section
        $html .= $this->buildHeroSection($searchQuery);
        
        // Content grid
        $html .= $this->buildContentGrid($searchQuery, $results);
        
        // Statistics/Summary section
        $html .= $this->buildSummarySection($results);
        
        // Related content
        $html .= $this->buildRelatedContent($searchQuery);
        
        // Sidebar with navigation
        $html .= $this->buildSidebar($searchQuery, $results);
        
        $html .= '</main>' . "\n";
        $html .= $this->buildFooter();
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";
        
        return $html;
    }
    
    /**
     * Build header section
     */
    private function buildHeader($searchQuery) {
        $html = '<header class="intebwio-header">' . "\n";
        $html .= '<div class="header-container">' . "\n";
        $html .= '<h1 class="logo">Intebwio</h1>' . "\n";
        $html .= '<nav class="header-nav">' . "\n";
        $html .= '<ul>' . "\n";
        $html .= '<li><a href="/">Home</a></li>' . "\n";
        $html .= '<li><a href="/#explore">Explore</a></li>' . "\n";
        $html .= '<li><a href="/#trending">Trending</a></li>' . "\n";
        $html .= '<li><a href="/#about">About</a></li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</nav>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</header>' . "\n";
        return $html;
    }
    
    /**
     * Build hero section
     */
    private function buildHeroSection($searchQuery) {
        $html = '<section class="hero-section">' . "\n";
        $html .= '<div class="hero-content">' . "\n";
        $html .= '<h2 class="hero-title">' . htmlspecialchars($searchQuery) . '</h2>' . "\n";
        $html .= '<p class="hero-subtitle">Comprehensive guide curated from the best sources across the web</p>' . "\n";
        $html .= '<div class="hero-buttons">' . "\n";
        $html .= '<button class="btn btn-primary" onclick="scrollToContent()">Explore Content</button>' . "\n";
        $html .= '<button class="btn btn-secondary" onclick="shareContent()">Share This Page</button>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        return $html;
    }
    
    /**
     * Build content grid with cards
     */
    private function buildContentGrid($searchQuery, $results) {
        $html = '<section class="content-grid" id="content">' . "\n";
        $html .= '<div class="grid-container">' . "\n";
        $html .= '<h2 class="section-title">Information from Best Sources</h2>' . "\n";
        $html .= '<div class="cards-container">' . "\n";
        
        foreach ($results as $index => $result) {
            $html .= $this->buildResultCard($result, $index);
        }
        
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        return $html;
    }
    
    /**
     * Build individual result card
     */
    private function buildResultCard($result, $index) {
        $html = '<div class="card" data-index="' . $index . '">' . "\n";
        
        // Card header with source badge
        $html .= '<div class="card-header">' . "\n";
        $html .= '<span class="source-badge">' . htmlspecialchars($result['source_name'] ?? 'Web') . '</span>' . "\n";
        $html .= '<div class="relevance-score">' . round(($result['relevance_score'] ?? 0) * 100) . '%</div>' . "\n";
        $html .= '</div>' . "\n";
        
        // Card image
        if (!empty($result['image_url'])) {
            $html .= '<div class="card-image">' . "\n";
            $html .= '<img src="' . htmlspecialchars($result['image_url']) . '" alt="' . htmlspecialchars($result['title'] ?? '') . '" loading="lazy">' . "\n";
            $html .= '</div>' . "\n";
        }
        
        // Card content
        $html .= '<div class="card-content">' . "\n";
        $html .= '<h3 class="card-title">' . htmlspecialchars($result['title'] ?? 'Untitled') . '</h3>' . "\n";
        $html .= '<p class="card-description">' . htmlspecialchars(substr($result['description'] ?? '', 0, 200)) . '...</p>' . "\n";
        
        if (!empty($result['author'])) {
            $html .= '<p class="card-author">By ' . htmlspecialchars($result['author']) . '</p>' . "\n";
        }
        
        if (!empty($result['published_date'])) {
            $html .= '<p class="card-date">' . date('M d, Y', strtotime($result['published_date'])) . '</p>' . "\n";
        }
        
        $html .= '</div>' . "\n";
        
        // Card footer with links
        if (!empty($result['source_url'])) {
            $html .= '<div class="card-footer">' . "\n";
            $html .= '<a href="' . htmlspecialchars($result['source_url']) . '" class="btn btn-sm" target="_blank" rel="noopener">Read More</a>' . "\n";
            $html .= '</div>' . "\n";
        }
        
        $html .= '</div>' . "\n";
        return $html;
    }
    
    /**
     * Build summary section with statistics
     */
    private function buildSummarySection($results) {
        $html = '<section class="summary-section">' . "\n";
        $html .= '<div class="summary-container">' . "\n";
        $html .= '<h2 class="section-title">Summary Statistics</h2>' . "\n";
        
        // Statistics table
        $html .= '<table class="stats-table">' . "\n";
        $html .= '<tr>' . "\n";
        $html .= '<th>Source</th>' . "\n";
        $html .= '<th>Relevance</th>' . "\n";
        $html .= '<th>Date</th>' . "\n";
        $html .= '</tr>' . "\n";
        
        foreach ($results as $result) {
            $html .= '<tr>' . "\n";
            $html .= '<td>' . htmlspecialchars($result['source_name'] ?? 'Unknown') . '</td>' . "\n";
            $html .= '<td><div class="progress-bar">' . "\n";
            $html .= '<div class="progress-fill" style="width: ' . (($result['relevance_score'] ?? 0) * 100) . '%"></div>' . "\n";
            $html .= '</div></td>' . "\n";
            $html .= '<td>' . date('M d, Y', strtotime($result['published_date'] ?? 'now')) . '</td>' . "\n";
            $html .= '</tr>' . "\n";
        }
        
        $html .= '</table>' . "\n";
        
        // Diagram placeholder
        $html .= '<div class="diagram-container">' . "\n";
        $html .= '<h3>Content Distribution</h3>' . "\n";
        $html .= '<svg width="300" height="300" class="pie-chart">' . "\n";
        $html .= '<circle cx="150" cy="150" r="100" fill="none" stroke="#e0e0e0" stroke-width="50" stroke-dasharray="157 314" />' . "\n";
        $html .= '</svg>' . "\n";
        $html .= '</div>' . "\n";
        
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        return $html;
    }
    
    /**
     * Build related content section
     */
    private function buildRelatedContent($searchQuery) {
        $html = '<section class="related-section">' . "\n";
        $html .= '<div class="related-container">' . "\n";
        $html .= '<h2 class="section-title">Related Topics</h2>' . "\n";
        $html .= '<div class="related-tags">' . "\n";
        
        $relatedTerms = explode(' ', $searchQuery);
        foreach ($relatedTerms as $term) {
            if (strlen($term) > 3) {
                $html .= '<a href="?search=' . urlencode($term) . '" class="tag">' . htmlspecialchars($term) . '</a>' . "\n";
            }
        }
        
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</section>' . "\n";
        return $html;
    }
    
    /**
     * Build sidebar
     */
    private function buildSidebar($searchQuery, $results) {
        $html = '<aside class="sidebar">' . "\n";
        $html .= '<div class="sidebar-widget">' . "\n";
        $html .= '<h3>Quick Facts</h3>' . "\n";
        $html .= '<ul>' . "\n";
        $html .= '<li>Total Sources: ' . count($results) . '</li>' . "\n";
        $html .= '<li>Average Relevance: ' . round(array_sum(array_column($results, 'relevance_score')) / count($results) * 100) . '%</li>' . "\n";
        $html .= '<li>Last Updated: ' . date('M d, Y') . '</li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</aside>' . "\n";
        return $html;
    }
    
    /**
     * Build footer
     */
    private function buildFooter() {
        $html = '<footer class="intebwio-footer">' . "\n";
        $html .= '<div class="footer-container">' . "\n";
        $html .= '<div class="footer-section">' . "\n";
        $html .= '<h4>About Intebwio</h4>' . "\n";
        $html .= '<p>Intebwio is an AI-powered web browser that curates and aggregates content from the best sources.</p>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '<div class="footer-section">' . "\n";
        $html .= '<h4>Quick Links</h4>' . "\n";
        $html .= '<ul>' . "\n";
        $html .= '<li><a href="/">Home</a></li>' . "\n";
        $html .= '<li><a href="/">Privacy</a></li>' . "\n";
        $html .= '<li><a href="/">Terms</a></li>' . "\n";
        $html .= '</ul>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '<div class="footer-section">' . "\n";
        $html .= '<h4>Contact</h4>' . "\n";
        $html .= '<p>Email: info@intebwio.com</p>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '<div class="footer-bottom">' . "\n";
        $html .= '<p>&copy; 2026 Intebwio. All rights reserved.</p>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</footer>' . "\n";
        return $html;
    }
    
    /**
     * Get inline CSS styles
     */
    private function getInlineStyles() {
        $cssFile = __DIR__ . '/../css/intebwio-page.css';
        if (file_exists($cssFile)) {
            return '<style>' . file_get_contents($cssFile) . '</style>' . "\n";
        }
        return '<style>body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; }</style>' . "\n";
    }
}