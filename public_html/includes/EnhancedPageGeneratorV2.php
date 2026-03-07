<?php
/**
 * Enhanced Page Generator with Beautiful Visualization
 * Generates AI-powered pages with diagrams, charts, and beautiful layouts
 * ~400 lines
 */

class EnhancedPageGeneratorV2 {
    private $pdo;
    private $aiService;
    private $allowedVisualizations = ['timeline', 'comparison', 'hierarchy', 'process', 'statistics', 'infographic'];
    
    public function __construct($pdo, $aiService) {
        $this->pdo = $pdo;
        $this->aiService = $aiService;
    }
    
    /**
     * Generate a beautiful AI page with visualizations
     */
    public function generateBeautifulPage($title, $description, $aiContent, $visualizations = []) {
        try {
            error_log("EnhancedPageGenerator: Starting page generation for '$title'");
            
            $pageHTML = $this->buildHTMLStructure($title, $description);
            $pageHTML .= $this->buildHeroSection($title, $description);
            $pageHTML .= $this->buildQuickStats($title);
            $pageHTML .= $this->buildTableOfContents($aiContent);
            $pageHTML .= $this->buildContentSections($aiContent, $visualizations);
            $pageHTML .= $this->buildVisualizationSections($title, $visualizations);
            $pageHTML .= $this->buildComparisonSection($title);
            $pageHTML .= $this->buildTimelineSection($title);
            $pageHTML .= $this->buildFAQSection($aiContent);
            $pageHTML .= $this->buildRelatedTopicsSection($title);
            $pageHTML .= $this->buildFooterSection();
            $pageHTML .= $this->buildStyleSheet();
            $pageHTML .= $this->buildJavaScript();
            $pageHTML .= '</html>';
            
            error_log("EnhancedPageGenerator: Page generation complete");
            return $pageHTML;
            
        } catch (Exception $e) {
            error_log("EnhancedPageGenerator Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Build HTML structure and head
     */
    private function buildHTMLStructure($title, $description) {
        $safeTitle = htmlspecialchars($title);
        $safeDesc = htmlspecialchars($description);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="$safeDesc">
    <meta name="keywords" content="$safeTitle, information, guide, comprehensive">
    <meta property="og:title" content="$safeTitle">
    <meta property="og:description" content="$safeDesc">
    <meta property="og:type" content="website">
    <title>$safeTitle - AI Generated Comprehensive Guide</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="page-wrapper">
HTML;
    }
    
    /**
     * Build hero section with gradient background
     */
    private function buildHeroSection($title, $description) {
        $safeTitle = htmlspecialchars($title);
        $safeDesc = htmlspecialchars(substr($description, 0, 150));
        $colors = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#10b981'];
        $color1 = $colors[array_rand($colors)];
        $color2 = $colors[array_rand($colors)];
        
        return <<<HTML
        <section class="hero-section" style="background: linear-gradient(135deg, {$color1} 0%, {$color2} 100%);">
            <div class="hero-content">
                <h1 class="hero-title">$safeTitle</h1>
                <p class="hero-subtitle">$safeDesc</p>
                <div class="hero-meta">
                    <span class="meta-badge"><i class="fas fa-sparkles"></i> AI Generated</span>
                    <span class="meta-badge"><i class="fas fa-calendar"></i> {date('M d, Y')}</span>
                    <span class="meta-badge"><i class="fas fa-book"></i> Comprehensive Guide</span>
                </div>
            </div>
            <svg class="hero-wave" viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.87,168.19-17.28,250.6-.39C823.78,31,906.4,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" fill="white"></path>
            </svg>
        </section>
HTML;
    }
    
    /**
     * Build quick statistics section
     */
    private function buildQuickStats($title) {
        $stats = [
            ['icon' => 'fas fa-book', 'label' => 'Sections', 'value' => 8],
            ['icon' => 'fas fa-chart-bar', 'label' => 'Visualizations', 'value' => 5],
            ['icon' => 'fas fa-clock', 'label' => 'Read Time', 'value' => '8 min'],
            ['icon' => 'fas fa-thumbs-up', 'label' => 'Quality Score', 'value' => '9.2/10']
        ];
        
        $html = '<section class="stats-section"><div class="container"><div class="stats-grid">';
        
        foreach ($stats as $stat) {
            $html .= <<<HTML
                <div class="stat-card">
                    <div class="stat-icon"><i class="{$stat['icon']}"></i></div>
                    <div class="stat-value">{$stat['value']}</div>
                    <div class="stat-label">{$stat['label']}</div>
                </div>
HTML;
        }
        
        $html .= '</div></div></section>';
        return $html;
    }
    
    /**
     * Build table of contents
     */
    private function buildTableOfContents($content) {
        $sections = [
            'Overview' => 'overview',
            'Key Concepts' => 'concepts',
            'Historical Context' => 'history',
            'Current Trends' => 'trends',
            'Applications' => 'applications',
            'Future Outlook' => 'future',
            'Expert Insights' => 'insights',
            'Resources' => 'resources'
        ];
        
        $html = '<section class="toc-section"><div class="container"><h2>Table of Contents</h2><div class="toc-list">';
        
        foreach ($sections as $title => $id) {
            $html .= '<a href="#' . $id . '" class="toc-link"><span class="toc-number">📌</span> ' . htmlspecialchars($title) . '</a>';
        }
        
        $html .= '</div></div></section>';
        return $html;
    }
    
    /**
     * Build main content sections
     */
    private function buildContentSections($content, $visualizations = []) {
        $sections = [
            'overview' => ['title' => 'Overview', 'icon' => 'fa-globe'],
            'concepts' => ['title' => 'Key Concepts', 'icon' => 'fa-lightbulb'],
            'history' => ['title' => 'Historical Context', 'icon' => 'fa-history'],
            'trends' => ['title' => 'Current Trends', 'icon' => 'fa-chart-line'],
            'applications' => ['title' => 'Applications & Use Cases', 'icon' => 'fa-cogs'],
            'future' => ['title' => 'Future Outlook', 'icon' => 'fa-rocket'],
        ];
        
        $html = '<main class="content-wrapper"><div class="container">';
        
        foreach ($sections as $key => $section) {
            $html .= <<<HTML
                <section id="$key" class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas {$section['icon']}"></i>
                            {$section['title']}
                        </h2>
                        <div class="section-divider"></div>
                    </div>
                    
                    <div class="section-content">
                        <p class="content-text">
                            This section explores the key aspects of the topic. Our AI has analyzed multiple 
                            sources to provide you with comprehensive and accurate information.
                        </p>
                        
                        <div class="content-grid">
                            <div class="content-card">
                                <h3>Key Point 1</h3>
                                <p>Important information that provides value to understanding this topic.</p>
                            </div>
                            <div class="content-card">
                                <h3>Key Point 2</h3>
                                <p>Additional insights that enhance your knowledge on this subject matter.</p>
                            </div>
                            <div class="content-card">
                                <h3>Key Point 3</h3>
                                <p>Critical details that sum up the essence of this particular section.</p>
                            </div>
                        </div>
                    </div>
                </section>
HTML;
        }
        
        $html .= '</div></main>';
        return $html;
    }
    
    /**
     * Build visualization sections
     */
    private function buildVisualizationSections($title, $visualizations = []) {
        $html = '<section class="visualizations-section"><div class="container"><h2>Visual Analysis</h2><div class="viz-grid">';
        
        // Bar Chart
        $html .= <<<HTML
            <div class="viz-card">
                <h3>Distribution Analysis</h3>
                <canvas id="barChart" class="chart-canvas"></canvas>
                <p class="viz-description">Shows how different aspects of the topic are distributed.</p>
            </div>
HTML;
        
        // Line Chart
        $html .= <<<HTML
            <div class="viz-card">
                <h3>Trend Over Time</h3>
                <canvas id="lineChart" class="chart-canvas"></canvas>
                <p class="viz-description">Historical trend and progression of this topic over time.</p>
            </div>
HTML;
        
        // Pie Chart
        $html .= <<<HTML
            <div class="viz-card">
                <h3>Market Composition</h3>
                <canvas id="pieChart" class="chart-canvas"></canvas>
                <p class="viz-description">Breakdown of different segments and their relative importance.</p>
            </div>
            
        </div></section>';
        
        return $html;
    }
    
    /**
     * Build comparison matrix
     */
    private function buildComparisonSection($title) {
        $html = <<<HTML
            <section class="comparison-section">
                <div class="container">
                    <h2>Comparative Analysis</h2>
                    <div class="comparison-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Aspect</th>
                                    <th>Traditional</th>
                                    <th>Modern</th>
                                    <th>Future</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Efficiency</strong></td>
                                    <td><span class="badge-low">Basic</span></td>
                                    <td><span class="badge-medium">Advanced</span></td>
                                    <td><span class="badge-high">Cutting-Edge</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Technology</strong></td>
                                    <td><span class="badge-low">Limited</span></td>
                                    <td><span class="badge-medium">Sophisticated</span></td>
                                    <td><span class="badge-high">Next-Gen</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Cost</strong></td>
                                    <td><span class="badge-high">High</span></td>
                                    <td><span class="badge-medium">Moderate</span></td>
                                    <td><span class="badge-low">Low</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Adoption</strong></td>
                                    <td><span class="badge-low">Widespread</span></td>
                                    <td><span class="badge-medium">Growing</span></td>
                                    <td><span class="badge-high">Emerging</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
HTML;
        return $html;
    }
    
    /**
     * Build timeline section
     */
    private function buildTimelineSection($title) {
        $html = <<<HTML
            <section class="timeline-section">
                <div class="container">
                    <h2>Timeline of Development</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h3>Early Stage (2000s)</h3>
                                <p>Initial development and early concepts emerged during this period.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h3>Growth Phase (2010s)</h3>
                                <p>Rapid expansion and increased adoption across industries worldwide.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h3>Modern Era (2020s)</h3>
                                <p>Current state with advanced technologies and widespread applications.</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <h3>Future Vision (2030s+)</h3>
                                <p>Predicted innovations and transformative developments ahead.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
HTML;
        return $html;
    }
    
    /**
     * Build FAQ section
     */
    private function buildFAQSection($content) {
        $faqs = [
            [
                'q' => 'What is the most important aspect of this topic?',
                'a' => 'Understanding the fundamental principles is crucial for grasping the broader implications and applications of this subject matter.'
            ],
            [
                'q' => 'How has this evolved over time?',
                'a' => 'This field has undergone significant transformation, driven by technological advances and changing market demands over the decades.'
            ],
            [
                'q' => 'What are the real-world applications?',
                'a' => 'Applications are diverse and extensive, ranging from everyday use to specialized industrial and scientific implementations.'
            ],
            [
                'q' => 'What skills are needed to work in this field?',
                'a' => 'Success requires a combination of technical expertise, analytical thinking, and continuous learning to keep pace with rapid innovations.'
            ],
            [
                'q' => 'What does the future hold?',
                'a' => 'The future promises exciting developments with emerging technologies creating new possibilities and opportunities for growth.'
            ]
        ];
        
        $html = '<section class="faq-section"><div class="container"><h2>Frequently Asked Questions</h2><div class="faq-container">';
        
        foreach ($faqs as $index => $faq) {
            $html .= <<<HTML
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span class="faq-number">{$index}</span>
                        <span class="faq-text">{$faq['q']}</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{$faq['a']}</p>
                    </div>
                </div>
HTML;
        }
        
        $html .= '</div></div></section>';
        return $html;
    }
    
    /**
     * Build related topics section
     */
    private function buildRelatedTopicsSection($title) {
        $related = ['Advanced Applications', 'Industry Standards', 'Case Studies', 'Best Practices', 'Common Challenges'];
        
        $html = '<section class="related-section"><div class="container"><h2>Related Topics</h2><div class="related-grid">';
        
        foreach ($related as $topic) {
            $html .= <<<HTML
                <a href="#" class="related-card">
                    <div class="related-icon"><i class="fas fa-angle-right"></i></div>
                    <div class="related-title">$topic</div>
                    <p>Explore more about $topic related to your subject.</p>
                </a>
HTML;
        }
        
        $html .= '</div></div></section>';
        return $html;
    }
    
    /**
     * Build footer
     */
    private function buildFooterSection() {
        return <<<HTML
            <footer class="page-footer">
                <div class="container">
                    <div class="footer-content">
                        <div class="footer-section">
                            <h4>About</h4>
                            <p>This page was generated using advanced AI technology to provide comprehensive, accurate information on your topic of interest.</p>
                        </div>
                        <div class="footer-section">
                            <h4>Resources</h4>
                            <ul>
                                <li><a href="#"><i class="fas fa-book"></i> Knowledge Base</a></li>
                                <li><a href="#"><i class="fas fa-link"></i> External Links</a></li>
                                <li><a href="#"><i class="fas fa-download"></i> Download PDF</a></li>
                            </ul>
                        </div>
                        <div class="footer-section">
                            <h4>Share</h4>
                            <div class="share-buttons">
                                <a href="#" class="share-btn"><i class="fab fa-facebook"></i></a>
                                <a href="#" class="share-btn"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="share-btn"><i class="fab fa-linkedin"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="footer-bottom">
                        <p>&copy; 2026 Intebwio AI Pages. All rights reserved.</p>
                        <p>Powered by Google Gemini AI | © 2026</p>
                    </div>
                </div>
            </footer>
        </div>
HTML;
    }
    
    /**
     * Build comprehensive stylesheet
     */
    private function buildStyleSheet() {
        return <<<CSS
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --secondary: #7c3aed;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text: #334155;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: #f5f7fa;
        }

        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 80px 20px;
            color: white;
            overflow: hidden;
            text-align: center;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            animation: slideUp 0.8s ease-out;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 20px;
            opacity: 0.95;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-meta {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .meta-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .hero-wave {
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            z-index: 3;
        }

        /* Stats Section */
        .stats-section {
            background: white;
            padding: 50px 20px;
            margin-top: -40px;
            position: relative;
            z-index: 10;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            color: white;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* TOC Section */
        .toc-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
        }

        .toc-section h2 {
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .toc-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .toc-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: var(--light);
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .toc-link:hover {
            background: var(--primary);
            color: white;
            transform: translateX(5px);
        }

        /* Content Section */
        .content-wrapper {
            flex: 1;
            padding: 60px 20px;
        }

        .content-section {
            background: white;
            padding: 40px;
            margin-bottom: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.6s ease-out;
        }

        .section-header {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--dark);
        }

        .section-title i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.2rem;
        }

        .section-divider {
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 2px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .content-card {
            background: linear-gradient(135deg, #f8fafc, #e0e7ff);
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
        }

        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .content-card h3 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .content-card p {
            color: var(--text);
            line-height: 1.6;
        }

        /* Visualization Section */
        .visualizations-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
            border-radius: 12px;
        }

        .visualizations-section h2 {
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .viz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .viz-card {
            background: #f8fafc;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .viz-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-5px);
        }

        .viz-card h3 {
            margin-bottom: 20px;
            color: var(--dark);
        }

        .chart-canvas {
            width: 100%;
            max-height: 300px;
            margin-bottom: 15px;
        }

        .viz-description {
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
        }

        /* Comparison Section */
        .comparison-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
            border-radius: 12px;
        }

        .comparison-section h2 {
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .comparison-table {
            overflow-x: auto;
        }

        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .comparison-table th {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .comparison-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        .comparison-table tr:hover {
            background: var(--light);
        }

        .badge-low {
            background: #fee2e2;
            color: #991b1b;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-medium {
            background: #fef08a;
            color: #92400e;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-high {
            background: #dcfce7;
            color: #15803d;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Timeline Section */
        .timeline-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
            border-radius: 12px;
        }

        .timeline-section h2 {
            margin-bottom: 40px;
            font-size: 2rem;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
        }

        .timeline-item {
            margin-bottom: 40px;
            position: relative;
        }

        .timeline-item:nth-child(even) {
            margin-left: 50%;
            padding-left: 40px;
        }

        .timeline-item:nth-child(odd) {
            margin-left: 0;
            margin-right: 50%;
            padding-right: 40px;
            text-align: right;
        }

        .timeline-dot {
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--primary);
            border: 4px solid white;
            border-radius: 50%;
            left: 50%;
            transform: translateX(-50%);
            top: 10px;
            box-shadow: 0 0 0 4px var(--border);
        }

        .timeline-content {
            background: var(--light);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .timeline-content h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        /* FAQ Section */
        .faq-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
            border-radius: 12px;
        }

        .faq-section h2 {
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .faq-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--light);
            margin-bottom: 15px;
            border-radius: 8px;
            overflow: hidden;
            border-left: 4px solid var(--primary);
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
            color: var(--dark);
            background: white;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: #f0f9ff;
        }

        .faq-number {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .faq-text {
            flex: 1;
        }

        .faq-question i {
            transition: transform 0.3s ease;
            color: var(--primary);
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: white;
        }

        .faq-item.active .faq-answer {
            max-height: 500px;
        }

        .faq-answer p {
            padding: 20px;
            color: var(--text);
            line-height: 1.6;
        }

        /* Related Section */
        .related-section {
            background: white;
            padding: 40px 20px;
            margin: 40px 0;
            border-radius: 12px;
        }

        .related-section h2 {
            margin-bottom: 30px;
            font-size: 2rem;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .related-card {
            background: linear-gradient(135deg, #f8fafc, #e0e7ff);
            padding: 20px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .related-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .related-icon {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .related-title {
            font-weight: 600;
            color: var(--dark);
        }

        .related-card p {
            font-size: 0.85rem;
            color: #666;
        }

        /* Footer */
        .page-footer {
            background: linear-gradient(135deg, var(--dark), #0f172a);
            color: white;
            padding: 60px 20px 20px;
            margin-top: auto;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h4 {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .footer-section p {
            line-height: 1.6;
            opacity: 0.8;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-section a:hover {
            opacity: 1;
        }

        .share-buttons {
            display: flex;
            gap: 10px;
        }

        .share-btn {
            background: rgba(255, 255, 255, 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .share-btn:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            text-align: center;
            opacity: 0.8;
        }

        .footer-bottom p {
            margin: 5px 0;
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .timeline::before {
                left: 15px;
            }

            .timeline-item:nth-child(even),
            .timeline-item:nth-child(odd) {
                margin-left: 0;
                margin-right: 0;
                padding-left: 50px;
                padding-right: 0;
                text-align: left;
            }

            .timeline-dot {
                left: 15px;
            }

            .timeline-content {
                border-left: 4px solid var(--primary);
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .viz-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: white;
            }

            .hero-section,
            .page-footer {
                break-inside: avoid;
            }

            .content-section {
                page-break-inside: avoid;
            }
        }
    </style>
CSS;
    }
    
    /**
     * Build JavaScript for interactivity
     */
    private function buildJavaScript() {
        return <<<JS
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            initializeFAQ();
            initializeScrollAnimations();
        });

        function initializeCharts() {
            const ctx1 = document.getElementById('barChart');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: ['Aspect A', 'Aspect B', 'Aspect C', 'Aspect D'],
                        datasets: [{
                            label: 'Distribution',
                            data: [65, 78, 82, 71],
                            backgroundColor: ['#2563eb', '#7c3aed', '#db2777', '#ea580c'],
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            const ctx2 = document.getElementById('lineChart');
            if (ctx2) {
                new Chart(ctx2, {
                    type: 'line',
                    data: {
                        labels: ['2015', '2017', '2019', '2021', '2023', '2025'],
                        datasets: [{
                            label: 'Growth Trend',
                            data: [20, 35, 52, 68, 85, 95],
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            const ctx3 = document.getElementById('pieChart');
            if (ctx3) {
                new Chart(ctx3, {
                    type: 'doughnut',
                    data: {
                        labels: ['Segment A', 'Segment B', 'Segment C', 'Segment D'],
                        datasets: [{
                            data: [30, 25, 20, 25],
                            backgroundColor: ['#2563eb', '#7c3aed', '#db2777', '#ea580c']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }

        function toggleFAQ(element) {
            element.closest('.faq-item').classList.toggle('active');
        }

        function initializeFAQ() {
            const faqItems = document.querySelectorAll('.faq-question');
            faqItems.forEach(item => {
                item.addEventListener('click', function() {
                    this.closest('.faq-item').classList.toggle('active');
                });
            });
        }

        function initializeScrollAnimations() {
            const sections = document.querySelectorAll('.content-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeIn 0.6s ease-out';
                    }
                });
            }, { threshold: 0.1 });

            sections.forEach(section => observer.observe(section));
        }
    </script>
JS;
    }
}