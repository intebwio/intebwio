<?php
/**
 * Intebwio - Content Management API
 * Manage pages, elements, and content administration
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';

class ContentManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get featured pages with admin controls
     */
    public function getFeaturedPages($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    fp.id,
                    fp.page_id,
                    fp.title,
                    fp.description,
                    fp.featured_image_url,
                    fp.position,
                    fp.is_active,
                    fp.created_at,
                    p.view_count,
                    p.search_query
                FROM featured_pages fp
                LEFT JOIN pages p ON fp.page_id = p.id
                WHERE fp.is_active = 1
                ORDER BY fp.position ASC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return [
                'success' => true,
                'featured' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Add/update featured page
     */
    public function setFeaturedPage($pageId, $title, $description, $imageUrl, $position, $isActive = true) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO featured_pages (page_id, title, description, featured_image_url, position, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    description = VALUES(description),
                    featured_image_url = VALUES(featured_image_url),
                    position = VALUES(position),
                    is_active = VALUE(is_active)
            ");
            
            return [
                'success' => $stmt->execute([
                    $pageId, $title, $description, $imageUrl, $position, $isActive ? 1 : 0
                ])
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Remove featured page
     */
    public function removeFeaturedPage($featureId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM featured_pages WHERE id = ?");
            return [
                'success' => $stmt->execute([$featureId])
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get page elements (images, tables, diagrams)
     */
    public function getPageElements($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM page_elements
                WHERE page_id = ?
                ORDER BY element_type ASC, position ASC
            ");
            
            $stmt->execute([$pageId]);
            return [
                'success' => true,
                'elements' => $stmt->fetchAll(),
                'count' => $stmt->rowCount()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Add page element
     */
    public function addPageElement($pageId, $elementType, $content, $position = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO page_elements (page_id, element_type, content, position)
                VALUES (?, ?, ?, ?)
            ");
            
            return [
                'success' => $stmt->execute([
                    $pageId, $elementType, $content, $position
                ]),
                'element_id' => $this->pdo->lastInsertId()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update page element
     */
    public function updatePageElement($elementId, $content, $position = null) {
        try {
            if ($position !== null) {
                $stmt = $this->pdo->prepare("
                    UPDATE page_elements
                    SET content = ?, position = ?
                    WHERE id = ?
                ");
                return [
                    'success' => $stmt->execute([$content, $position, $elementId])
                ];
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE page_elements
                    SET content = ?
                    WHERE id = ?
                ");
                return [
                    'success' => $stmt->execute([$content, $elementId])
                ];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Delete page element
     */
    public function deletePageElement($elementId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM page_elements WHERE id = ?");
            return [
                'success' => $stmt->execute([$elementId])
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get page content and metadata for editing
     */
    public function getPageForEditing($pageId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, search_query, title, description, html_content,
                    view_count, created_at, updated_at, status
                FROM pages
                WHERE id = ?
            ");
            
            $stmt->execute([$pageId]);
            $page = $stmt->fetch();
            
            if (!$page) {
                return ['success' => false, 'message' => 'Page not found'];
            }
            
            $elements = $this->getPageElements($pageId);
            
            return [
                'success' => true,
                'page' => $page,
                'elements' => $elements['elements']
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update page HTML content (for admin editing)
     */
    public function updatePageContent($pageId, $title, $description, $htmlContent) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE pages
                SET title = ?, description = ?, html_content = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            return [
                'success' => $stmt->execute([
                    $title, $description, $htmlContent, $pageId
                ])
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get page status distribution
     */
    public function getPageStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(view_count) as avg_views,
                    SUM(view_count) as total_views
                FROM pages
                GROUP BY status
            ");
            
            return [
                'success' => true,
                'stats' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get duplicate/similar pages
     */
    public function getSimilarPages($pageId, $threshold = 0.75) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sp.similar_page_id,
                    sp.similarity_score,
                    p.search_query,
                    p.title,
                    p.view_count
                FROM similar_pages sp
                JOIN pages p ON sp.similar_page_id = p.id
                WHERE sp.page_id = ? AND sp.similarity_score >= ?
                ORDER BY sp.similarity_score DESC
            ");
            
            $stmt->execute([$pageId, $threshold]);
            return [
                'success' => true,
                'similar_pages' => $stmt->fetchAll()
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Merge duplicate pages
     */
    public function mergeDuplicatePages($pageId, $duplicatePageId) {
        try {
            $this->pdo->beginTransaction();
            
            // Combine view counts
            $stmt1 = $this->pdo->prepare("
                UPDATE pages 
                SET view_count = view_count + (
                    SELECT view_count FROM pages WHERE id = ?
                )
                WHERE id = ?
            ");
            $stmt1->execute([$duplicatePageId, $pageId]);
            
            // Redirect old page references
            $stmt2 = $this->pdo->prepare("
                UPDATE user_activity 
                SET page_id = ? 
                WHERE page_id = ?
            ");
            $stmt2->execute([$pageId, $duplicatePageId]);
            
            // Delete duplicate
            $stmt3 = $this->pdo->prepare("DELETE FROM pages WHERE id = ?");
            $stmt3->execute([$duplicatePageId]);
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Pages merged successfully'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

try {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $manager = new ContentManager($pdo);
    
    switch ($action) {
        case 'featured':
            $limit = intval($_GET['limit'] ?? 10);
            $result = $manager->getFeaturedPages($limit);
            echo json_encode($result);
            break;
            
        case 'set_featured':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST required']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $manager->setFeaturedPage(
                intval($data['page_id'] ?? 0),
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['image_url'] ?? '',
                intval($data['position'] ?? 0),
                $data['is_active'] ?? true
            );
            echo json_encode($result);
            break;
            
        case 'remove_featured':
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'DELETE required']);
                break;
            }
            $featureId = intval($_GET['id'] ?? 0);
            $result = $manager->removeFeaturedPage($featureId);
            echo json_encode($result);
            break;
            
        case 'elements':
            $pageId = intval($_GET['page_id'] ?? 0);
            if ($pageId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                break;
            }
            $result = $manager->getPageElements($pageId);
            echo json_encode($result);
            break;
            
        case 'page_for_edit':
            $pageId = intval($_GET['page_id'] ?? 0);
            if ($pageId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                break;
            }
            $result = $manager->getPageForEditing($pageId);
            echo json_encode($result);
            break;
            
        case 'update_content':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST required']);
                break;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $manager->updatePageContent(
                intval($data['page_id'] ?? 0),
                $data['title'] ?? '',
                $data['description'] ?? '',
                $data['html_content'] ?? ''
            );
            echo json_encode($result);
            break;
            
        case 'page_stats':
            $result = $manager->getPageStats();
            echo json_encode($result);
            break;
            
        case 'similar':
            $pageId = intval($_GET['page_id'] ?? 0);
            if ($pageId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'page_id required']);
                break;
            }
            $threshold = floatval($_GET['threshold'] ?? 0.75);
            $result = $manager->getSimilarPages($pageId, $threshold);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    error_log("Content Manager API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => DEBUG_MODE ? $e->getMessage() : ''
    ]);
}