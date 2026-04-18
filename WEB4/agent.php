<?php
/**
 * agent.php - Lead Agent Orchestrateur
 * Système DeerFlow PHP - Hostinger Compatible
 * 
 * Ce fichier est le cerveau du système. Il orchestre tous les modules
 * pour exécuter des tâches complexes de manière autonome.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/skills.php';
require_once __DIR__ . '/sandbox.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/planner.php';
require_once __DIR__ . '/subagent.php';
require_once __DIR__ . '/stream.php';

class LeadAgent {
    
    private PDO $db;
    private TaskPlanner $planner;
    private SubAgent $subAgent;
    private MemoryManager $memory;
    private Sandbox $sandbox;
    private SSEHelper $stream;
    
    public function __construct() {
        $this->db = getDbConnection();
        $this->planner = getTaskPlanner();
        $this->subAgent = getSubAgent();
        $this->memory = new MemoryManager();
        $this->sandbox = new Sandbox();
        $this->stream = null; // Sera initialisé avec un threadId quand nécessaire
    }
    
    /**
     * Exécute une tâche complète (planifiée ou simple)
     */
    public function executeTask(
        string $taskDescription,
        array $options = []
    ): array {
        set_time_limit(600);
        
        // Générer un ID unique pour la tâche
        $taskId = $options['task_id'] ?? 'task_' . uniqid() . '_' . time();
        $threadId = $options['thread_id'] ?? $taskId;
        $title = $options['title'] ?? substr($taskDescription, 0, 50);
        $usePlanning = $options['use_planning'] ?? true;
        
        // Initialiser le stream helper avec le threadId
        $this->stream = new SSEHelper($threadId, $taskId);
        
        try {
            // Créer le thread dans la base de données
            $this->createThread($taskId, $threadId, $title, $taskDescription);
            
            // Initialiser le sandbox
            $this->sandbox->initializeThread($threadId);
            
            // Envoyer l'événement de début
            $this->stream->send('task_start', [
                'task_id' => $taskId,
                'title' => $title,
                'description' => $taskDescription,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            if ($usePlanning) {
                // Mode planifié : décomposer et exécuter étape par étape
                return $this->executePlannedTask($taskId, $threadId, $taskDescription);
            } else {
                // Mode simple : exécution directe
                return $this->executeSimpleTask($taskId, $threadId, $taskDescription);
            }
            
        } catch (Exception $e) {
            $this->updateThreadStatus($taskId, 'failed');
            
            return [
                'success' => false,
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Exécute une tâche avec planification
     */
    private function executePlannedTask(
        string $taskId,
        string $threadId,
        string $description
    ): array {
        // Étape 1: Planification
        $this->stream->send('planning_start', ['task_id' => $taskId]);
        
        $planResult = $this->planner->decomposeTask($taskId, $description);
        
        if (!$planResult['success']) {
            $this->stream->send('planning_error', [
                'task_id' => $taskId,
                'error' => $planResult['error']
            ]);
            
            // Utiliser le plan de secours si disponible
            if (isset($planResult['fallback_plan'])) {
                $planResult['plan'] = $planResult['fallback_plan'];
                $planResult['is_fallback'] = true;
            } else {
                return [
                    'success' => false,
                    'task_id' => $taskId,
                    'error' => 'Échec de la planification',
                    'details' => $planResult
                ];
            }
        }
        
        $plan = $planResult['plan'];
        $this->stream->send('planning_complete', [
            'task_id' => $taskId,
            'summary' => $plan['summary'],
            'steps_count' => count($plan['steps']),
            'is_fallback' => $planResult['is_fallback'] ?? false
        ]);
        
        // Sauvegarder le résultat de la planification en mémoire
        $this->memory->store(
            $taskId,
            $threadId,
            "Plan: {$plan['summary']}",
            'plan_result',
            ['plan' => $plan]
        );
        
        // Étape 2: Exécution des sous-tâches
        $results = [];
        $totalSteps = count($plan['steps']);
        $completedSteps = 0;
        
        foreach ($plan['steps'] as $index => $step) {
            $stepNumber = $index + 1;
            
            $this->stream->send('step_start', [
                'task_id' => $taskId,
                'step_id' => $step['step_id'],
                'step_number' => $stepNumber,
                'total_steps' => $totalSteps,
                'title' => $step['title'],
                'skill' => $step['skill_required'],
                'model' => $step['model_recommended']
            ]);
            
            // Mettre à jour le statut de l'étape
            $this->planner->updateStepStatus($taskId, $step['step_id'], 'running');
            
            // Récupérer les résultats des étapes précédentes
            $previousResults = array_filter($results, function($key) use ($step) {
                return in_array($key, $step['dependencies'] ?? []);
            }, ARRAY_FILTER_USE_KEY);
            
            // Exécuter l'étape
            $stepResult = $this->subAgent->executeStep($taskId, $step, $previousResults);
            
            $results[$step['step_id']] = $stepResult;
            
            if ($stepResult['success']) {
                $this->planner->updateStepStatus(
                    $taskId,
                    $step['step_id'],
                    'completed',
                    substr($stepResult['content'], 0, 2000)
                );
                
                $this->stream->send('step_complete', [
                    'task_id' => $taskId,
                    'step_id' => $step['step_id'],
                    'step_number' => $stepNumber,
                    'files_created' => $stepResult['files_created'] ?? [],
                    'model_used' => $stepResult['model_used']
                ]);
                
                $completedSteps++;
            } else {
                $this->planner->updateStepStatus($taskId, $step['step_id'], 'failed');
                
                $this->stream->send('step_error', [
                    'task_id' => $taskId,
                    'step_id' => $step['step_id'],
                    'step_number' => $stepNumber,
                    'error' => $stepResult['error']
                ]);
            }
            
            // Progression
            $progress = round(($completedSteps / $totalSteps) * 100, 2);
            $this->stream->send('progress', [
                'task_id' => $taskId,
                'progress' => $progress,
                'completed' => $completedSteps,
                'total' => $totalSteps
            ]);
        }
        
        // Étape 3: Synthèse finale
        $this->stream->send('synthesis_start', ['task_id' => $taskId]);
        
        $synthesisResult = $this->generateSynthesis($taskId, $plan, $results);
        
        // Mettre à jour le thread
        $this->updateThreadStatus($taskId, 'completed');
        $this->memory->store(
            $taskId,
            $threadId,
            "Synthèse: " . substr($synthesisResult['content'], 0, 500),
            'synthesis',
            ['full_synthesis' => $synthesisResult]
        );
        
        $this->stream->send('task_complete', [
            'task_id' => $taskId,
            'synthesis' => substr($synthesisResult['content'], 0, 500),
            'total_steps' => $totalSteps,
            'completed_steps' => $completedSteps,
            'files_created' => $this->sandbox->listFiles($threadId)
        ]);
        
        return [
            'success' => true,
            'task_id' => $taskId,
            'plan' => $plan,
            'step_results' => $results,
            'synthesis' => $synthesisResult,
            'files' => $this->sandbox->listFiles($threadId),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Exécute une tâche simple (sans planification)
     */
    private function executeSimpleTask(
        string $taskId,
        string $threadId,
        string $description
    ): array {
        $this->stream->send('simple_task_start', [
            'task_id' => $taskId,
            'description' => $description
        ]);
        
        // Sélectionner le skill approprié
        $skillSelection = selectSkill('general', $description);
        
        $result = $this->subAgent->executeSimpleTask(
            $taskId,
            $description,
            $skillSelection['skill_name']
        );
        
        if ($result['success']) {
            $this->updateThreadStatus($taskId, 'completed');
            
            $this->memory->store(
                $taskId,
                $threadId,
                "Résultat: " . substr($result['content'], 0, 500),
                'task_result',
                ['full_result' => $result]
            );
            
            $this->stream->send('task_complete', [
                'task_id' => $taskId,
                'content_preview' => substr($result['content'], 0, 500),
                'files_created' => $result['files_created'] ?? [],
                'model_used' => $result['model_used']
            ]);
            
            return [
                'success' => true,
                'task_id' => $taskId,
                'result' => $result,
                'files' => $this->sandbox->listFiles($threadId),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            $this->updateThreadStatus($taskId, 'failed');
            
            return [
                'success' => false,
                'task_id' => $taskId,
                'error' => $result['error'],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Génère une synthèse finale des résultats
     */
    private function generateSynthesis(
        string $taskId,
        array $plan,
        array $results
    ): array {
        set_time_limit(300);
        
        // Construire le contexte de synthèse
        $contextParts = [];
        $contextParts[] = "# TÂCHE ORIGINALE\n";
        $contextParts[] = $plan['summary'] ?? 'Tâche complexe';
        $contextParts[] = "\n\n# RÉSULTATS PAR ÉTAPE\n";
        
        foreach ($results as $stepId => $result) {
            if ($result['success']) {
                $contextParts[] = "## {$stepId}\n";
                $contextParts[] = substr($result['content'], 0, 2000);
                $contextParts[] = "\n\n";
            }
        }
        
        $context = implode('', $contextParts);
        
        // Appeler Mistral pour la synthèse
        $ch = curl_init(MISTRAL_API_ENDPOINT);
        
        $prompt = <<<PROMPT
# SYNTHÈSE FINALE

Tu dois compiler tous les résultats d'une tâche complexe en un rapport final cohérent.

{$context}

---

# CONSIGNES DE SYNTHÈSE

1. **Résume** l'objectif initial et la stratégie employée
2. **Synthétise** les résultats de chaque étape
3. **Identifie** les points clés et conclusions importantes
4. **Liste** les fichiers générés et leur utilité
5. **Propose** des pistes d'amélioration ou extensions possibles

Formatte ta réponse de manière professionnelle et structurée.
Utilise des titres, listes et blocs de code si nécessaire.

PROMPT;
        
        $payload = json_encode([
            'model' => MISTRAL_MODEL_REPORT,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => 6000
        ]);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MISTRAL_API_KEY
            ],
            CURLOPT_TIMEOUT => MISTRAL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => MISTRAL_CONNECT_TIMEOUT
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        sleep(1); // Rate limit
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? 'Synthèse non disponible';
            
            return [
                'success' => true,
                'content' => $content,
                'tokens' => strlen($content) / 4
            ];
        }
        
        // Fallback: utiliser les résultats bruts
        return [
            'success' => true,
            'content' => $context,
            'tokens' => 0,
            'is_fallback' => true
        ];
    }
    
    /**
     * Crée un thread dans la base de données
     */
    private function createThread(
        string $taskId,
        string $threadId,
        string $title,
        string $description
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO threads (id, title, description, status, created_at)
            VALUES (:id, :title, :description, :status, :created_at)
        ");
        
        $stmt->execute([
            ':id' => $threadId,
            ':title' => $title,
            ':description' => $description,
            ':status' => 'running',
            ':created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Met à jour le statut d'un thread
     */
    private function updateThreadStatus(string $threadId, string $status): void {
        $stmt = $this->db->prepare("
            UPDATE threads 
            SET status = :status, 
                updated_at = datetime('now')
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':status' => $status,
            ':id' => $threadId
        ]);
    }
    
    /**
     * Obtient l'état complet d'une tâche
     */
    public function getTaskState(string $taskId): array {
        // Récupérer le thread
        $stmt = $this->db->prepare("SELECT * FROM threads WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$thread) {
            return ['error' => 'Tâche non trouvée'];
        }
        
        // Récupérer le plan
        $plan = $this->planner->getPlan($taskId);
        
        // Récupérer les étapes
        $stmt = $this->db->prepare("
            SELECT * FROM plan_steps WHERE task_id = :task_id ORDER BY priority
        ");
        $stmt->execute([':task_id' => $taskId]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer les mémoires
        $memories = $this->memory->searchByTask($taskId, 20);
        
        // Récupérer les fichiers
        $files = $this->sandbox->listFiles($taskId);
        
        return [
            'thread' => $thread,
            'plan' => $plan,
            'steps' => $steps,
            'memories' => $memories,
            'files' => $files,
            'sandbox_path' => $this->sandbox->getThreadPath($taskId)
        ];
    }
    
    /**
     * Annule une tâche en cours
     */
    public function cancelTask(string $taskId): bool {
        $this->updateThreadStatus($taskId, 'cancelled');
        
        // Annuler toutes les étapes pending
        $stmt = $this->db->prepare("
            UPDATE plan_steps 
            SET status = 'cancelled' 
            WHERE task_id = :task_id AND status IN ('pending', 'running')
        ");
        $stmt->execute([':task_id' => $taskId]);
        
        $this->stream->send('task_cancelled', ['task_id' => $taskId]);
        
        return true;
    }
    
    /**
     * Reprend une tâche annulée ou échouée
     */
    public function resumeTask(string $taskId): array {
        $plan = $this->planner->getPlan($taskId);
        
        if (!$plan) {
            return ['error' => 'Aucun plan trouvé pour cette tâche'];
        }
        
        // Trouver les étapes incomplètes
        $readySteps = $this->planner->getReadySteps($taskId);
        
        if (empty($readySteps)) {
            return ['error' => 'Aucune étape à reprendre'];
        }
        
        $this->updateThreadStatus($taskId, 'running');
        
        $results = [];
        foreach ($readySteps as $step) {
            $stepResult = $this->subAgent->executeStep($taskId, $step, []);
            $results[$step['step_id']] = $stepResult;
            
            if ($stepResult['success']) {
                $this->planner->updateStepStatus(
                    $taskId,
                    $step['step_id'],
                    'completed',
                    substr($stepResult['content'], 0, 2000)
                );
            }
        }
        
        // Vérifier si toutes les étapes sont complètes
        if ($this->planner->isPlanComplete($taskId)) {
            $this->updateThreadStatus($taskId, 'completed');
        }
        
        return [
            'success' => true,
            'resumed_steps' => count($readySteps),
            'results' => $results
        ];
    }
}

// Fonctions utilitaires globales

/**
 * Crée une instance unique de LeadAgent
 */
function getLeadAgent(): LeadAgent {
    static $instance = null;
    if ($instance === null) {
        $instance = new LeadAgent();
    }
    return $instance;
}

/**
 * Exécute une nouvelle tâche
 */
function runTask(string $description, array $options = []): array {
    $agent = getLeadAgent();
    return $agent->executeTask($description, $options);
}

/**
 * Obtient l'état d'une tâche
 */
function getTaskState(string $taskId): array {
    $agent = getLeadAgent();
    return $agent->getTaskState($taskId);
}

// Initialisation automatique si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'info';
    
    if ($action === 'test') {
        $agent = new LeadAgent();
        $result = $agent->executeTask(
            'Explique les concepts de base de la programmation orientée objet',
            ['use_planning' => false, 'title' => 'Test OOP']
        );
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'service' => 'LeadAgent',
            'status' => 'operational',
            'modules' => [
                'planner' => 'active',
                'subagent' => 'active',
                'memory' => 'active',
                'sandbox' => 'active',
                'stream' => 'active'
            ],
            'available_actions' => ['test']
        ]);
    }
}
