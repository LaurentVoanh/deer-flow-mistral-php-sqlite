<?php
/**
 * subagent.php - Exécuteur isolé de sous-tâches
 * Système DeerFlow PHP - Hostinger Compatible
 * 
 * Ce fichier exécute les sous-tâches définies par le planner
 * en utilisant le skill et le modèle appropriés.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/skills.php';
require_once __DIR__ . '/sandbox.php';
require_once __DIR__ . '/memory.php';

class SubAgent {
    
    private PDO $db;
    private SkillsLoader $skillsLoader;
    private Sandbox $sandbox;
    private MemoryManager $memory;
    private string $apiKey;
    private string $apiEndpoint;
    
    public function __construct() {
        $this->db = getDbConnection();
        $this->skillsLoader = getSkillsLoader();
        $this->sandbox = new Sandbox();
        $this->memory = new MemoryManager();
        $this->apiKey = MISTRAL_API_KEY;
        $this->apiEndpoint = MISTRAL_API_ENDPOINT;
    }
    
    /**
     * Exécute une sous-tâche spécifique
     */
    public function executeStep(
        string $taskId,
        array $step,
        array $previousResults = []
    ): array {
        set_time_limit(600);
        
        try {
            $stepId = $step['step_id'];
            $skillName = $step['skill_required'];
            $model = $step['model_recommended'];
            $description = $step['description'];
            
            // Initialiser le sandbox pour ce thread/tâche
            $this->sandbox->initializeThread($taskId);
            
            // Récupérer le skill
            $skillContent = getSkillContent($skillName);
            
            // Construire le contexte d'exécution
            $executionContext = $this->buildExecutionContext(
                $taskId,
                $step,
                $previousResults
            );
            
            // Construire le prompt complet
            $prompt = $this->buildExecutionPrompt(
                $description,
                $skillContent,
                $executionContext
            );
            
            // Appeler l'API Mistral
            $response = $this->callMistralAPI($prompt, $model);
            
            // Extraire et traiter la réponse
            $result = $this->processResponse($response, $taskId, $stepId);
            
            // Sauvegarder en mémoire
            $this->memory->store(
                $taskId,
                $taskId,
                "Résultat étape {$stepId}: " . substr($result['content'], 0, 500),
                'step_result',
                ['step_id' => $stepId, 'full_result' => $result]
            );
            
            return [
                'success' => true,
                'step_id' => $stepId,
                'content' => $result['content'],
                'files_created' => $result['files'] ?? [],
                'tokens_used' => $result['tokens'] ?? 0,
                'model_used' => $model,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'step_id' => $step['step_id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Construit le contexte d'exécution
     */
    private function buildExecutionContext(
        string $taskId,
        array $step,
        array $previousResults
    ): array {
        // Récupérer les informations de la tâche principale
        $taskInfo = $this->getTaskInfo($taskId);
        
        // Récupérer les résultats des étapes précédentes
        $prevStepsSummary = [];
        foreach ($previousResults as $prevStepId => $result) {
            if (isset($result['content'])) {
                $prevStepsSummary[] = "### Étape {$prevStepId}\n" . substr($result['content'], 0, 1000);
            }
        }
        
        // Récupérer les fichiers du sandbox
        $sandboxFiles = $this->sandbox->listFiles($taskId);
        
        return [
            'task_id' => $taskId,
            'task_title' => $taskInfo['title'] ?? 'Tâche sans titre',
            'task_description' => $taskInfo['description'] ?? '',
            'current_step' => [
                'id' => $step['step_id'],
                'title' => $step['title'],
                'skill' => $step['skill_required'],
                'model' => $step['model_recommended']
            ],
            'previous_steps' => $prevStepsSummary,
            'available_files' => $sandboxFiles,
            'thread_path' => $this->sandbox->getThreadPath($taskId)
        ];
    }
    
    /**
     * Construit le prompt d'exécution
     */
    private function buildExecutionPrompt(
        string $description,
        string $skillContent,
        array $context
    ): string {
        $prompt = <<<PROMPT
# COMPÉTENCE ACTIVE: {$context['current_step']['skill']}

{$skillContent}

---

# CONTEXTE D'EXÉCUTION

## Informations de la tâche
- **ID**: {$context['task_id']}
- **Titre**: {$context['task_title']}
- **Description**: {$context['task_description']}

## Étape actuelle
- **ID**: {$context['current_step']['id']}
- **Titre**: {$context['current_step']['title']}
- **Modèle IA**: {$context['current_step']['model']}

## Résultats des étapes précédentes
PROMPT;

        if (!empty($context['previous_steps'])) {
            foreach ($context['previous_steps'] as $summary) {
                $prompt .= "\n{$summary}\n";
            }
        } else {
            $prompt .= "\nAucune étape précédente.\n";
        }

        $prompt .= "\n## Fichiers disponibles dans le sandbox\n";
        if (!empty($context['available_files'])) {
            foreach ($context['available_files'] as $file) {
                $prompt .= "- {$file}\n";
            }
        } else {
            $prompt .= "- Aucun fichier\n";
        }

        $prompt .= <<<PROMPT

---

# INSTRUCTION À EXÉCUTER

{$description}

---

# DIRECTIVES DE RÉPONSE

1. **Analyse** la demande et le contexte
2. **Exécute** la tâche avec précision en utilisant ta compétence
3. **Structure** ta réponse clairement
4. **Code** : Si tu génères du code, fournis-le complet et fonctionnel
5. **Fichiers** : Si tu crées des fichiers, indique leur chemin relatif au sandbox
6. **Qualité** : Vérifie que ton travail est complet avant de conclure

## FORMAT DE SORTIE ATTENDU

Réponds de manière structurée avec :
- Une section d'analyse/compréhension
- Une section d'exécution/développement
- Une section de conclusion/vérification

Si tu génères du code ou des fichiers, utilise des blocs de code markdown avec le langage spécifié.

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
                    'content' => 'Tu es un agent expert exécutant des tâches spécialisées. Tes réponses sont précises, complètes et bien structurées.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.5,
            'max_tokens' => 8000,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.2
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
        
        // Respect du rate limit (1 req/sec)
        sleep(1);
        
        return $data['choices'][0]['message']['content'];
    }
    
    /**
     * Traite la réponse de l'IA
     */
    private function processResponse(
        string $response,
        string $taskId,
        string $stepId
    ): array {
        $result = [
            'content' => $response,
            'files' => [],
            'tokens' => strlen($response) / 4 // Estimation grossière
        ];
        
        // Extraire les blocs de code pour créer des fichiers si nécessaire
        preg_match_all('/```(\w+)?\n(.*?)```/s', $response, $codeBlocks, PREG_SET_ORDER);
        
        foreach ($codeBlocks as $index => $block) {
            $language = $block[1] ?? '';
            $code = $block[2];
            
            // Déterminer l'extension selon le langage
            $extensionMap = [
                'php' => '.php',
                'javascript' => '.js',
                'js' => '.js',
                'python' => '.py',
                'html' => '.html',
                'css' => '.css',
                'json' => '.json',
                'sql' => '.sql',
                'markdown' => '.md',
                'md' => '.md',
                'text' => '.txt',
                'txt' => '.txt'
            ];
            
            $extension = $extensionMap[strtolower($language)] ?? '.txt';
            $filename = "generated_{$stepId}_{$index}{$extension}";
            
            // Sauvegarder dans le sandbox
            $filePath = $this->sandbox->writeFile($taskId, $filename, $code);
            if ($filePath) {
                $result['files'][] = $filename;
            }
        }
        
        return $result;
    }
    
    /**
     * Obtient les informations d'une tâche
     */
    private function getTaskInfo(string $taskId): array {
        $prefix = DB_PREFIX;
        $stmt = $this->db->prepare("SELECT * FROM {$prefix}threads WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'title' => $result['title'],
                'description' => $result['description']
            ];
        }
        
        // Chercher dans les mémoires
        $memoryData = $this->memory->searchByTask($taskId, 1);
        if (!empty($memoryData)) {
            return [
                'title' => 'Tâche ' . $taskId,
                'description' => $memoryData[0]['content'] ?? ''
            ];
        }
        
        return [
            'title' => 'Tâche ' . $taskId,
            'description' => ''
        ];
    }
    
    /**
     * Exécute une tâche simple (sans plan)
     */
    public function executeSimpleTask(
        string $taskId,
        string $taskDescription,
        string $skillName = 'analysis'
    ): array {
        set_time_limit(600);
        
        try {
            $this->sandbox->initializeThread($taskId);
            
            $skillContent = getSkillContent($skillName);
            $model = $this->skillsLoader->getModelForSkill($skillName);
            
            $prompt = <<<PROMPT
# COMPÉTENCE: {$skillName}

{$skillContent}

---

# TÂCHE À EXÉCUTER

{$taskDescription}

---

# DIRECTIVES

1. Analyse la demande
2. Exécute la tâche avec expertise
3. Fournis une réponse complète et structurée
4. Si du code est généré, il doit être fonctionnel et testé

Réponds de manière détaillée et professionnelle.

PROMPT;
            
            $response = $this->callMistralAPI($prompt, $model);
            
            $result = $this->processResponse($response, $taskId, 'simple');
            
            $this->memory->store(
                $taskId,
                $taskId,
                "Tâche simple: " . substr($result['content'], 0, 500),
                'task_result',
                ['full_result' => $result]
            );
            
            return [
                'success' => true,
                'content' => $result['content'],
                'files_created' => $result['files'],
                'model_used' => $model,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Annule l'exécution d'une étape
     */
    public function cancelStep(string $taskId, string $stepId): bool {
        // Mettre à jour le statut dans la base de données
        $stmt = $this->db->prepare("
            UPDATE plan_steps 
            SET status = 'cancelled'
            WHERE task_id = :task_id AND step_id = :step_id AND status = 'pending'
        ");
        
        return $stmt->execute([
            ':task_id' => $taskId,
            ':step_id' => $stepId
        ]);
    }
}

// Fonctions utilitaires globales

/**
 * Crée une instance unique de SubAgent
 */
function getSubAgent(): SubAgent {
    static $instance = null;
    if ($instance === null) {
        $instance = new SubAgent();
    }
    return $instance;
}

/**
 * Exécute une étape spécifique
 */
function executeStep(string $taskId, array $step, array $previousResults = []): array {
    $agent = getSubAgent();
    return $agent->executeStep($taskId, $step, $previousResults);
}

/**
 * Exécute une tâche simple
 */
function executeSimpleTask(string $taskId, string $description, string $skill = 'analysis'): array {
    $agent = getSubAgent();
    return $agent->executeSimpleTask($taskId, $description, $skill);
}

// Initialisation automatique si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? 'info';
    
    if ($action === 'test') {
        $agent = new SubAgent();
        $result = $agent->executeSimpleTask(
            'test_' . time(),
            'Explique comment fonctionne une API REST',
            'report'
        );
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'service' => 'SubAgent',
            'status' => 'operational',
            'available_actions' => ['test'],
            'available_skills' => getSkillsLoader()->getAvailableSkills()
        ]);
    }
}
