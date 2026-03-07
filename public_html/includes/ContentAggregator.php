<?php
/**
 * Intebwio - Content Aggregator
 * Fetches and aggregates content from multiple sources
 */

class ContentAggregator {
    private $pdo;
    private $cache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Aggregate content from multiple sources
     */
    public function aggregateContent($searchQuery, $pageId) {
        try {
            $results = [];
            
            // Wikipedia content
            $wikiResults = $this->getWikipediaContent($searchQuery);
            if ($wikiResults) {
                $results = array_merge($results, $wikiResults);
            }
            
            // Web search results
            $webResults = $this->getWebSearchResults($searchQuery);
            if ($webResults) {
                $results = array_merge($results, $webResults);
            }
            
            // News results
            $newsResults = $this->getNewsResults($searchQuery);
            if ($newsResults) {
                $results = array_merge($results, $newsResults);
            }
            
            // GitHub/Technical content
            $techResults = $this->getTechnicalContent($searchQuery);
            if ($techResults) {
                $results = array_merge($results, $techResults);
            }
            
            // Sort by relevance
            usort($results, function($a, $b) {
                return $b['relevance_score'] - $a['relevance_score'];
            });
            
            // Store results in database
            foreach ($results as $index => $result) {
                $result['position_index'] = $index;
                $this->storeResult($pageId, $result);
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Error aggregating content: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Wikipedia content
     */
    private function getWikipediaContent($searchQuery) {
        try {
            $url = "https://en.wikipedia.org/w/api.php?action=query&list=search&srsearch=" . 
                   urlencode($searchQuery) . "&format=json&srlimit=5";
            
            $response = $this->fetchUrl($url, 10);
            if (!$response) return [];
            
            $data = json_decode($response, true);
            $results = [];
            
            if (isset($data['query']['search'])) {
                foreach ($data['query']['search'] as $item) {
                    $results[] = [
                        'source_name' => 'Wikipedia',
                        'source_url' => 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $item['title']),
                        'title' => $item['title'],
                        'description' => strip_tags($item['snippet']),
                        'image_url' => NULL,
                        'author' => 'Wikipedia Community',
                        'published_date' => date('Y-m-d H:i:s'),
                        'relevance_score' => 0.85
                    ];
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Wikipedia fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get web search results using SerpAPI or similar
     */
    private function getWebSearchResults($searchQuery) {
        try {
            // Using a free web scraping approach
            $url = "https://www.google.com/search?q=" . urlencode($searchQuery);
            
            // Parse search results (simplified approach)
            $results = [];
            
            // In production, use proper API like SerpAPI, Google Search API, or Bing
            $searchResults = $this->scrapeGoogleResults($searchQuery);
            
            return $searchResults;
        } catch (Exception $e) {
            error_log("Web search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get news results
     */
    private function getNewsResults($searchQuery) {
        try {
            // Using NewsAPI or similar
            $results = [];
            
            // Simulated news results - in production use NewsAPI
            $newsItems = $this->fetchNewsItems($searchQuery);
            
            foreach ($newsItems as $item) {
                $results[] = [
                    'source_name' => 'News',
                    'source_url' => $item['url'] ?? '#',
                    'title' => $item['title'] ?? 'News Item',
                    'description' => $item['description'] ?? '',
                    'image_url' => $item['image'] ?? NULL,
                    'author' => $item['author'] ?? 'News Publisher',
                    'published_date' => $item['published_at'] ?? date('Y-m-d H:i:s'),
                    'relevance_score' => 0.80
                ];
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("News fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get technical/GitHub content
     */
    private function getTechnicalContent($searchQuery) {
        try {
            $results = [];
            
            // GitHub API
            $url = "https://api.github.com/search/repositories?q=" . 
                   urlencode($searchQuery) . "&sort=stars&order=desc&per_page=5";
            
            $response = $this->fetchUrl($url, 10);
            if (!$response) return [];
            
            $data = json_decode($response, true);
            
            if (isset($data['items'])) {
                foreach ($data['items'] as $repo) {
                    $results[] = [
                        'source_name' => 'GitHub',
                        'source_url' => $repo['html_url'],
                        'title' => $repo['full_name'],
                        'description' => $repo['description'] ?? 'No description',
                        'image_url' => $repo['owner']['avatar_url'] ?? NULL,
                        'author' => $repo['owner']['login'],
                        'published_date' => $repo['created_at'],
                        'relevance_score' => 0.75
                    ];
                }
            }
            
            return $results;
        } catch (Exception $e) {
            error_log("Technical content fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fetch URL with timeout
     */
    private function fetchUrl($url, $timeout = 10) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'Intebwio/1.0 (+http://intebwio.com)',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return $response;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Fetch URL error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Scrape Google search results (simplified)
     */
    private function scrapeGoogleResults($searchQuery) {
        $results = [];
        
        // Mock data - in production, use proper web scraping or API
        $mockResults = [
            [
                'source_name' => 'Web',
                'source_url' => 'https://example.com/result1',
                'title' => ucfirst($searchQuery) . ' - Overview',
                'description' => 'Comprehensive information about ' . $searchQuery . '. Find the latest details and resources.',
                'image_url' => 'https://via.placeholder.com/300x200?text=' . urlencode($searchQuery),
                'author' => 'Web Publisher',
                'published_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'relevance_score' => 0.90
            ],
            [
                'source_name' => 'Web',
                'source_url' => 'https://example.com/result2',
                'title' => ucfirst($searchQuery) . ' - Guide',
                'description' => 'Complete guide and tutorial about ' . $searchQuery . ' with examples and best practices.',
                'image_url' => 'https://via.placeholder.com/300x200?text=Guide',
                'author' => 'Expert Author',
                'published_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'relevance_score' => 0.85
            ],
            [
                'source_name' => 'Web',
                'source_url' => 'https://example.com/result3',
                'title' => ucfirst($searchQuery) . ' - Latest News',
                'description' => 'Recent updates and news related to ' . $searchQuery . ' from reliable sources.',
                'image_url' => 'https://via.placeholder.com/300x200?text=News',
                'author' => 'News Team',
                'published_date' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                'relevance_score' => 0.88
            ]
        ];
        
        return $mockResults;
    }
    
    /**
     * Fetch news items
     */
    private function fetchNewsItems($searchQuery) {
        // Mock data - integrate with NewsAPI in production
        return [
            [
                'title' => 'Latest ' . $searchQuery . ' News',
                'description' => 'Breaking news about ' . $searchQuery,
                'url' => '#',
                'author' => 'News Agency',
                'published_at' => date('Y-m-d H:i:s'),
                'image' => 'https://via.placeholder.com/300x200?text=News'
            ]
        ];
    }
    
    /**
     * Store search result in database
     */
    private function storeResult($pageId, $result) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO search_results 
                (page_id, source_name, source_url, title, description, image_url, author, published_date, relevance_score, position_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $pageId,
                $result['source_name'] ?? NULL,
                $result['source_url'] ?? NULL,
                $result['title'] ?? NULL,
                substr($result['description'] ?? '', 0, 1000),
                $result['image_url'] ?? NULL,
                $result['author'] ?? NULL,
                $result['published_date'] ?? date('Y-m-d H:i:s'),
                $result['relevance_score'] ?? 0.5,
                $result['position_index'] ?? 0
            ]);
        } catch (Exception $e) {
            error_log("Error storing result: " . $e->getMessage());
            return false;
        }
    }
}