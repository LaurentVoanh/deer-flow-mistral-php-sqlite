<?php
/**
 * DeerFlow PHP - Configuration Centrale
 * Fichier de configuration principal pour l'architecture multi-agents PHP
 * Version optimisée pour Hostinger avec API Mistral Free Tier
 *
 * @package DeerFlow
 * @version 1.0.0
 */

// ============================================================================
// 📁 CHEMINS ET RACINES
// ============================================================================

define('DEERFLOW_VERSION', '1.0.0');
define('START_TIME', microtime(true));

// Racine absolue du projet
define('ROOT_PATH', __DIR__);
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/'));

// Dossiers principaux
define('DIR_SKILLS', ROOT_PATH . '/skills');
define('DIR_SANDBOX', ROOT_PATH . '/sandbox');
define('DIR_MEMORY', ROOT_PATH . '/memory');
define('DIR_LOGS', ROOT_PATH . '/logs');
define('DIR_TEMP', ROOT_PATH . '/temp');
define('DIR_CONFIG', ROOT_PATH . '/config');

// URLs
define('URL_ASSETS', BASE_URL . '/assets');
define('URL_API', BASE_URL . '/gateway.php');

// ============================================================================
// 🗄️ CONFIGURATION BASE DE DONNÉES SQLITE
// ============================================================================

// Type de base de données (sqlite uniquement - MySQL interdit)
define('DB_TYPE', 'sqlite');

// Configuration SQLite (recommandé pour Hostinger sans MySQL)
define('DB_SQLITE_FILE', ROOT_PATH . '/deerflow.db');
define('DB_SQLITE_WAL', true); // Mode WAL pour meilleures performances

// Préfixe des tables
define('DB_PREFIX', 'df_');

// ============================================================================
// 🧠 CONFIGURATION API MISTRAL
// ============================================================================

// Clés API Mistral (plusieurs clés pour rotation en cas de rate limit)
define('MISTRAL_API_KEYS', [
    'a5qaRTjWUjGJpAk5z35XcdEP5ZbH8Raked',
    'bo3rG1zvdq1yDOvjb7Z4J3J3eHXRShytue',
    'cvEzQMKN74Ez8RIwJ6y8J30ENDjFruXkFf'
]);

// Clé API principale (pour compatibilité)
define('MISTRAL_API_KEY', MISTRAL_API_KEYS[0]);

// End point API Mistral
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// Modèle par défaut pour le lead agent
define('LEAD_AGENT_DEFAULT_MODEL', 'mistral-large-2512');

// Modèle pour les rapports et synthèses
define('MISTRAL_MODEL_REPORT', 'mistral-medium-2505');

// Modèle pour la planification de tâches
define('MISTRAL_MODEL_PLANNER', 'mistral-large-2512');

// Modèle pour la recherche
define('MISTRAL_MODEL_RESEARCH', 'mistral-medium-2505');

// Modèle pour la génération de code
define('MISTRAL_MODEL_CODEGEN', 'codestral-2508');

// Modèle pour l'analyse de données
define('MISTRAL_MODEL_ANALYSIS', 'magistral-medium-2509');

// Modèle par défaut
define('MISTRAL_MODEL_DEFAULT', 'mistral-small-2506');

// Timeouts cURL (300 secondes minimum)
define('MISTRAL_TIMEOUT', 300);
define('MISTRAL_CONNECT_TIMEOUT', 300);
define('CURL_TIMEOUT', 300);
define('CURL_CONNECT_TIMEOUT', 300);

