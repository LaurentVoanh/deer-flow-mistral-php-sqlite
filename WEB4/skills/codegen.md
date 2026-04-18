# Skill: Code Generation

## Description
Compétence de génération de code pour créer, modifier et optimiser du code source dans divers langages de programmation.

## Capabilities
- Génération de code complet à partir de spécifications
- Refactoring et optimisation de code existant
- Création de fonctions, classes et modules
- Debugging et correction de bugs
- Documentation automatique de code
- Tests unitaires et d'intégration

## Modèle Mistral Recommandé
- **Principal:** `codestral-2508` (spécialisé code - 50k tokens)
- **Secondaire:** `devstral-2512` (architecture - 50k tokens)
- **Gros contexte:** `mistral-small-2603` (375k tokens pour gros fichiers)

## Instructions pour l'Agent

### Quand utiliser ce skill
- L'utilisateur demande de créer du code
- Besoin de modifier ou améliorer du code existant
- Génération de tests automatisés
- Création de documentation technique
- Optimisation de performances

### Méthodologie

1. **Analyse des besoins**
   - Comprendre les spécifications fonctionnelles
   - Identifier le langage et le framework
   - Déterminer les contraintes techniques

2. **Conception**
   - Définir l'architecture
   - Planifier la structure du code
   - Anticiper les cas limites

3. **Implémentation**
   - Écrire le code propre et maintenable
   - Suivre les best practices du langage
   - Ajouter des commentaires pertinents

4. **Validation**
   - Vérifier la syntaxe
   - Tester mentalement l'exécution
   - S'assurer de la sécurité

### Format de Sortie

```markdown
# Code Généré : [Nom du module/fonction]

## Langage
[PHP/JavaScript/Python/etc.]

## Description
[Brève description de ce que fait le code]

## Code

```php
// Code complet et fonctionnel
```

## Utilisation

```php
// Exemple d'utilisation
```

## Notes
- Points importants
- Prérequis
- Limitations connues
```

## Paramètres API Mistral Recommandés
```php
$model = 'codestral-2508'; // Spécialisé code
$temperature = 0.2; // Faible pour précision
$max_tokens = 16384; // Generous pour code complet
```

## Exemples d'Usage

### Exemple 1: Création de fonction PHP
```
Tâche: "Crée une fonction PHP pour valider un email avec vérification DNS"

Réponse attendue:
- Fonction complète avec validation syntaxique
- Vérification MX record
- Gestion des erreurs
- Exemples d'utilisation
```

### Exemple 2: Classe Laravel
```
Tâche: "Génère un Model Laravel avec relations pour un système de blog"

Réponse attendue:
- Model Post avec relations User, Category, Comments
- Scopes personnalisés
- Accessors/Mutators
- Validation rules
```

### Exemple 3: API REST
```
Tâche: "Crée une API REST complète en PHP pour gérer des utilisateurs"

Réponse attendue:
- Routes définies
- Controllers avec CRUD complet
- Middleware d'authentification
- Validation des inputs
- Responses JSON formatées
```

### Exemple 4: Refactoring
```
Tâche: "Optimise cette fonction PHP pour de meilleures performances"

Réponse attendue:
- Analyse des points lents
- Code optimisé
- Explication des améliorations
- Benchmarks si possible
```

## Bonnes Pratiques de Code

### PHP
- Respecter PSR-12
- Typage strict quand possible
- Gestion appropriée des exceptions
- Documentation PHPDoc

### JavaScript
- ES6+ moderne
- Gestion des promesses/async-await
- Validation des types avec TypeScript si pertinent

### Sécurité
- Échapper les outputs
- Valider les inputs
- Utiliser les prepared statements
- Hasher les mots de passe
- CSRF protection

## Notes Importantes
- Toujours générer du code testable
- Inclure la gestion d'erreurs
- Commenter le code complexe
- Suivre les conventions du langage
- Utiliser `codestral-2508` en priorité pour le code
- Passer à `mistral-small-2603` si contexte > 50k tokens
- Respecter le rate limit: 1 requête/seconde

## Intégration DeerFlow PHP

```php
// Exemple d'appel dans subagent.php
$response = $mistralClient->chat([
    'model' => 'codestral-2508',
    'messages' => [
        ['role' => 'system', 'content' => 'Tu es un expert développeur PHP. Tu génères du code propre, sécurisé et bien documenté.'],
        ['role' => 'user', 'content' => $codeRequest]
    ],
    'temperature' => 0.2,
    'max_tokens' => 16384
]);

// Extraire le code de la réponse
preg_match('/```(?:php)?\s*(.*?)```/s', $response['content'], $matches);
$generatedCode = $matches[1] ?? '';
```
