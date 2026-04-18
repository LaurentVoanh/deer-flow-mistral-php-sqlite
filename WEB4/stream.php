<?php
/**
 * DeerFlow PHP - Server-Sent Events Helpers
 * Gestion des flux SSE pour le temps réel
 * Optimisé pour Hostinger avec LiteSpeed
 *
 * @package DeerFlow
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class SSEHelper {

    private string $threadId;
    private ?string $taskId;
    private int $eventCounter = 0;

    /**
     * Constructeur
     * @param string $threadId ID du thread
     * @param string|null $taskId ID de la tâche (optionnel)
     */
    public function __construct(string $threadId, ?string $taskId = null) {
        $this->threadId = $threadId;
        $this->taskId = $taskId;
    }

    /**
     * Initialise les headers SSE pour LiteSpeed/Apache
     */
    public static function setHeaders(): void {
        // Désactiver le buffer PHP
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Headers SSE
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx
        header('Access-Control-Allow-Origin: *');
        
        // Headers spécifiques LiteSpeed
        if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false) {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }

        // Désactiver le buffer Apache si présent
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        // Flush initial
        ob_flush();
        flush();
    }

    /**
     * Envoie un événement SSE
     * @param string $type Type d'événement
     * @param mixed $data Données à envoyer
     * @param string|null $id ID de l'événement (optionnel)
     * @return bool Succès
     */
    public function send(string $type, mixed $data, ?string $id = null): bool {
        $this->eventCounter++;

        // Générer un ID si non fourni
        if ($id === null) {
            $id = $this->eventCounter;
        }

        // Encoder les données en JSON
        $encodedData = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);

        // Format SSE standard
        $message = "id: {$id}\n";
        $message .= "event: {$type}\n";
        $message .= "data: {$encodedData}\n\n";

        // Envoyer
        echo $message;

        // Forcer l'envoi immédiat
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        // Vérifier si le client est toujours connecté
        return !connection_aborted();
    }

    /**
     * Envoie un événement de progression
     * @param int $current Valeur actuelle
     * @param int $total Valeur totale
     * @param string $message Message descriptif
     * @param array $extra Données supplémentaires
     * @return bool Succès
     */
    public function sendProgress(int $current, int $total, string $message = '', array $extra = []): bool {
        $percentage = $total > 0 ? round(($current / $total) * 100, 2) : 0;

        $data = array_merge([
            'progress' => $percentage,
            'current' => $current,
            'total' => $total,
            'message' => $message,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ], $extra);

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send(SSE_EVENT_PROGRESS, $data);
    }

    /**
     * Envoie un message texte simple
     * @param string $message Message à envoyer
     * @param string $level Niveau (info, warning, error, success)
     * @return bool Succès
     */
    public function sendMessage(string $message, string $level = 'info'): bool {
        $data = [
            'message' => $message,
            'level' => $level,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ];

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send(SSE_EVENT_MESSAGE, $data);
    }

    /**
     * Envoie une erreur
     * @param string $error Message d'erreur
     * @param string|null $code Code d'erreur (optionnel)
     * @param array $context Contexte supplémentaire
     * @return bool Succès
     */
    public function sendError(string $error, ?string $code = null, array $context = []): bool {
        $data = array_merge([
            'error' => $error,
            'code' => $code ?? 'UNKNOWN_ERROR',
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ], $context);

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send(SSE_EVENT_ERROR, $data);
    }

    /**
     * Envoie un événement de complétion
     * @param mixed $result Résultat final
     * @param int $duration Durée d'exécution en ms
     * @return bool Succès
     */
    public function sendComplete(mixed $result, int $duration = 0): bool {
        $data = [
            'status' => 'completed',
            'result' => $result,
            'duration_ms' => $duration,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ];

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send(SSE_EVENT_COMPLETE, $data);
    }

    /**
     * Envoie un événement de sous-agent
     * @param string $agentId ID du sous-agent
     * @param string $action Action en cours
     * @param mixed $data Données associées
     * @return bool Succès
     */
    public function sendSubAgent(string $agentId, string $action, mixed $data = null): bool {
        $payload = [
            'agent_id' => $agentId,
            'action' => $action,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ];

        if ($this->taskId) {
            $payload['parent_task_id'] = $this->taskId;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->send(SSE_EVENT_SUBAGENT, $payload);
    }

    /**
     * Envoie un événement de mémoire
     * @param string $operation Type d'opération (add, update, delete, extract)
     * @param string $memoryType Type de mémoire (short_term, long_term, knowledge)
     * @param array $metadata Métadonnées
     * @return bool Succès
     */
    public function sendMemory(string $operation, string $memoryType, array $metadata = []): bool {
        $data = array_merge([
            'operation' => $operation,
            'memory_type' => $memoryType,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ], $metadata);

        return $this->send(SSE_EVENT_MEMORY, $data);
    }

    /**
     * Envoie un heartbeat pour maintenir la connexion
     * @return bool Succès
     */
    public function sendHeartbeat(): bool {
        return $this->send('heartbeat', [
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ]);
    }

    /**
     * Envoie le début d'une tâche
     * @param string $taskTitle Titre de la tâche
     * @param array $metadata Métadonnées
     * @return bool Succès
     */
    public function sendTaskStart(string $taskTitle, array $metadata = []): bool {
        $data = array_merge([
            'task_title' => $taskTitle,
            'status' => 'started',
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ], $metadata);

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send('task_start', $data);
    }

    /**
     * Envoie la fin d'une tâche
     * @param mixed $result Résultat
     * @param int $duration Durée en ms
     * @return bool Succès
     */
    public function sendTaskEnd(mixed $result, int $duration = 0): bool {
        $data = [
            'status' => 'finished',
            'result' => $result,
            'duration_ms' => $duration,
            'timestamp' => time(),
            'thread_id' => $this->threadId
        ];

        if ($this->taskId) {
            $data['task_id'] = $this->taskId;
        }

        return $this->send('task_end', $data);
    }

    /**
     * Envoie un token stream (pour streaming de réponse IA)
     * @param string $token Token à envoyer
     * @param bool $isLast Dernier token
     * @return bool Succès
     */
    public function sendToken(string $token, bool $isLast = false): bool {
        $data = [
            'token' => $token,
            'is_last' => $isLast,
            'timestamp' => time()
        ];

        return $this->send('token', $data);
    }

    /**
     * Garde la connexion active avec des heartbeats périodiques
     * @param int $maxDuration Durée maximale en secondes
     */
    public function keepAlive(int $maxDuration = 300): void {
        $startTime = time();
        
        while ((time() - $startTime) < $maxDuration) {
            if (!$this->sendHeartbeat()) {
                break; // Client déconnecté
            }
            
            usleep(SSE_HEARTBEAT_INTERVAL);
        }
    }
}

/**
 * Classe utilitaire pour gérer les connexions SSE côté serveur
 */
class SSEConnection {
    
    private string $threadId;
    private ?SSEHelper $helper = null;
    private bool $connected = false;

    public function __construct(string $threadId) {
        $this->threadId = $threadId;
    }

    /**
     * Initialise la connexion SSE
     */
    public function connect(): void {
        SSEHelper::setHeaders();
        $this->helper = new SSEHelper($this->threadId);
        $this->connected = true;
    }

    /**
     * Envoie un événement
     * @param string $type Type
     * @param mixed $data Données
     * @return bool Succès
     */
    public function send(string $type, mixed $data): bool {
        if (!$this->connected || !$this->helper) {
            return false;
        }
        return $this->helper->send($type, $data);
    }

    /**
     * Vérifie si la connexion est active
     * @return bool
     */
    public function isActive(): bool {
        return $this->connected && !connection_aborted();
    }

    /**
     * Ferme la connexion
     */
    public function close(): void {
        $this->connected = false;
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
}