// Modèles disponibles classés par spécialité
define('MISTRAL_MODELS', [
    // Code et développement
    'codestral-2508' => [
        'type' => 'code',
        'max_tokens' => 50000,
        'specialty' => 'écriture_code',
        'priority' => 1
    ],
    'devstral-2512' => [
        'type' => 'code',
        'max_tokens' => 50000,
        'specialty' => 'architecture',
        'priority' => 2
    ],
    'devstral-medium-2507' => [
        'type' => 'code',
        'max_tokens' => 50000,
        'specialty' => 'development',
        'priority' => 2
    ],
    'devstral-small-2507' => [
        'type' => 'code',
        'max_tokens' => 50000,
        'specialty' => 'scripts',
        'priority' => 3
    ],
    
    // Contexte large
    'mistral-small-2603' => [
        'type' => 'general',
        'max_tokens' => 375000,
        'specialty' => 'contexte_large',
        'priority' => 1
    ],
    'mistral-medium-2505' => [
        'type' => 'general',
        'max_tokens' => 375000,
        'specialty' => 'documentation',
        'priority' => 2
    ],
    'mistral-medium-2508' => [
        'type' => 'general',
        'max_tokens' => 375000,
        'specialty' => 'documentation',
        'priority' => 2
    ],
    
    // Vision / Images
    'pixtral-12b-2409' => [
        'type' => 'vision',
        'max_tokens' => 50000,
        'specialty' => 'reconnaissance_image',
        'priority' => 1
    ],
    'pixtral-large-2411' => [
        'type' => 'vision',
        'max_tokens' => 50000,
        'specialty' => 'vision_haute_performance',
        'priority' => 1
    ],
    
    // Raisonnement complexe
    'mistral-large-2512' => [
        'type' => 'reasoning',
        'max_tokens' => 50000,
        'specialty' => 'raisonnement_complexe',
        'priority' => 1
    ],
    
    // Analyse de données
    'magistral-medium-2509' => [
        'type' => 'analysis',
        'max_tokens' => 75000,
        'specialty' => 'analyse_donnees',
        'priority' => 1
    ],
    'magistral-small-2509' => [
        'type' => 'analysis',
        'max_tokens' => 75000,
        'specialty' => 'analyse_legere',
        'priority' => 2
    ],
    
    // Micro-services et rapidité
    'ministral-14b-2512' => [
        'type' => 'fast',
        'max_tokens' => 50000,
        'specialty' => 'micro_services',
        'priority' => 2
    ],
    'ministral-8b-2512' => [
        'type' => 'fast',
        'max_tokens' => 50000,
        'specialty' => 'rapidite',
        'priority' => 2
    ],
    'ministral-3b-2512' => [
        'type' => 'fast',
        'max_tokens' => 50000,
        'specialty' => 'taches_simples',
        'priority' => 3
    ],
    
    // Généralistes
    'mistral-small-2506' => [
        'type' => 'general',
        'max_tokens' => 50000,
        'specialty' => 'scripts_rapides',
        'priority' => 2
    ],
    'open-mistral-nemo' => [
        'type' => 'general',
        'max_tokens' => 50000,
        'specialty' => 'chat_technique',
        'priority' => 2
    ],
    
    // Créativité
    'labs-mistral-small-creative' => [
        'type' => 'creative',
        'max_tokens' => 50000,
        'specialty' => 'brainstorming',
        'priority' => 1
    ],
    
    // Audio/Dialogue
    'voxtral-mini-2507' => [
        'type' => 'audio',
        'max_tokens' => 50000,
        'specialty' => 'analyse_dialogues',
        'priority' => 2
    ],
    'voxtral-small-2507' => [
        'type' => 'audio',
        'max_tokens' => 50000,
        'specialty' => 'analyse_audio',
        'priority' => 2
    ]
]);

// ============================================================================
// ⏳ GESTION DU TEMPS ET DU DÉBIT (CRITIQUE)
// ============================================================================

// Temps d'exécution maximum (10 minutes)
define('AGENT_TIMEOUT_DEFAULT', 600);
define('AGENT_TIMEOUT_MAX', 600);

// Timeouts cURL (300 secondes minimum)
define('CURL_TIMEOUT', 300);
define('CURL_CONNECT_TIMEOUT', 300);

// Rate Limit: 1 requête par seconde entre chaque appel API
define('API_RATE_LIMIT_DELAY', 1);

// Nombre maximum de retries en cas d'échec API
define('API_MAX_RETRIES', 3);

// Délai entre les retries (en secondes)
define('API_RETRY_DELAY', 2);

// ============================================================================
// 🧠 CONFIGURATION MÉMOIRE
// ============================================================================

// Taille maximale de la mémoire par thread (en entrées)
define('MEMORY_MAX_ENTRIES_PER_THREAD', 1000);

// Nombre d'entrées à garder en mémoire récente
define('MEMORY_RECENT_COUNT', 50);

// Seuil de compression automatique (quand déclencher l'extraction)
define('MEMORY_COMPRESSION_THRESHOLD', 500);

// Types de mémoire
define('MEMORY_TYPE_SHORT_TERM', 'short_term');
define('MEMORY_TYPE_LONG_TERM', 'long_term');
define('MEMORY_TYPE_EXTRACTED', 'extracted');

// ============================================================================
// 🧠 CONFIGURATION AGENTS
// ============================================================================

// Nombre maximum de sous-agents parallèles
define('SUBAGENT_MAX_PARALLEL', 3);

// Interval de heartbeat (en secondes)
define('AGENT_HEARTBEAT_INTERVAL', 5);

// Modèle par défaut pour le lead agent (déjà défini plus haut, gardé pour compatibilité)
// define('LEAD_AGENT_DEFAULT_MODEL', 'mistral-large-2512');

// ============================================================================
// 🔧 CONFIGURATION SANDBOX
// ============================================================================

// Isolation stricte par thread
define('SANDBOX_STRICT_ISOLATION', true);

