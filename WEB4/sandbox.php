<?php
/**
 * DeerFlow PHP - Sandbox Isolé par Thread
 * Système de fichiers virtuel isolé pour chaque thread d'exécution
 * Optimisé pour Hostinger avec SQLite
 *
 * @package DeerFlow
 * @version 1.0.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Sandbox {

    private string $threadId;
    private string $basePath;
    private PDO $db;

    /**
     * Constructeur
     * @param string $threadId ID du thread
     * @param PDO|null $db Instance PDO (optionnelle)
     */
    public function __construct(string $threadId, ?PDO $db = null) {
        $this->threadId = $threadId;
        $this->basePath = SANDBOX_BASE . '/' . $threadId;
        $this->db = $db ?? Database::getInstance();

        // Initialiser le dossier du thread
        $this->initializeThreadSandbox();
    }

    /**
     * Initialise le sandbox pour le thread
     */
    private function initializeThreadSandbox(): void {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        // Créer les sous-dossiers standards
        $subdirs = ['files', 'temp', 'output', 'uploads'];
        foreach ($subdirs as $subdir) {
            $path = $this->basePath . '/' . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Vérifie si une extension est autorisée
     * @param string $filename Nom du fichier
     * @return bool True si autorisé
     */
    private function isExtensionAllowed(string $filename): bool {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, SANDBOX_ALLOWED_EXTENSIONS);
    }

    /**
     * Nettoie un nom de fichier (sécurité)
     * @param string $filename Nom à nettoyer
     * @return string Nom nettoyé
     */
    private function sanitizeFilename(string $filename): string {
        // Supprimer les caractères dangereux
        $filename = preg_replace('/[^\w\.\-]/u', '_', $filename);

        // Supprimer les séquences de points multiples
        $filename = preg_replace('/\.+/', '.', $filename);

        // Limiter la longueur
        $filename = substr($filename, 0, 200);

        return trim($filename, '.');
    }

    /**
     * Nettoie un chemin relatif
     * @param string $relativePath Chemin à nettoyer
     * @return string Chemin nettoyé
     */
    private function sanitizePath(string $relativePath): string {
        // Supprimer les .. pour éviter les sorties du sandbox
        $relativePath = str_replace('..', '', $relativePath);
        
        // Normaliser les séparateurs
        $relativePath = str_replace('\\', '/', $relativePath);
        
        // Supprimer les slashes doubles
        $relativePath = preg_replace('/\/+/', '/', $relativePath);
        
        // Supprimer le slash leading
        $relativePath = ltrim($relativePath, '/');
        
        return $relativePath;
    }

    /**
     * Calcule l'espace utilisé par le thread
     * @return int Espace en octets
     */
    public function getUsedSpace(): int {
        $totalSize = 0;

        if (is_dir($this->basePath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->basePath)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }
        }

        return $totalSize;
    }

    /**
     * Vérifie si on peut ajouter un fichier (quota)
     * @param int $additionalSize Taille supplémentaire prévue
     * @return bool True si possible
     */
    public function canAddFile(int $additionalSize): bool {
        $usedSpace = $this->getUsedSpace();
        return ($usedSpace + $additionalSize) <= SANDBOX_MAX_THREAD_SIZE;
    }

    /**
     * Écrit un fichier dans le sandbox
     * @param string $relativePath Chemin relatif depuis la base du thread
     * @param string $content Contenu du fichier
     * @param string|null $taskId ID de la tâche associée (optionnel)
     * @return array Informations sur le fichier créé
     * @throws Exception Si erreur d'écriture
     */
    public function writeFile(string $relativePath, string $content, ?string $taskId = null): array {
        // Nettoyer et valider le chemin
        $relativePath = $this->sanitizePath($relativePath);
        $filename = basename($relativePath);

        // Vérifier l'extension
        if (!$this->isExtensionAllowed($filename)) {
            throw new Exception("Extension de fichier non autorisée: " . pathinfo($filename, PATHINFO_EXTENSION));
        }

        // Vérifier la taille
        $size = strlen($content);
        if ($size > SANDBOX_MAX_FILE_SIZE) {
            throw new Exception("Fichier trop volumineux: {$size} octets (max: " . SANDBOX_MAX_FILE_SIZE . ")");
        }

        // Vérifier le quota du thread
        if (!$this->canAddFile($size)) {
            throw new Exception("Quota du thread dépassé");
        }

        // Construire le chemin complet
        $fullPath = $this->basePath . '/' . $relativePath;

        // Créer les dossiers parents si nécessaire
        $parentDir = dirname($fullPath);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        // Vérifier qu'on reste dans le sandbox (sécurité)
        $realBase = realpath($this->basePath);
        $realPath = realpath(dirname($fullPath)) ?: dirname($fullPath);

        if (strpos($realPath, $realBase) !== 0) {
            throw new Exception("Tentative d'écriture hors du sandbox détectée");
        }

        // Écrire le fichier
        $bytesWritten = file_put_contents($fullPath, $content);

        if ($bytesWritten === false) {
            throw new Exception("Échec de l'écriture du fichier");
        }

        // Calculer le checksum
        $checksum = hash('sha256', $content);

        // Déterminer le MIME type
        $mimeType = $this->detectMimeType($fullPath, $filename);

        // Enregistrer en base de données
        $fileId = generateId('file');
        $this->registerFile($fileId, $relativePath, $filename, $size, $mimeType, $checksum, $taskId);

        return [
            'id' => $fileId,
            'path' => $relativePath,
            'full_path' => $fullPath,
            'filename' => $filename,
            'size' => $size,
            'mime_type' => $mimeType,
            'checksum' => $checksum,
            'url' => $this->getFileUrl($relativePath),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Lit un fichier du sandbox
     * @param string $relativePath Chemin relatif
     * @return string Contenu du fichier
     * @throws Exception Si fichier non trouvé
     */
    public function readFile(string $relativePath): string {
        $fullPath = $this->resolvePath($relativePath);

        if (!file_exists($fullPath)) {
            throw new Exception("Fichier non trouvé: {$relativePath}");
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new Exception("Impossible de lire le fichier");
        }

        return $content;
    }

    /**
     * Supprime un fichier du sandbox
     * @param string $relativePath Chemin relatif
     * @return bool Succès
     */
    public function deleteFile(string $relativePath): bool {
        $fullPath = $this->resolvePath($relativePath);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Liste les fichiers d'un dossier
     * @param string $relativePath Chemin relatif du dossier
     * @return array Liste des fichiers
     */
    public function listFiles(string $relativePath = ''): array {
        $fullPath = $this->resolvePath($relativePath);

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = [];
        $iterator = new DirectoryIterator($fullPath);

        foreach ($iterator as $file) {
            if ($file->isDot()) {
                continue;
            }

            $relPath = str_replace($this->basePath . '/', '', $file->getPathname());
            
            $files[] = [
                'name' => $file->getFilename(),
                'path' => $relPath,
                'is_dir' => $file->isDir(),
                'size' => $file->isFile() ? $file->getSize() : 0,
                'modified' => date('Y-m-d H:i:s', $file->getMTime())
            ];
        }

        return $files;
    }

    /**
     * Résout un chemin relatif en chemin absolu
     * @param string $relativePath Chemin relatif
     * @return string Chemin absolu
     */
    private function resolvePath(string $relativePath): string {
        $relativePath = $this->sanitizePath($relativePath);
        return $this->basePath . '/' . $relativePath;
    }

    /**
     * Détecte le MIME type d'un fichier
     * @param string $fullPath Chemin complet
     * @param string $filename Nom du fichier
     * @return string MIME type
     */
    private function detectMimeType(string $fullPath, string $filename): string {
        if (function_exists('mime_content_type')) {
            return mime_content_type($fullPath);
        }

        // Fallback basé sur l'extension
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            'php' => 'application/x-php',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'sql' => 'application/sql',
            'py' => 'text/x-python',
            'log' => 'text/plain',
            'yaml' => 'application/x-yaml',
            'yml' => 'application/x-yaml'
        ];

        return $mimeTypes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Enregistre un fichier en base de données
     * @param string $fileId ID du fichier
     * @param string $relativePath Chemin relatif
     * @param string $filename Nom du fichier
     * @param int $size Taille
     * @param string $mimeType MIME type
     * @param string $checksum Checksum SHA256
     * @param string|null $taskId ID de la tâche
     */
    private function registerFile(
        string $fileId,
        string $relativePath,
        string $filename,
        int $size,
        string $mimeType,
        string $checksum,
        ?string $taskId
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO " . DB_PREFIX . "sandbox_files 
                (id, thread_id, task_id, filepath, filename, size, mime_type, checksum, created_at)
                VALUES (:id, :thread_id, :task_id, :filepath, :filename, :size, :mime, :checksum, datetime('now'))
            ");
            
            $stmt->execute([
                ':id' => $fileId,
                ':thread_id' => $this->threadId,
                ':task_id' => $taskId,
                ':filepath' => $relativePath,
                ':filename' => $filename,
                ':size' => $size,
                ':mime' => $mimeType,
                ':checksum' => $checksum
            ]);
        } catch (Exception $e) {
            error_log("Failed to register file in database: " . $e->getMessage());
        }
    }

    /**
     * Génère une URL pour accéder au fichier
     * @param string $relativePath Chemin relatif
     * @return string URL
     */
    private function getFileUrl(string $relativePath): string {
        return BASE_URL . '/sandbox.php?thread=' . urlencode($this->threadId) . '&file=' . urlencode($relativePath);
    }

    /**
     * Nettoie le sandbox d'un thread
     * @return array Statistiques de nettoyage
     */
    public function cleanup(): array {
        $stats = [
            'files_deleted' => 0,
            'space_freed' => 0
        ];

        try {
            if (is_dir($this->basePath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->basePath),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $size = $file->getSize();
                        if (unlink($file->getPathname())) {
                            $stats['files_deleted']++;
                            $stats['space_freed'] += $size;
                        }
                    } elseif ($file->isDir() && !$file->isDot()) {
                        rmdir($file->getPathname());
                    }
                }

                // Supprimer le dossier principal s'il est vide
                @rmdir($this->basePath);
            }
        } catch (Exception $e) {
            error_log("Sandbox cleanup error: " . $e->getMessage());
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Récupère les informations d'un fichier
     * @param string $relativePath Chemin relatif
     * @return array|null Informations ou null si inexistant
     */
    public function getFileInfo(string $relativePath): ?array {
        $fullPath = $this->resolvePath($relativePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        return [
            'path' => $relativePath,
            'full_path' => $fullPath,
            'filename' => basename($fullPath),
            'size' => filesize($fullPath),
            'is_readable' => is_readable($fullPath),
            'is_writable' => is_writable($fullPath),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            'created' => date('Y-m-d H:i:s', filectime($fullPath))
        ];
    }

    /**
     * Copie un fichier dans le sandbox
     * @param string $sourcePath Chemin source (externe)
     * @param string $destPath Chemin de destination (relatif)
     * @param string|null $taskId ID de la tâche
     * @return array Informations sur le fichier copié
     */
    public function copyFile(string $sourcePath, string $destPath, ?string $taskId = null): array {
        if (!file_exists($sourcePath)) {
            throw new Exception("Fichier source inexistant: {$sourcePath}");
        }

        $content = file_get_contents($sourcePath);
        
        if ($content === false) {
            throw new Exception("Impossible de lire le fichier source");
        }

        return $this->writeFile($destPath, $content, $taskId);
    }
}
