<?php
/**
 * planner.php - Décomposeur de tâches en plan d'exécution
 * Système DeerFlow PHP - Hostinger Compatible
 * 
 * Ce fichier utilise l'API Mistral pour décomposer une tâche complexe
 * en sous-tâches exécutables par les sub-agents.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/skills.php';

class TaskPlanner {
    
    private PDO $db;
    private SkillsLoader $skillsLoader;
    private string $apiKey;
    private string $apiEndpoint;
    
    public function __construct() {
        $this->db = getDbConnection();
        $this->skillsLoader = getSkillsLoader();
        $this->apiKey = MISTRAL_API_KEY;
        $this->apiEndpoint = MISTRAL_API_ENDPOINT;
    }
    
    /**
     * Décompose une tâche en sous-tâches structurées
     */
    public function decomposeTask(
        string $taskId,
        string $taskDescription,
        array $context = []
    ): array {
        set_time_limit(600);
        
        try {
            // Récupérer le contexte de la tâche depuis la mémoire
            $memoryContext = $this->getTaskMemoryContext($taskId);
            
            // Construire le prompt de planification
            $planningPrompt = $this->buildPlanningPrompt($taskDescription, $memoryContext, $context);
            
            // Appeler l'API Mistral avec le modèle adapté
            $model = MISTRAL_MODEL_PLANNER;
            $response = $this->callMistralAPI($planningPrompt, $model);
            
            // Parser la réponse JSON
            $plan = $this->parsePlanResponse($response, $taskId);
            
            // Sauvegarder le plan en base de données
            $this->savePlan($taskId, $plan);
            
            return [
                'success' => true,
                'plan' => $plan,
                'model_used' => $model,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_plan' => $this->createFallbackPlan($taskDescription, $taskId)
            ];
        }
    }
    
    /**
     * Construit le prompt pour la planification
     */
    private function buildPlanningPrompt(
        string $taskDescription,
        array $memoryContext,
        array $additionalContext
    ): string {
        $prompt = <<<PROMPT
# RÔLE : PLANIFICATEUR DE TÂCHES AUTONOME

Tu es un planificateur expert chargé de décomposer des tâches complexes en sous-tâches exécutables.

## CONTEXTE MÉMOIRE (informations pertinentes)
PROMPT;

        if (!empty($memoryContext)) {
            foreach ($memoryContext as $info) {
                $prompt .= "- {$info}\n";
            }
        } else {
            $prompt .= "Aucun contexte mémoire disponible.\n";
        }

        $prompt .= <<<PROMPT

## TÂCHE PRINCIPALE À DÉCOMPOSER
{$taskDescription}

## CONSIGNES DE PLANIFICATION

Tu dois générer un plan JSON STRICTEMENT structuré comme suit :

```json
{
    "summary": "Résumé court de la stratégie globale",
    "estimated_steps": 3,
    "steps": [
        {
            "id": "step_1",
            "title": "Titre clair de l'étape",
            "description": "Description détaillée de ce qui doit être fait",
            "skill_required": "research|codegen|analysis|report",
            "model_recommended": "nom_du_modèle_mistral",
            "dependencies": [],
            "estimated_tokens": 5000,
            "priority": 1,
            "can_parallel": false
        }
    ],
    "final_deliverable": "Description du livrable final attendu",
    "success_criteria": ["Critère 1", "Critère 2"]
}
```

## RÈGLES IMPORTANTES

1. **Ordre logique** : Les étapes doivent être dans l'ordre d'exécution
2. **Dépendances** : Liste les IDs des étapes dont celle-ci dépend
3. **Skill requis** : Choisis parmi : research, codegen, analysis, report
4. **Modèle recommandé** : Utilise les modèles Mistral appropriés
   - codegen → codestral-2508
   - research → mistral-small-2603 ou mistral-medium-2508
   - analysis → mistral-large-2512
   - report → mistral-medium-2508
5. **Parallélisation** : Indique si l'étape peut être exécutée en parallèle
6. **Granularité** : Chaque étape doit être atomique et exécutable seule

## FORMAT DE RÉPONSE

Réponds UNIQUEMENT avec le JSON valide, sans texte avant ni après.
Commence directement par { et termine par }.

PROMPT;

        return $prompt;
    }
    
    /**
     * Appelle l'API Mistral
     */
    private function callMistralAPI(string $prompt, string $model): string {
        $ch = curl_init($this->apiEndpoint);
        
        $payload = json_encode([
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un planificateur de tâches expert. Tu réponds uniquement en JSON valide.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 4000,
            'top_p' => 0.9,
            'frequency_penalty' => 0.1,
            'presence_penalty' => 0.1
        ]);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => MISTRAL_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => MISTRAL_CONNECT_TIMEOUT
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            throw new Exception("Erreur API Mistral: HTTP {$httpCode} - {$error}");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Réponse API invalide: " . json_encode($data));
        }
        
        // Respect du rate limit
        sleep(1);
        
        return $data['choices'][0]['message']['content'];
    }
    
    /**
     * Parse la réponse JSON du plan
     */
    private function parsePlanResponse(string $response, string $taskId): array {
        // Nettoyer la réponse (enlever les balises markdown si présentes)
        $cleanedResponse = preg_replace('/```json\s*/', '', $response);
        $cleanedResponse = preg_replace('/\s*```/', '', $cleanedResponse);
        $cleanedResponse = trim($cleanedResponse);
        
        $plan = json_decode($cleanedResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Tentative de récupération avec extraction du JSON
            preg_match('/\{.*\}/s', $cleanedResponse, $matches);
            if (isset($matches[0])) {
                $plan = json_decode($matches[0], true);
            }
        }
        
        if (!is_array($plan) || !isset($plan['steps'])) {
            throw new Exception("Impossible de parser le plan: " . json_last_error_msg());
        }
        
        // Normaliser le plan
        $normalizedPlan = [
            'task_id' => $taskId,
            'summary' => $plan['summary'] ?? 'Plan généré automatiquement',
            'estimated_steps' => count($plan['steps']),
            'steps' => [],
            'final_deliverable' => $plan['final_deliverable'] ?? '',
            'success_criteria' => $plan['success_criteria'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'planned'
        ];
        
        // Normaliser chaque étape
        foreach ($plan['steps'] as $index => $step) {
            $normalizedPlan['steps'][] = [
                'step_id' => $step['id'] ?? "step_" . ($index + 1),
                'title' => $step['title'] ?? "Étape " . ($index + 1),
                'description' => $step['description'] ?? '',
                'skill_required' => $step['skill_required'] ?? 'analysis',
                'model_recommended' => $step['model_recommended'] ?? MISTRAL_MODEL_DEFAULT,
                'dependencies' => $step['dependencies'] ?? [],
                'estimated_tokens' => (int)($step['estimated_tokens'] ?? 5000),
                'priority' => (int)($step['priority'] ?? ($index + 1)),
                'can_parallel' => (bool)($step['can_parallel'] ?? false),
                'status' => 'pending',
                'result' => null,
                'started_at' => null,
                'completed_at' => null
            ];
        }
        
        // Trier par priorité
        usort($normalizedPlan['steps'], function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $normalizedPlan;
    }
    
    /**
     * Crée un plan de secours simple
     */
    private function createFallbackPlan(string $taskDescription, string $taskId): array {
        return [
            'task_id' => $taskId,
            'summary' => 'Plan de secours généré automatiquement',
            'estimated_steps' => 3,
            'steps' => [
                [
                    'step_id' => 'step_1',
                    'title' => 'Analyse de la demande',
                    'description' => "Analyser et comprendre la tâche: {$taskDescription}",
                    'skill_required' => 'analysis',
                    'model_recommended' => MISTRAL_MODEL_ANALYSIS,
                    'dependencies' => [],
                    'estimated_tokens' => 3000,
                    'priority' => 1,
                    'can_parallel' => false,
                    'status' => 'pending',
                    'result' => null,
                    'started_at' => null,
                    'completed_at' => null
                ],
                [
                    'step_id' => 'step_2',
                    'title' => 'Exécution de la tâche',
                    'description' => "Exécuter la tâche principale: {$taskDescription}",
                    'skill_required' => 'codegen',
                    'model_recommended' => MISTRAL_MODEL_CODEGEN,
                    'dependencies' => ['step_1'],
                    'estimated_tokens' => 8000,
                    'priority' => 2,
                    'can_parallel' => false,
                    'status' => 'pending',
                    'result' => null,
                    'started_at' => null,
                    'completed_at' => null
                ],
                [
                    'step_id' => 'step_3',
                    'title' => 'Synthèse et rapport',
                    'description' => 'Compiler les résultats et générer un rapport final',
                    'skill_required' => 'report',
                    'model_recommended' => MISTRAL_MODEL_REPORT,
                    'dependencies' => ['step_2'],
                    'estimated_tokens' => 4000,
                    'priority' => 3,
                    'can_parallel' => false,
                    'status' => 'pending',
                    'result' => null,
                    'started_at' => null,
                    'completed_at' => null
                ]
            ],
            'final_deliverable' => 'Résultat de la tâche',
            'success_criteria' => ['Tâche complétée avec succès'],
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'planned',
            'is_fallback' => true
        ];
    }
    
    /**
     * Sauvegarde le plan en base de données
     */
    private function savePlan(string $taskId, array $plan): void {
        // Sauvegarder le plan principal
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO plans (task_id, plan_data, status, created_at)
            VALUES (:task_id, :plan_data, :status, :created_at)
        ");
        
        $stmt->execute([
            ':task_id' => $taskId,
            ':plan_data' => json_encode($plan, JSON_UNESCAPED_UNICODE),
            ':status' => $plan['status'],
            ':created_at' => $plan['created_at']
        ]);
        
        // Sauvegarder chaque étape
        $stmtStep = $this->db->prepare("
            INSERT OR REPLACE INTO plan_steps 
            (task_id, step_id, title, description, skill_required, model_recommended, 
             dependencies, estimated_tokens, priority, can_parallel, status)
            VALUES (:task_id, :step_id, :title, :description, :skill, :model, 
                    :deps, :tokens, :priority, :parallel, :status)
        ");
        
        foreach ($plan['steps'] as $step) {
            $stmtStep->execute([
                ':task_id' => $taskId,
                ':step_id' => $step['step_id'],
                ':title' => $step['title'],
                ':description' => $step['description'],
                ':skill' => $step['skill_required'],
                ':model' => $step['model_recommended'],
                ':deps' => json_encode($step['dependencies']),
                ':tokens' => $step['estimated_tokens'],
                ':priority' => $step['priority'],
                ':parallel' => $step['can_parallel'] ? 1 : 0,
                ':status' => $step['status']
            ]);
        }
    }
    
    /**
     * Récupère le contexte mémoire d'une tâche
     */
    private function getTaskMemoryContext(string $taskId): array {
        $stmt = $this->db->prepare("
            SELECT content FROM memories 
            WHERE task_id = :task_id OR thread_id = :thread_id
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        $stmt->execute([
            ':task_id' => $taskId,
            ':thread_id' => $taskId
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map(function($content) {
            return substr($content, 0, 500); // Limiter la longueur
        }, $results);
    }
    
    /**
     * Récupère un plan existant
     */
    public function getPlan(string $taskId): ?array {
        $stmt = $this->db->prepare("SELECT plan_data FROM plans WHERE task_id = :task_id");
        $stmt->execute([':task_id' => $taskId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return json_decode($result['plan_data'], true);
        }
        
        return null;
    }
    
    /**
     * Met à jour le statut d'une étape
     */
    public function updateStepStatus(
        string $taskId,
        string $stepId,
        string $status,
        ?string $result = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE plan_steps 
            SET status = :status, 
                result = :result,
                completed_at = CASE WHEN :status = 'completed' THEN datetime('now') ELSE NULL END,
                started_at = CASE WHEN :status = 'running' AND started_at IS NULL THEN datetime('now') ELSE started_at END
            WHERE task_id = :task_id AND step_id = :step_id
        ");
        
        $stmt->execute([
            ':status' => $status,
            ':result' => $result,
            ':task_id' => $taskId,
            ':step_id' => $stepId
        ]);
    }
    
    /**
     * Vérifie si toutes les étapes sont complétées
     */
    public function isPlanComplete(string $taskId): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM plan_steps 
            WHERE task_id = :task_id AND status != 'completed'
        ");
        $stmt->execute([':task_id' => $taskId]);
        return (int)$stmt->fetchColumn() === 0;
    }
    
    /**
     * Obtient les étapes prêtes à être exécutées
     */
    public function getReadySteps(string $taskId): array {
        $plan = $this->getPlan($taskId);
        if (!$plan) {
            return [];
        }
        
        $stmt = $this->db->prepare("SELECT * FROM plan_steps WHERE task_id = :task_id AND status = 'pending'");
        $stmt->execute([':task_id' => $taskId]);
        $pendingSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $readySteps = [];
        foreach ($pendingSteps as $step) {
            $dependencies = json_decode($step['dependencies'], true) ?? [];
            
            // Vérifier si toutes les dépendances sont complétées
            $depsCompleted = true;
            foreach ($dependencies as $depId) {
                $depStmt = $this->db->prepare("SELECT status FROM plan_steps WHERE task_id = :task_id AND step_id = :step_id");
                $depStmt->execute([':task_id' => $taskId, ':step_id' => $depId]);
                $depStatus = $depStmt->fetchColumn();
                
                if ($depStatus !== 'completed') {
                    $depsCompleted = false;
                    break;
                }
            }
            
            if ($depsCompleted) {
                $readySteps[] = $step;
            }
        }
        
        return $readySteps;
    }
}

// Fonctions utilitaires globales

/**
 * Crée une instance unique de TaskPlanner
 */
function getTaskPlanner(): TaskPlanner {
    static $instance = null;
    if ($instance === null) {
        $instance = new TaskPlanner();
    }
    return $instance;
}

/**
 * Planifie une nouvelle tâche
 */
function planTask(string $taskId, string $description, array $context = []): array {
    $planner = getTaskPlanner();
    return $planner->decomposeTask($taskId, $description, $context);
}

// Initialisation automatique si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'info';
    
    if ($action === 'test') {
        $planner = new TaskPlanner();
        $result = $planner->decomposeTask(
            'test_' . time(),
            'Créer une API REST complète avec authentification JWT'
        );
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'service' => 'TaskPlanner',
            'status' => 'operational',
            'available_actions' => ['test']
        ]);
    }
}