// Chemin de base du sandbox
define('SANDBOX_BASE', DIR_SANDBOX);

// Extensions de fichiers autorisées
define('SANDBOX_ALLOWED_EXTENSIONS', [
    'txt', 'md', 'json', 'xml', 'csv',
    'php', 'js', 'css', 'html', 'sql',
    'py', 'rb', 'sh', 'bat',
    'log', 'conf', 'yaml', 'yml'
]);

// Taille maximale par fichier (en octets)
define('SANDBOX_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Taille maximale totale par thread (en octets)
define('SANDBOX_MAX_THREAD_SIZE', 100 * 1024 * 1024); // 100MB

// Nettoyage automatique (en jours)
define('SANDBOX_AUTO_CLEANUP_DAYS', 7);

// ============================================================================
// 📡 CONFIGURATION SSE (Server-Sent Events)
// ============================================================================

// Délai entre les événements (en microsecondes)
define('SSE_HEARTBEAT_INTERVAL', 30000000); // 30 secondes

// Timeout de connexion SSE (en secondes)
define('SSE_CONNECTION_TIMEOUT', 300);

// Buffer size pour les événements
define('SSE_BUFFER_SIZE', 4096);

// Types d'événements SSE
define('SSE_EVENT_PROGRESS', 'progress');
define('SSE_EVENT_MESSAGE', 'message');
define('SSE_EVENT_ERROR', 'error');
define('SSE_EVENT_COMPLETE', 'complete');
define('SSE_EVENT_SUBAGENT', 'subagent');
define('SSE_EVENT_MEMORY', 'memory');

// ============================================================================
// 📚 CONFIGURATION SKILLS
// ============================================================================

// Dossier des skills
define('SKILLS_DIR', DIR_SKILLS);

// Extension des fichiers skill
define('SKILLS_EXTENSION', '.md');

// Skills de base inclus
define('SKILLS_CORE', [
    'research',
    'codegen',
    'report',
    'analysis'
]);

// Cache des skills (en secondes)
define('SKILLS_CACHE_TTL', 300);

// ============================================================================
// 📝 CONFIGURATION LOGS
// ============================================================================

// Activer/désactiver les logs
define('LOG_ENABLED', true);

// Niveau de log minimum (DEBUG, INFO, WARNING, ERROR, CRITICAL)
define('LOG_LEVEL', 'DEBUG');

// Format des logs
define('LOG_FORMAT', '[{datetime}] [{level}] {message}');

// Rotation des logs (taille max en octets)
define('LOG_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Nombre de fichiers de log à conserver
define('LOG_MAX_FILES', 5);

// ============================================================================
// 🔒 SÉCURITÉ
// ============================================================================

// Clé secrète pour les tokens (à changer en production !)
define('SECRET_KEY', 'deerflow_' . bin2hex(random_bytes(32)));

// Durée de vie des tokens (en secondes)
define('TOKEN_TTL', 3600);

// Nombre maximum de requêtes par minute par IP
define('RATE_LIMIT_MAX_REQUESTS', 60);

// Fenêtre de temps pour le rate limiting (en secondes)
define('RATE_LIMIT_WINDOW', 60);

// IPs autorisées (vide = toutes autorisées)
define('ALLOWED_IPS', []);

// Headers de sécurité
define('SECURITY_HEADERS', [
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: DENY',
    'X-XSS-Protection: 1; mode=block',
    'Referrer-Policy: strict-origin-when-cross-origin'
]);

// ============================================================================
// ⚙️ OPTIONS DIVERSES
// ============================================================================

// Encodage par défaut
define('DEFAULT_ENCODING', 'UTF-8');

// Fuseau horaire
define('DEFAULT_TIMEZONE', 'Europe/Paris');

// Langue par défaut
define('DEFAULT_LANGUAGE', 'fr');

// Separator pour les IDs uniques
define('ID_SEPARATOR', '_');

// Longueur des IDs générés
define('ID_LENGTH', 12);

// ============================================================================
// 🎨 INTERFACE UTILISATEUR
// ============================================================================

// Titre de l'application
define('APP_TITLE', 'DeerFlow PHP - Agent Auto-Codeur');

// Thème par défaut (light, dark, auto)
define('UI_THEME', 'dark');

// Messages par défaut
define('UI_WELCOME_MESSAGE', 'Bienvenue sur DeerFlow. Quelle tâche souhaitez-vous accomplir ?');
define('UI_PLACEHOLDER_INPUT', 'Décrivez votre tâche complexe...');

// ============================================================================
// 📊 MÉTRIQUES ET PERFORMANCE
// ============================================================================

// Activer les métriques de performance
define('METRICS_ENABLED', true);

// Seuil d'alerte pour les requêtes lentes (en secondes)
define('METRICS_SLOW_QUERY_THRESHOLD', 2.0);

// ============================================================================
// 🔄 CONSTANTES INTERNES (NE PAS MODIFIER)
// ============================================================================

// États possibles d'une tâche
define('TASK_STATUS_PENDING', 'pending');
define('TASK_STATUS_RUNNING', 'running');
define('TASK_STATUS_COMPLETED', 'completed');
define('TASK_STATUS_FAILED', 'failed');
define('TASK_STATUS_CANCELLED', 'cancelled');

// Types de tâches
define('TASK_TYPE_SIMPLE', 'simple');
define('TASK_TYPE_COMPOUND', 'compound');
define('TASK_TYPE_ORCHESTRATED', 'orchestrated');

// Priorités
define('PRIORITY_LOW', 1);
define('PRIORITY_NORMAL', 5);
define('PRIORITY_HIGH', 10);
define('PRIORITY_CRITICAL', 100);

// ============================================================================
// 🛠️ FONCTIONS UTILITAIRES DE CONFIGURATION
// ============================================================================

/**
 * Vérifie que tous les dossiers requis existent
 * @return bool True si tout est OK
 */
function checkDirectories(): bool {
    $dirs = [
        DIR_SKILLS,
        DIR_SANDBOX,
        DIR_MEMORY,
        DIR_LOGS,
        DIR_TEMP
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            error_log("DeerFlow: Le dossier {$dir} n'est pas accessible en écriture");
            return false;
        }
    }
    return true;
}

/**
 * Génère un ID unique
 * @param string $prefix Préfixe optionnel
 * @return string ID unique
 */
function generateId(string $prefix = ''): string {
    $id = bin2hex(random_bytes(ID_LENGTH / 2));
    return $prefix ? $prefix . ID_SEPARATOR . $id : $id;
}

/**
 * Formate une durée en secondes
 * @param float $seconds Durée en secondes
 * @return string Durée formatée
 */
function formatDuration(float $seconds): string {
    if ($seconds < 1) {
        return round($seconds * 1000) . 'ms';
    }
    if ($seconds < 60) {
        return round($seconds, 2) . 's';
    }
    if ($seconds < 3600) {
        $mins = floor($seconds / 60);
        $secs = round($seconds % 60);
        return "{$mins}m {$secs}s";
    }
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    return "{$hours}h {$mins}m";
}

/**
 * Nettoie une chaîne pour usage sécurisé
 * @param string $input Chaîne à nettoyer
 * @return string Chaîne nettoyée
 */
function sanitizeInput(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, DEFAULT_ENCODING);
}

