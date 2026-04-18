<?php
/**
 * skills.php - Chargeur et sélecteur de compétences
 * Système DeerFlow PHP - Hostinger Compatible
 * 
 * Ce fichier gère le chargement des skills (compétences) depuis les fichiers Markdown
 * et la sélection du skill approprié selon le type de tâche.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

class SkillsLoader {
    
    private array $skills = [];
    private string $skillsDir;
    
    public function __construct() {
        $this->skillsDir = __DIR__ . '/skills';
        $this->loadAllSkills();
    }
    
    /**
     * Charge tous les skills depuis le dossier skills/
     */
    private function loadAllSkills(): void {
        $skillFiles = [
            'research' => 'research.md',
            'codegen' => 'codegen.md',
            'report' => 'report.md',
            'analysis' => 'analysis.md'
        ];
        
        foreach ($skillFiles as $skillName => $fileName) {
            $filePath = $this->skillsDir . '/' . $fileName;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $this->skills[$skillName] = [
                    'name' => $skillName,
                    'content' => $content,
                    'file' => $fileName,
                    'loaded_at' => date('Y-m-d H:i:s')
                ];
            }
        }
    }
    
    /**
     * Obtient la liste des skills disponibles
     */
    public function getAvailableSkills(): array {
        return array_keys($this->skills);
    }
    
    /**
     * Obtient un skill spécifique par son nom
     */
    public function getSkill(string $skillName): ?array {
        return $this->skills[$skillName] ?? null;
    }
    
    /**
     * Sélectionne le meilleur skill selon le type de tâche
     */
    public function selectSkillForTask(string $taskType, string $taskDescription): array {
        $taskTypeLower = strtolower($taskType);
        $taskDescLower = strtolower($taskDescription);
        
        // Mapping des types de tâches vers les skills
        $skillMapping = [
            'research' => ['recherche', 'analyse', 'étude', 'investigation', 'market', 'concurrent'],
            'codegen' => ['code', 'développement', 'programmation', 'script', 'fonction', 'classe', 'api', 'php', 'javascript', 'html', 'css'],
            'report' => ['rapport', 'synthèse', 'document', 'présentation', 'compte-rendu', 'summary'],
            'analysis' => ['analyse', 'diagnostic', 'évaluation', 'audit', 'review', 'inspection']
        ];
        
        $bestSkill = 'analysis'; // Skill par défaut
        $bestScore = 0;
        
        foreach ($skillMapping as $skillName => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($taskDescLower, $keyword) !== false) {
                    $score += 2;
                }
                if (strpos($taskTypeLower, $keyword) !== false) {
                    $score += 3;
                }
            }
            
            if ($score > $bestScore && isset($this->skills[$skillName])) {
                $bestScore = $score;
                $bestSkill = $skillName;
            }
        }
        
        // Si aucun score, utiliser le skill par défaut basé sur le type
        if ($bestScore === 0) {
            if (in_array($taskTypeLower, ['code', 'development', 'dev'])) {
                $bestSkill = 'codegen';
            } elseif (in_array($taskTypeLower, ['research', 'search'])) {
                $bestSkill = 'research';
            } elseif (in_array($taskTypeLower, ['report', 'document'])) {
                $bestSkill = 'report';
            }
        }
        
        $selectedSkill = $this->skills[$bestSkill] ?? $this->skills['analysis'];
        
        return [
            'skill_name' => $bestSkill,
            'skill_content' => $selectedSkill['content'] ?? '',
            'confidence' => min(1.0, $bestScore / 5),
            'all_available' => array_keys($this->skills)
        ];
    }
    
    /**
     * Formate le prompt avec le skill sélectionné
     */
    public function formatPromptWithSkill(
        string $basePrompt,
        string $skillName,
        array $context = []
    ): string {
        $skill = $this->getSkill($skillName);
        if (!$skill) {
            return $basePrompt;
        }
        
        $formattedPrompt = "# COMPÉTENCE ACTIVE: " . strtoupper($skillName) . "\n\n";
        $formattedPrompt .= $skill['content'] . "\n\n";
        $formattedPrompt .= "---\n\n";
        $formattedPrompt .= "# TÂCHE À EXÉCUTER\n\n";
        $formattedPrompt .= $basePrompt . "\n\n";
        
        if (!empty($context)) {
            $formattedPrompt .= "# CONTEXTE SUPPLÉMENTAIRE\n\n";
            foreach ($context as $key => $value) {
                $formattedPrompt .= "**{$key}**: {$value}\n";
            }
        }
        
        return $formattedPrompt;
    }
    
    /**
     * Obtient le modèle recommandé pour un skill
     */
    public function getModelForSkill(string $skillName): string {
        $modelMapping = [
            'research' => MISTRAL_MODEL_RESEARCH,
            'codegen' => MISTRAL_MODEL_CODEGEN,
            'report' => MISTRAL_MODEL_REPORT,
            'analysis' => MISTRAL_MODEL_ANALYSIS
        ];
        
        return $modelMapping[$skillName] ?? MISTRAL_MODEL_DEFAULT;
    }
    
    /**
     * Vérifie si un skill existe
     */
    public function hasSkill(string $skillName): bool {
        return isset($this->skills[$skillName]);
    }
    
    /**
     * Recharge les skills depuis le disque
     */
    public function reloadSkills(): void {
        $this->skills = [];
        $this->loadAllSkills();
    }
    
    /**
     * Exporte les métadonnées des skills
     */
    public function exportMetadata(): array {
        $metadata = [];
        foreach ($this->skills as $name => $skill) {
            $metadata[$name] = [
                'name' => $skill['name'],
                'file' => $skill['file'],
                'content_length' => strlen($skill['content']),
                'loaded_at' => $skill['loaded_at'],
                'recommended_model' => $this->getModelForSkill($name)
            ];
        }
        return $metadata;
    }
}

// Fonctions utilitaires globales

/**
 * Crée une instance unique de SkillsLoader
 */
function getSkillsLoader(): SkillsLoader {
    static $instance = null;
    if ($instance === null) {
        $instance = new SkillsLoader();
    }
    return $instance;
}

/**
 * Sélectionne un skill pour une tâche donnée
 */
function selectSkill(string $taskType, string $taskDescription): array {
    $loader = getSkillsLoader();
    return $loader->selectSkillForTask($taskType, $taskDescription);
}

/**
 * Obtient le contenu brut d'un skill
 */
function getSkillContent(string $skillName): string {
    $loader = getSkillsLoader();
    $skill = $loader->getSkill($skillName);
    return $skill['content'] ?? '';
}

/**
 * Formatte un prompt avec un skill
 */
function formatSkillPrompt(
    string $basePrompt,
    string $skillName,
    array $context = []
): string {
    $loader = getSkillsLoader();
    return $loader->formatPromptWithSkill($basePrompt, $skillName, $context);
}

// Initialisation automatique si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    header('Content-Type: application/json');
    $loader = new SkillsLoader();
    echo json_encode([
        'status' => 'success',
        'available_skills' => $loader->getAvailableSkills(),
        'metadata' => $loader->exportMetadata()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
