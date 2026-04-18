<?php
/**
 * DeerFlow PHP - Couche Base de Données
 * Gestion PDO + Schéma SQL + Initialisation WAL pour SQLite
 * Optimisé pour Hostinger avec SQLite uniquement
 *
 * @package DeerFlow
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';

class Database {

    private static ?PDO $instance = null;
    private static bool $initialized = false;

    /**
     * Obtient l'instance unique de la connexion PDO
     * @return PDO Instance de connexion
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Établit la connexion à la base de données
     */
    private static function connect(): void {
        try {
            // Connexion SQLite uniquement (MySQL interdit)
            self::connectSQLite();

            // Configuration commune
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Initialiser le schéma
            self::initializeSchema();
            self::$initialized = true;

        } catch (PDOException $e) {
            error_log("DeerFlow DB Error: " . $e->getMessage());
            throw new Exception("Échec de la connexion à la base de données: " . $e->getMessage());
        }
    }

    /**
     * Connexion SQLite avec mode WAL pour performances optimales
     */
    private static function connectSQLite(): void {
        $dsn = 'sqlite:' . DB_SQLITE_FILE;

        self::$instance = new PDO($dsn);

        // Activer le mode WAL si configuré (meilleures performances en écriture)
        if (DB_SQLITE_WAL) {
            self::$instance->exec('PRAGMA journal_mode=WAL');
            self::$instance->exec('PRAGMA synchronous=NORMAL');
            self::$instance->exec('PRAGMA cache_size=10000');
            self::$instance->exec('PRAGMA temp_store=MEMORY');
            self::$instance->exec('PRAGMA busy_timeout=5000');
        }

        // Optimisations supplémentaires
        self::$instance->exec('PRAGMA foreign_keys=ON');
        self::$instance->exec('PRAGMA encoding="UTF-8"');
        self::$instance->exec('PRAGMA page_size=4096');
        self::$instance->exec('PRAGMA mmap_size=268435456'); // 256MB
    }

    /**
     * Initialise le schéma de la base de données
     */
    private static function initializeSchema(): void {
        if (self::$initialized) {
            return;
        }

        $schema = self::getSchema();

        foreach ($schema as $table => $sql) {
            try {
                self::$instance->exec($sql);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'already exists')) {
                    error_log("DeerFlow Schema Error for {$table}: " . $e->getMessage());
                }
            }
        }

        // Créer les index pour optimiser les performances
        self::createIndexes();

        // Données d'initialisation
        self::initializeDefaultData();
    }

    /**
     * Crée les index pour optimiser les requêtes
     */
    private static function createIndexes(): void {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_tasks_thread ON " . DB_PREFIX . "tasks(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_status ON " . DB_PREFIX . "tasks(status)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_parent ON " . DB_PREFIX . "tasks(parent_task_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_thread ON " . DB_PREFIX . "messages(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_messages_task ON " . DB_PREFIX . "messages(task_id)",
            "CREATE INDEX IF NOT EXISTS idx_memory_short_thread ON " . DB_PREFIX . "memory_short(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_memory_long_thread ON " . DB_PREFIX . "memory_long(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_knowledge_thread ON " . DB_PREFIX . "extracted_knowledge(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_sandbox_thread ON " . DB_PREFIX . "sandbox_files(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_plans_thread ON " . DB_PREFIX . "plans(thread_id)",
            "CREATE INDEX IF NOT EXISTS idx_agent_logs_thread ON " . DB_PREFIX . "agent_logs(thread_id)",
        ];

        foreach ($indexes as $indexSql) {
            try {
                self::$instance->exec($indexSql);
            } catch (PDOException $e) {
                error_log("Index creation error: " . $e->getMessage());
            }
        }
    }

    /**
     * Retourne le schéma SQL complet pour SQLite
     * @return array Tableau des tables et leur SQL
     */
    private static function getSchema(): array {
        $prefix = DB_PREFIX;

        return [
            'threads' => "
                CREATE TABLE IF NOT EXISTS {$prefix}threads (
                    id TEXT PRIMARY KEY,
                    name TEXT NOT NULL DEFAULT 'Nouveau thread',
                    status TEXT DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    metadata TEXT
                )
            ",

            'tasks' => "
                CREATE TABLE IF NOT EXISTS {$prefix}tasks (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    parent_task_id TEXT,
                    title TEXT NOT NULL,
                    description TEXT,
                    type TEXT DEFAULT 'simple',
                    status TEXT DEFAULT 'pending',
                    priority INTEGER DEFAULT 5,
                    model TEXT DEFAULT 'mistral-large-2512',
                    progress INTEGER DEFAULT 0,
                    result TEXT,
                    error_message TEXT,
                    started_at DATETIME,
                    completed_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    metadata TEXT,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id),
                    FOREIGN KEY (parent_task_id) REFERENCES {$prefix}tasks(id)
                )
            ",

            'messages' => "
                CREATE TABLE IF NOT EXISTS {$prefix}messages (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    task_id TEXT,
                    role TEXT NOT NULL CHECK(role IN ('user', 'assistant', 'system')),
                    content TEXT NOT NULL,
                    token_count INTEGER DEFAULT 0,
                    model_used TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    metadata TEXT,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id),
                    FOREIGN KEY (task_id) REFERENCES {$prefix}tasks(id)
                )
            ",

            'memory_short' => "
                CREATE TABLE IF NOT EXISTS {$prefix}memory_short (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    content TEXT NOT NULL,
                    importance REAL DEFAULT 0.5,
                    access_count INTEGER DEFAULT 0,
                    last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id)
                )
            ",

            'memory_long' => "
                CREATE TABLE IF NOT EXISTS {$prefix}memory_long (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    summary TEXT NOT NULL,
                    keywords TEXT,
                    category TEXT,
                    importance REAL DEFAULT 0.7,
                    access_count INTEGER DEFAULT 0,
                    last_accessed DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id)
                )
            ",

            'extracted_knowledge' => "
                CREATE TABLE IF NOT EXISTS {$prefix}extracted_knowledge (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    source_memory_id TEXT,
                    knowledge_type TEXT NOT NULL,
                    content TEXT NOT NULL,
                    confidence REAL DEFAULT 0.8,
                    tags TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id),
                    FOREIGN KEY (source_memory_id) REFERENCES {$prefix}memory_short(id)
                )
            ",

            'sandbox_files' => "
                CREATE TABLE IF NOT EXISTS {$prefix}sandbox_files (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    task_id TEXT,
                    filepath TEXT NOT NULL,
                    filename TEXT NOT NULL,
                    size INTEGER DEFAULT 0,
                    mime_type TEXT,
                    checksum TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id),
                    FOREIGN KEY (task_id) REFERENCES {$prefix}tasks(id)
                )
            ",

            'agent_logs' => "
                CREATE TABLE IF NOT EXISTS {$prefix}agent_logs (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    task_id TEXT,
                    agent_type TEXT NOT NULL,
                    action TEXT NOT NULL,
                    details TEXT,
                    duration_ms INTEGER,
                    tokens_used INTEGER DEFAULT 0,
                    model_used TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id),
                    FOREIGN KEY (task_id) REFERENCES {$prefix}tasks(id)
                )
            ",

            'plans' => "
                CREATE TABLE IF NOT EXISTS {$prefix}plans (
                    id TEXT PRIMARY KEY,
                    thread_id TEXT NOT NULL,
                    task TEXT NOT NULL,
                    steps TEXT NOT NULL,
                    status TEXT DEFAULT 'pending',
                    current_step INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (thread_id) REFERENCES {$prefix}threads(id)
                )
            ",

            'sessions' => "
                CREATE TABLE IF NOT EXISTS {$prefix}sessions (
                    id TEXT PRIMARY KEY,
                    user_id TEXT,
                    ip_address TEXT,
                    user_agent TEXT,
                    data TEXT,
                    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",

            'rate_limits' => "
                CREATE TABLE IF NOT EXISTS {$prefix}rate_limits (
                    id TEXT PRIMARY KEY,
                    identifier TEXT NOT NULL,
                    request_count INTEGER DEFAULT 1,
                    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(identifier, window_start)
                )
            ",

            'api_usage' => "
                CREATE TABLE IF NOT EXISTS {$prefix}api_usage (
                    id TEXT PRIMARY KEY,
                    api_key_hash TEXT NOT NULL,
                    model_used TEXT NOT NULL,
                    tokens_input INTEGER DEFAULT 0,
                    tokens_output INTEGER DEFAULT 0,
                    request_duration_ms INTEGER,
                    status TEXT DEFAULT 'success',
                    error_message TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            "
        ];
    }

    /**
     * Initialise les données par défaut
     */
    private static function initializeDefaultData(): void {
        // Insérer un thread système si inexistant
        try {
            $stmt = self::$instance->prepare(
                "INSERT OR IGNORE INTO {$prefix}threads (id, name, status, metadata) 
                 VALUES (:id, :name, :status, :metadata)"
            );
            $stmt->execute([
                ':id' => 'system',
                ':name' => 'Thread Système',
                ':status' => 'active',
                ':metadata' => json_encode(['type' => 'system', 'created_by' => 'init'])
            ]);
        } catch (PDOException $e) {
            error_log("Error initializing default data: " . $e->getMessage());
        }
    }

    /**
     * Réinitialise la connexion (utile pour tests)
     */
    public static function resetInstance(): void {
        self::$instance = null;
        self::$initialized = false;
    }

    /**
     * Vérifie si la base de données est initialisée
     * @return bool
     */
    public static function isInitialized(): bool {
        return self::$initialized;
    }

    /**
     * Exécute une requête transactionnelle
     * @param callable $callback Fonction à exécuter dans la transaction
     * @return mixed Résultat du callback
     */
    public static function transaction(callable $callback) {
        try {
            self::$instance->beginTransaction();
            $result = $callback(self::$instance);
            self::$instance->commit();
            return $result;
        } catch (Exception $e) {
            if (self::$instance->inTransaction()) {
                self::$instance->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Nettoie les anciennes données (maintenance)
     * @param int $days Nombre de jours à conserver
     * @return array Statistiques de nettoyage
     */
    public static function cleanupOldData(int $days = 30): array {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stats = [];

        try {
            // Supprimer les vieux logs
            $stmt = self::$instance->prepare(
                "DELETE FROM " . DB_PREFIX . "agent_logs WHERE created_at < :cutoff"
            );
            $stmt->execute([':cutoff' => $cutoff]);
            $stats['logs_deleted'] = $stmt->rowCount();

            // Supprimer les vieilles sessions
            $stmt = self::$instance->prepare(
                "DELETE FROM " . DB_PREFIX . "sessions WHERE last_activity < :cutoff"
            );
            $stmt->execute([':cutoff' => $cutoff]);
            $stats['sessions_deleted'] = $stmt->rowCount();

            // Vider le WAL et checkpoint
            self::$instance->exec('PRAGMA wal_checkpoint(TRUNCATE)');

        } catch (PDOException $e) {
            error_log("Cleanup error: " . $e->getMessage());
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }
}

// ============================================================================
// FONCTIONS HELPER POUR LA BASE DE DONNÉES
// ============================================================================

/**
 * Initialise la table plans si elle n'existe pas
 * @param PDO $db Instance PDO
 */
function initPlansTable(PDO $db): void {
    // La table est déjà créée via le schéma automatique
    // Cette fonction est gardée pour compatibilité
}

/**
 * Enregistre l'usage de l'API Mistral
 * @param string $apiKeyHash Hash de la clé API
 * @param string $model Modèle utilisé
 * @param int $tokensInput Tokens en entrée
 * @param int $tokensOutput Tokens en sortie
 * @param int $durationMs Durée de la requête
 * @param string $status Statut (success/error)
 * @param string|null $errorMessage Message d'erreur si échec
 */
function logApiUsage(
    string $apiKeyHash,
    string $model,
    int $tokensInput,
    int $tokensOutput,
    int $durationMs,
    string $status = 'success',
    ?string $errorMessage = null
): void {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO " . DB_PREFIX . "api_usage 
            (id, api_key_hash, model_used, tokens_input, tokens_output, request_duration_ms, status, error_message)
            VALUES (:id, :key_hash, :model, :input, :output, :duration, :status, :error)
        ");
        $stmt->execute([
            ':id' => generateId('api'),
            ':key_hash' => $apiKeyHash,
            ':model' => $model,
            ':input' => $tokensInput,
            ':output' => $tokensOutput,
            ':duration' => $durationMs,
            ':status' => $status,
            ':error' => $errorMessage
        ]);
    } catch (Exception $e) {
        error_log("Failed to log API usage: " . $e->getMessage());
    }
}

/**
 * Obtient une instance de connexion PDO
 * @return PDO Instance de connexion
 */
function getDbConnection(): PDO {
    return Database::getInstance();
}

/**
 * Récupère les statistiques d'usage de l'API
 * @param int $days Nombre de jours à analyser
 * @return array Statistiques
 */
function getApiUsageStats(int $days = 7): array {
    try {
        $db = Database::getInstance();
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stmt = $db->prepare("
            SELECT 
                model_used,
                COUNT(*) as requests,
                SUM(tokens_input) as total_input_tokens,
                SUM(tokens_output) as total_output_tokens,
                AVG(request_duration_ms) as avg_duration,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
            FROM " . DB_PREFIX . "api_usage
            WHERE created_at >= :cutoff
            GROUP BY model_used
            ORDER BY requests DESC
        ");
        $stmt->execute([':cutoff' => $cutoff]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get API usage stats: " . $e->getMessage());
        return [];
    }
}
