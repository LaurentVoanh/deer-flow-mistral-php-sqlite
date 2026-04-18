<?php
/**
 * gateway.php - Routeur REST API + SSE Endpoint
 * Système DeerFlow PHP - Hostinger Compatible
 * 
 * Ce fichier sert de point d'entrée unique pour toutes les requêtes API
 * et gère le streaming SSE pour les mises à jour en temps réel.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/skills.php';
require_once __DIR__ . '/sandbox.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/subagent.php';
require_once __DIR__ . '/agent.php';
require_once __DIR__ . '/stream.php';

// Désactiver le buffer de sortie pour SSE
if (ob_get_level()) {
    ob_end_clean();
}

class APIGateway {
    
    private PDO $db;
    private LeadAgent $agent;
    private StreamHelper $stream;
    private array $allowedOrigins = ['*'];
    
    public function __construct() {
        $this->db = getDbConnection();
        $this->agent = getLeadAgent();
        $this->stream = new StreamHelper();
    }
    
    /**
     * Traite la requête HTTP entrante
     */
    public function handleRequest(): void {
        $this->setCorsHeaders();
        
        // Gérer les preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            return;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $this->getRequestPath();
        
        try {
            switch ($path) {
                // Routes principales
                case '/api/task':
                case '/api/task/':
                    $this->handleTaskRoute($method);
                    break;
                    
                case '/api/stream':
                case '/api/stream/':
                    $this->handleStreamRoute();
                    break;
                    
                case '/api/threads':
                case '/api/threads/':
                    $this->handleThreadsRoute($method);
                    break;
                    
                case '/api/files':
                case '/api/files/':
                    $this->handleFilesRoute($method);
                    break;
                    
                case '/api/memory':
                case '/api/memory/':
                    $this->handleMemoryRoute($method);
                    break;
                    
                case '/api/skills':
                case '/api/skills/':
                    $this->handleSkillsRoute($method);
                    break;
                    
                case '/api/stats':
                case '/api/stats/':
                    $this->handleStatsRoute();
                    break;
                    
                case '/api/health':
                case '/api/health/':
                    $this->handleHealthRoute();
                    break;
                    
                default:
                    // Vérifier les routes dynamiques
                    if (preg_match('#^/api/task/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
                        $this->handleTaskDetailRoute($method, $matches[1]);
                    } elseif (preg_match('#^/api/threads/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
                        $this->handleThreadDetailRoute($method, $matches[1]);
                    } elseif (preg_match('#^/api/files/([a-zA-Z0-9_-]+)/(.+)$#', $path, $matches)) {
                        $this->handleFileDownloadRoute($matches[1], $matches[2]);
                    } else {
                        $this->sendError('Not Found', 404);
                    }
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    /**
     * Gère la route /api/task
     */
    private function handleTaskRoute(string $method): void {
        if ($method === 'POST') {
            $input = $this->getJsonInput();
            
            $description = $input['description'] ?? $input['task'] ?? '';
            if (empty($description)) {
                $this->sendError('Description de tâche requise', 400);
                return;
            }
            
            $options = [
                'task_id' => $input['task_id'] ?? null,
                'thread_id' => $input['thread_id'] ?? null,
                'title' => $input['title'] ?? null,
                'use_planning' => $input['use_planning'] ?? ($input['planned'] ?? true)
            ];
            
            $result = $this->agent->executeTask($description, $options);
            $this->sendJson($result);
            
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/task/{id}
     */
    private function handleTaskDetailRoute(string $method, string $taskId): void {
        switch ($method) {
            case 'GET':
                $state = $this->agent->getTaskState($taskId);
                $this->sendJson($state);
                break;
                
            case 'DELETE':
            case 'POST':
                $action = $_GET['action'] ?? ($method === 'DELETE' ? 'cancel' : null);
                
                if ($action === 'cancel') {
                    $this->agent->cancelTask($taskId);
                    $this->sendJson(['success' => true, 'action' => 'cancelled']);
                } elseif ($action === 'resume') {
                    $result = $this->agent->resumeTask($taskId);
                    $this->sendJson($result);
                } else {
                    $this->sendError('Action non spécifiée', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/stream (SSE)
     */
    private function handleStreamRoute(): void {
        // Headers SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        // Désactiver le buffering PHP
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', false);
        implicit_flush(true);
        
        $taskId = $_GET['task_id'] ?? $_GET['thread'] ?? null;
        
        // Envoyer un premier événement pour établir la connexion
        echo "event: connected\n";
        echo "data: " . json_encode([
            'status' => 'connected',
            'task_id' => $taskId,
            'timestamp' => date('Y-m-d H:i:s')
        ]) . "\n\n";
        
        flush();
        
        if ($taskId) {
            // Récupérer l'historique des événements pour cette tâche
            $stmt = $this->db->prepare("
                SELECT event_type, event_data, created_at 
                FROM stream_events 
                WHERE task_id = :task_id 
                ORDER BY created_at DESC 
                LIMIT 50
            ");
            $stmt->execute([':task_id' => $taskId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach (array_reverse($events) as $event) {
                echo "event: history\n";
                echo "data: " . $event['event_data'] . "\n\n";
                flush();
                usleep(10000); // Petit délai entre les événements
            }
        }
        
        // Garder la connexion ouverte pour les nouveaux événements
        // Note: En production, utiliser WebSocket serait plus approprié
        set_time_limit(300);
        for ($i = 0; $i < 300; $i++) {
            echo ": ping\n\n";
            flush();
            sleep(1);
        }
    }
    
    /**
     * Gère la route /api/threads
     */
    private function handleThreadsRoute(string $method): void {
        if ($method === 'GET') {
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $status = $_GET['status'] ?? null;
            
            $query = "SELECT * FROM threads";
            $params = [];
            
            if ($status) {
                $query .= " WHERE status = :status";
                $params[':status'] = $status;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute($params);
            
            $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Compter le total
            $countQuery = "SELECT COUNT(*) FROM threads";
            if ($status) {
                $countQuery .= " WHERE status = :status";
            }
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($status ? [':status' => $status] : []);
            $total = (int)$countStmt->fetchColumn();
            
            $this->sendJson([
                'threads' => $threads,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total
                ]
            ]);
            
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/threads/{id}
     */
    private function handleThreadDetailRoute(string $method, string $threadId): void {
        if ($method === 'GET') {
            $state = $this->agent->getTaskState($threadId);
            $this->sendJson($state);
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/files
     */
    private function handleFilesRoute(string $method): void {
        if ($method === 'GET') {
            $threadId = $_GET['thread_id'] ?? null;
            
            if (!$threadId) {
                $this->sendError('thread_id requis', 400);
                return;
            }
            
            $sandbox = new Sandbox();
            $files = $sandbox->listFiles($threadId);
            
            $this->sendJson([
                'thread_id' => $threadId,
                'files' => $files,
                'path' => $sandbox->getThreadPath($threadId)
            ]);
            
        } elseif ($method === 'POST') {
            // Upload de fichier
            $threadId = $_POST['thread_id'] ?? null;
            
            if (!$threadId) {
                $this->sendError('thread_id requis', 400);
                return;
            }
            
            if (!isset($_FILES['file'])) {
                $this->sendError('Aucun fichier uploadé', 400);
                return;
            }
            
            $sandbox = new Sandbox();
            $sandbox->initializeThread($threadId);
            
            $file = $_FILES['file'];
            $filename = basename($file['name']);
            $targetPath = $sandbox->getThreadPath($threadId) . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $this->sendJson([
                    'success' => true,
                    'filename' => $filename,
                    'path' => $targetPath
                ]);
            } else {
                $this->sendError('Échec de l\'upload', 500);
            }
            
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/files/{threadId}/{filename}
     */
    private function handleFileDownloadRoute(string $threadId, string $filename): void {
        $sandbox = new Sandbox();
        $filePath = $sandbox->getThreadPath($threadId) . '/' . basename($filename);
        
        if (!file_exists($filePath)) {
            $this->sendError('Fichier non trouvé', 404);
            return;
        }
        
        // Déterminer le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Gère la route /api/memory
     */
    private function handleMemoryRoute(string $method): void {
        $memory = new MemoryManager();
        
        if ($method === 'GET') {
            $taskId = $_GET['task_id'] ?? null;
            $threadId = $_GET['thread_id'] ?? null;
            $query = $_GET['q'] ?? null;
            $limit = (int)($_GET['limit'] ?? 20);
            
            if ($query) {
                $results = $memory->search($query, $limit);
            } elseif ($taskId) {
                $results = $memory->searchByTask($taskId, $limit);
            } elseif ($threadId) {
                $results = $memory->searchByThread($threadId, $limit);
            } else {
                $results = $memory->getAll($limit);
            }
            
            $this->sendJson(['memories' => $results]);
            
        } elseif ($method === 'POST') {
            $input = $this->getJsonInput();
            
            $taskId = $input['task_id'] ?? null;
            $threadId = $input['thread_id'] ?? null;
            $content = $input['content'] ?? '';
            $type = $input['type'] ?? 'manual';
            $metadata = $input['metadata'] ?? [];
            
            if (empty($content)) {
                $this->sendError('Content requis', 400);
                return;
            }
            
            $result = $memory->store($taskId, $threadId, $content, $type, $metadata);
            $this->sendJson(['success' => true, 'memory_id' => $result]);
            
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                $this->sendError('ID requis', 400);
                return;
            }
            
            $memory->delete((int)$id);
            $this->sendJson(['success' => true]);
            
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/skills
     */
    private function handleSkillsRoute(string $method): void {
        $loader = getSkillsLoader();
        
        if ($method === 'GET') {
            $this->sendJson([
                'skills' => $loader->getAvailableSkills(),
                'metadata' => $loader->exportMetadata()
            ]);
        } else {
            $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Gère la route /api/stats
     */
    private function handleStatsRoute(): void {
        // Statistiques générales
        $stats = [
            'threads' => [
                'total' => (int)$this->db->query("SELECT COUNT(*) FROM threads")->fetchColumn(),
                'running' => (int)$this->db->query("SELECT COUNT(*) FROM threads WHERE status = 'running'")->fetchColumn(),
                'completed' => (int)$this->db->query("SELECT COUNT(*) FROM threads WHERE status = 'completed'")->fetchColumn(),
                'failed' => (int)$this->db->query("SELECT COUNT(*) FROM threads WHERE status = 'failed'")->fetchColumn()
            ],
            'memories' => [
                'total' => (int)$this->db->query("SELECT COUNT(*) FROM memories")->fetchColumn()
            ],
            'plans' => [
                'total' => (int)$this->db->query("SELECT COUNT(*) FROM plans")->fetchColumn(),
                'steps_total' => (int)$this->db->query("SELECT COUNT(*) FROM plan_steps")->fetchColumn(),
                'steps_completed' => (int)$this->db->query("SELECT COUNT(*) FROM plan_steps WHERE status = 'completed'")->fetchColumn()
            ],
            'storage' => [
                'sandbox_size' => $this->getDirectorySize(__DIR__ . '/sandbox')
            ]
        ];
        
        $this->sendJson($stats);
    }
    
    /**
     * Gère la route /api/health
     */
    private function handleHealthRoute(): void {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => DEERFLOW_VERSION,
            'php_version' => PHP_VERSION,
            'extensions' => [
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring')
            ],
            'database' => [
                'writable' => is_writable(__DIR__ . '/data')
            ],
            'mistral_api' => [
                'configured' => defined('MISTRAL_API_KEY') && !empty(MISTRAL_API_KEY)
            ]
        ];
        
        $this->sendJson($health);
    }
    
    /**
     * Obtient le chemin de la requête
     */
    private function getRequestPath(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        return rtrim($path, '/');
    }
    
    /**
     * Obtient l'input JSON
     */
    private function getJsonInput(): array {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * Définit les headers CORS
     */
    private function setCorsHeaders(): void {
        header('Access-Control-Allow-Origin: ' . implode(', ', $this->allowedOrigins));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    /**
     * Envoie une réponse JSON
     */
    private function sendJson(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Envoie une erreur
     */
    private function sendError(string $message, int $statusCode = 500): void {
        http_response_code($statusCode);
        echo json_encode([
            'error' => true,
            'message' => $message,
            'code' => $statusCode
        ], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Calcule la taille d'un dossier
     */
    private function getDirectorySize(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }
        
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}

// Initialisation et exécution
$gateway = new APIGateway();
$gateway->handleRequest();
