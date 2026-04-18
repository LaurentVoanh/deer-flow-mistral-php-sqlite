<?php
/**
 * DeerFlow PHP - Gestion de la Mémoire
 * CRUD mémoire + Extraction automatique de connaissances
 * Optimisé pour SQLite et Hostinger
 *
 * @package DeerFlow
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class MemoryManager {

    private string $threadId;
    private PDO $db;
    private array $cache = [];

    /**
     * Constructeur
     * @param PDO|null $db Instance PDO (optionnelle, sera créée si null)
     */
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Définit le thread ID pour les opérations
     * @param string $threadId
     */
    public function setThreadId(string $threadId): void {
        $this->threadId = $threadId;
    }

    // ========================================================================
    // MÉMOIRE À COURT TERME
    // ========================================================================

    /**
     * Ajoute une entrée en mémoire court terme
     * @param string $threadId ID du thread
     * @param string $content Contenu de la mémoire
     * @param float $importance Niveau d'importance (0-1)
     * @return string ID de la mémoire créée
     */
    public function addShortTerm(string $threadId, string $content, float $importance = 0.5): string {
        $id = generateId('mem_short');

        $sql = "INSERT INTO " . DB_PREFIX . "memory_short
                (id, thread_id, content, importance, created_at)
                VALUES (:id, :thread_id, :content, :importance, datetime('now'))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId,
            ':content' => $content,
            ':importance' => $importance
        ]);

        // Vérifier le seuil de compression
        $this->checkCompressionThreshold($threadId);

        return $id;
    }

    /**
     * Récupère les mémoires court terme récentes
     * @param string $threadId ID du thread
     * @param int $limit Nombre maximum à récupérer
     * @return array Liste des mémoires
     */
    public function getRecentShortTerm(string $threadId, int $limit = MEMORY_RECENT_COUNT): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "memory_short
                WHERE thread_id = :thread_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche dans la mémoire court terme
     * @param string $threadId ID du thread
     * @param string $query Terme de recherche
     * @param int $limit Nombre maximum de résultats
     * @return array Résultats de recherche
     */
    public function searchShortTerm(string $threadId, string $query, int $limit = 20): array {
        $sql = "SELECT *,
                (access_count * 0.3 + importance * 0.7) as relevance
                FROM " . DB_PREFIX . "memory_short
                WHERE thread_id = :thread_id
                AND (content LIKE :query OR content LIKE :query2)
                ORDER BY relevance DESC, created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':query2', '%' . strtolower($query) . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mettre à jour le compteur d'accès
        foreach ($results as $mem) {
            $this->incrementAccess($mem['id'], 'short');
        }

        return $results;
    }

    /**
     * Supprime une mémoire court terme
     * @param string $id ID de la mémoire
     * @param string $threadId ID du thread
     * @return bool Succès de l'opération
     */
    public function deleteShortTerm(string $id, string $threadId): bool {
        $sql = "DELETE FROM " . DB_PREFIX . "memory_short WHERE id = :id AND thread_id = :thread_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Compte les mémoires court terme
     * @param string $threadId ID du thread
     * @return int Nombre de mémoires
     */
    public function countShortTerm(string $threadId): int {
        $sql = "SELECT COUNT(*) as count FROM " . DB_PREFIX . "memory_short WHERE thread_id = :thread_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':thread_id' => $threadId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0);
    }

    // ========================================================================
    // MÉMOIRE À LONG TERME
    // ========================================================================

    /**
     * Ajoute une entrée en mémoire long terme
     * @param string $threadId ID du thread
     * @param string $summary Résumé du contenu
     * @param array $keywords Mots-clés associés
     * @param string $category Catégorie optionnelle
     * @param float $importance Niveau d'importance (0-1)
     * @return string ID de la mémoire créée
     */
    public function addLongTerm(
        string $threadId,
        string $summary,
        array $keywords = [],
        string $category = '',
        float $importance = 0.7
    ): string {
        $id = generateId('mem_long');

        $sql = "INSERT INTO " . DB_PREFIX . "memory_long
                (id, thread_id, summary, keywords, category, importance, created_at)
                VALUES (:id, :thread_id, :summary, :keywords, :category, :importance, datetime('now'))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId,
            ':summary' => $summary,
            ':keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
            ':category' => $category,
            ':importance' => $importance
        ]);

        return $id;
    }

    /**
     * Récupère les mémoires long terme par catégorie
     * @param string $threadId ID du thread
     * @param string|null $category Filtre par catégorie (null = toutes)
     * @param int $limit Nombre maximum à récupérer
     * @return array Liste des mémoires
     */
    public function getLongTerm(string $threadId, ?string $category = null, int $limit = 50): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "memory_long
                WHERE thread_id = :thread_id";

        if ($category !== null) {
            $sql .= " AND category = :category";
        }

        $sql .= " ORDER BY importance DESC, last_accessed DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        if ($category !== null) {
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Décoder les keywords
        foreach ($results as &$mem) {
            $mem['keywords'] = json_decode($mem['keywords'], true) ?? [];
        }

        return $results;
    }

    /**
     * Recherche dans la mémoire long terme
     * @param string $threadId ID du thread
     * @param string $query Terme de recherche
     * @param int $limit Nombre maximum de résultats
     * @return array Résultats de recherche
     */
    public function searchLongTerm(string $threadId, string $query, int $limit = 20): array {
        $sql = "SELECT *,
                (access_count * 0.3 + importance * 0.7) as relevance
                FROM " . DB_PREFIX . "memory_long
                WHERE thread_id = :thread_id
                AND (summary LIKE :query OR keywords LIKE :query2 OR category LIKE :query3)
                ORDER BY relevance DESC, created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':query2', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':query3', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Décoder et mettre à jour
        foreach ($results as &$mem) {
            $mem['keywords'] = json_decode($mem['keywords'], true) ?? [];
            $this->incrementAccess($mem['id'], 'long');
        }

        return $results;
    }

    /**
     * Supprime une mémoire long terme
     * @param string $id ID de la mémoire
     * @param string $threadId ID du thread
     * @return bool Succès de l'opération
     */
    public function deleteLongTerm(string $id, string $threadId): bool {
        $sql = "DELETE FROM " . DB_PREFIX . "memory_long WHERE id = :id AND thread_id = :thread_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId
        ]);

        return $stmt->rowCount() > 0;
    }

    // ========================================================================
    // CONNAISSANCES EXTRACTÉES
    // ========================================================================

    /**
     * Extrait et stocke une connaissance depuis une mémoire
     * @param string $threadId ID du thread
     * @param string $sourceMemoryId ID de la mémoire source
     * @param string $knowledgeType Type de connaissance (fact, concept, procedure, etc.)
     * @param string $content Contenu de la connaissance
     * @param array $tags Tags associés
     * @param float $confidence Niveau de confiance (0-1)
     * @return string ID de la connaissance créée
     */
    public function extractKnowledge(
        string $threadId,
        string $sourceMemoryId,
        string $knowledgeType,
        string $content,
        array $tags = [],
        float $confidence = 0.8
    ): string {
        $id = generateId('knowledge');

        $sql = "INSERT INTO " . DB_PREFIX . "extracted_knowledge
                (id, thread_id, source_memory_id, knowledge_type, content, tags, confidence, created_at)
                VALUES (:id, :thread_id, :source_id, :type, :content, :tags, :confidence, datetime('now'))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId,
            ':source_id' => $sourceMemoryId,
            ':type' => $knowledgeType,
            ':content' => $content,
            ':tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
            ':confidence' => $confidence
        ]);

        return $id;
    }

    /**
     * Récupère les connaissances par type
     * @param string $threadId ID du thread
     * @param string|null $knowledgeType Filtre par type (null = tous)
     * @param int $limit Nombre maximum à récupérer
     * @return array Liste des connaissances
     */
    public function getKnowledge(string $threadId, ?string $knowledgeType = null, int $limit = 50): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "extracted_knowledge
                WHERE thread_id = :thread_id";

        if ($knowledgeType !== null) {
            $sql .= " AND knowledge_type = :type";
        }

        $sql .= " ORDER BY confidence DESC, created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        if ($knowledgeType !== null) {
            $stmt->bindValue(':type', $knowledgeType, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Décoder les tags
        foreach ($results as &$result) {
            $result['tags'] = json_decode($result['tags'], true) ?? [];
        }

        return $results;
    }

    /**
     * Recherche dans les connaissances
     * @param string $threadId ID du thread
     * @param string $query Terme de recherche
     * @param int $limit Nombre maximum de résultats
     * @return array Résultats de recherche
     */
    public function searchKnowledge(string $threadId, string $query, int $limit = 20): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "extracted_knowledge
                WHERE thread_id = :thread_id
                AND (content LIKE :query OR knowledge_type LIKE :query2)
                ORDER BY confidence DESC, created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':query2', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as &$result) {
            $result['tags'] = json_decode($result['tags'], true) ?? [];
        }

        return $results;
    }

    // ========================================================================
    // MÉTHODES UTILITAIRES
    // ========================================================================

    /**
     * Incrémente le compteur d'accès d'une mémoire
     * @param string $id ID de la mémoire
     * @param string $type Type de mémoire (short/long)
     */
    private function incrementAccess(string $id, string $type): void {
        $table = $type === 'short' ? 'memory_short' : 'memory_long';
        
        try {
            $sql = "UPDATE " . DB_PREFIX . "{$table}
                    SET access_count = access_count + 1,
                        last_accessed = datetime('now')
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            error_log("Error incrementing access count: " . $e->getMessage());
        }
    }

    /**
     * Vérifie le seuil de compression et déclenche l'extraction si nécessaire
     * @param string $threadId ID du thread
     */
    private function checkCompressionThreshold(string $threadId): void {
        $count = $this->countShortTerm($threadId);
        
        if ($count >= MEMORY_COMPRESSION_THRESHOLD) {
            // Déclencher l'extraction automatique
            $this->triggerAutoExtraction($threadId);
        }
    }

    /**
     * Déclenche l'extraction automatique de connaissances
     * @param string $threadId ID du thread
     */
    private function triggerAutoExtraction(string $threadId): void {
        // Récupérer les anciennes mémoires peu accédées
        $oldMemories = $this->getOldLowAccessMemories($threadId, 10);
        
        foreach ($oldMemories as $memory) {
            // Extraire les points clés comme connaissances
            $this->extractKeyPoints($threadId, $memory);
        }
    }

    /**
     * Récupère les anciennes mémoires avec faible accès
     * @param string $threadId ID du thread
     * @param int $limit Nombre maximum
     * @return array Mémoires à compresser
     */
    private function getOldLowAccessMemories(string $threadId, int $limit = 10): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "memory_short
                WHERE thread_id = :thread_id
                AND access_count <= 1
                ORDER BY created_at ASC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Extrait les points clés d'une mémoire pour la connaissance
     * @param string $threadId ID du thread
     * @param array $memory Mémoire à analyser
     */
    private function extractKeyPoints(string $threadId, array $memory): void {
        // Extraction simplifiée (pourrait utiliser l'IA pour une extraction plus intelligente)
        $content = $memory['content'];
        
        // Extraire les premières phrases comme points clés
        $sentences = preg_split('/[.!?]+/', $content, 3);
        
        if (!empty($sentences[0])) {
            $keyPoint = trim($sentences[0]);
            if (strlen($keyPoint) > 20) {
                $this->extractKnowledge(
                    $threadId,
                    $memory['id'],
                    'fact',
                    $keyPoint,
                    ['auto_extracted', 'compression'],
                    0.7
                );
            }
        }
    }

    /**
     * Sauvegarde un message en mémoire et retourne le contexte
     * @param string $threadId ID du thread
     * @param string $role Rôle (user/assistant/system)
     * @param string $content Contenu du message
     * @param string|null $taskId ID de la tâche associée
     * @param string|null $model Modèle utilisé
     * @param int $tokenCount Nombre de tokens
     * @return string ID du message
     */
    public function saveMessage(
        string $threadId,
        string $role,
        string $content,
        ?string $taskId = null,
        ?string $model = null,
        int $tokenCount = 0
    ): string {
        $id = generateId('msg');

        $sql = "INSERT INTO " . DB_PREFIX . "messages
                (id, thread_id, task_id, role, content, token_count, model_used, created_at)
                VALUES (:id, :thread_id, :task_id, :role, :content, :tokens, :model, datetime('now'))";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':thread_id' => $threadId,
            ':task_id' => $taskId,
            ':role' => $role,
            ':content' => $content,
            ':tokens' => $tokenCount,
            ':model' => $model
        ]);

        // Si c'est un message utilisateur important, l'ajouter en mémoire court terme
        if ($role === 'user' && strlen($content) > 50) {
            $this->addShortTerm($threadId, "User: " . substr($content, 0, 500), 0.6);
        }

        return $id;
    }

    /**
     * Récupère l'historique des messages d'un thread
     * @param string $threadId ID du thread
     * @param int $limit Nombre maximum de messages
     * @return array Historique des messages
     */
    public function getMessageHistory(string $threadId, int $limit = 50): array {
        $sql = "SELECT * FROM " . DB_PREFIX . "messages
                WHERE thread_id = :thread_id
                ORDER BY created_at ASC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':thread_id', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtient le contexte complet d'un thread pour l'IA
     * @param string $threadId ID du thread
     * @param int $messageLimit Nombre maximum de messages
     * @return array Contexte formaté pour l'API Mistral
     */
    public function getThreadContext(string $threadId, int $messageLimit = 50): array {
        $context = [
            'messages' => $this->getMessageHistory($threadId, $messageLimit),
            'short_term_memories' => $this->getRecentShortTerm($threadId, 20),
            'long_term_memories' => $this->getLongTerm($threadId, null, 10),
            'knowledge' => $this->getKnowledge($threadId, null, 10)
        ];

        return $context;
    }

    /**
     * Nettoie la mémoire d'un thread (archivage)
     * @param string $threadId ID du thread
     * @return array Statistiques de nettoyage
     */
    public function cleanupThread(string $threadId): array {
        $stats = [];

        try {
            // Supprimer les anciennes mémoires court terme
            $stmt = $this->db->prepare(
                "DELETE FROM " . DB_PREFIX . "memory_short 
                 WHERE thread_id = :thread_id AND created_at < datetime('now', '-30 days')"
            );
            $stmt->execute([':thread_id' => $threadId]);
            $stats['short_term_deleted'] = $stmt->rowCount();

            // Supprimer les anciens messages
            $stmt = $this->db->prepare(
                "DELETE FROM " . DB_PREFIX . "messages 
                 WHERE thread_id = :thread_id AND created_at < datetime('now', '-60 days')"
            );
            $stmt->execute([':thread_id' => $threadId]);
            $stats['messages_deleted'] = $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Memory cleanup error: " . $e->getMessage());
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }
}