/**
 * Sélectionne le modèle Mistral optimal selon le type de tâche et la taille du contexte
 * @param string $taskType Type de tâche (code, research, analysis, etc.)
 * @param int $contextSize Taille estimée du contexte en tokens
 * @return string Nom du modèle sélectionné
 */
function selectMistralModel(string $taskType, int $contextSize = 0): string {
    $models = MISTRAL_MODELS;
    
    // Si contexte > 50000 tokens, utiliser les modèles à grand contexte
    if ($contextSize > 50000) {
        if ($taskType === 'code' || $taskType === 'codegen') {
            return 'mistral-small-2603';
        }
        return 'mistral-small-2603';
    }
    
    // Sélection par type de tâche
    switch ($taskType) {
        case 'code':
        case 'codegen':
            return 'codestral-2508';
        
        case 'vision':
        case 'image':
            return 'pixtral-large-2411';
        
        case 'reasoning':
        case 'complex':
            return 'mistral-large-2512';
        
        case 'analysis':
        case 'data':
            return 'magistral-medium-2509';
        
        case 'creative':
        case 'brainstorm':
            return 'labs-mistral-small-creative';
        
        case 'fast':
        case 'simple':
            return 'ministral-8b-2512';
        
        default:
            return LEAD_AGENT_DEFAULT_MODEL;
    }
}

/**
 * Obtient une clé API Mistral (rotation simple)
 * @param int $index Index de la clé à utiliser
 * @return string Clé API
 */
function getMistralApiKey(int $index = 0): string {
    $keys = MISTRAL_API_KEYS;
    return $keys[$index % count($keys)];
}

// ============================================================================
// INITIALISATION
// ============================================================================

// Définir le fuseau horaire
date_default_timezone_set(DEFAULT_TIMEZONE);

// Augmenter les limites pour les traitements longs
set_time_limit(AGENT_TIMEOUT_DEFAULT);
ini_set('memory_limit', '512M');

// Vérifier les dossiers
checkDirectories();

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// Log de démarrage en mode debug
if (LOG_ENABLED && LOG_LEVEL === 'DEBUG') {
    error_log("DeerFlow v" . DEERFLOW_VERSION . " démarré à " . date('Y-m-d H:i:s'));
}
