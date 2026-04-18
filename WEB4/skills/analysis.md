# Skill: Analysis

## Description
Compétence d'analyse critique pour examiner, évaluer et interpréter des données, situations ou problèmes complexes.

## Capabilities
- Analyse de données structurées et non structurées
- Évaluation critique d'arguments
- Identification de patterns et tendances
- Diagnostic de problèmes
- Comparaison et benchmarking
- SWOT analysis

## Modèle Mistral Recommandé
- **Principal:** `magistral-medium-2509` (analyse de données - 75k tokens)
- **Secondaire:** `mistral-large-2512` (raisonnement complexe - 50k tokens)
- **Rapide:** `magistral-small-2509` (analyse légère - 75k tokens)

## Instructions pour l'Agent

### Quand utiliser ce skill
- L'utilisateur demande une analyse approfondie
- Besoin d'évaluer des options multiples
- Diagnostic de situation problématique
- Interprétation de données complexes
- Validation d'hypothèses

### Méthodologie

1. **Compréhension du contexte**
   - Définir le périmètre d'analyse
   - Identifier les parties prenantes
   - Clarifier les objectifs

2. **Collecte et préparation**
   - Rassembler les données pertinentes
   - Nettoyer et organiser les informations
   - Vérifier la qualité des données

3. **Analyse**
   - Appliquer les méthodes appropriées
   - Identifier les corrélations et causalités
   - Tester les hypothèses

4. **Interprétation**
   - Donner du sens aux résultats
   - Contextualiser les findings
   - Identifier les implications

5. **Recommandations**
   - Formuler des conclusions actionnables
   - Prioriser les actions
   - Anticiper les risques

### Formats d'Analyse

#### 1. Analyse SWOT
```markdown
## Forces (Strengths)
- ...

## Faiblesses (Weaknesses)
- ...

## Opportunités (Opportunities)
- ...

## Menaces (Threats)
- ...
```

#### 2. Analyse Comparative
```markdown
## Critères d'évaluation
1. Critère 1
2. Critère 2

## Option A
- Score: X/10
- Avantages: ...
- Inconvénients: ...

## Option B
- Score: Y/10
- Avantages: ...
- Inconvénients: ...

## Recommandation
[Option recommandée avec justification]
```

#### 3. Analyse de Causes Racines (5 Why)
```markdown
## Problème initial
[Description]

## Pourquoi 1?
[Réponse]

## Pourquoi 2?
[Réponse]

...

## Cause racine identifiée
[Conclusion]
```

#### 4. Analyse de Données
```markdown
## Vue d'ensemble
- Volume de données: ...
- Période analysée: ...

## Tendances principales
1. ...
2. ...

## Insights clés
- ...

## Anomalies détectées
- ...
```

## Paramètres API Mistral Recommandés
```php
$model = 'magistral-medium-2509'; // Pour analyse de données
$temperature = 0.4; // Équilibré entre créativité et rigueur
$max_tokens = 16384;
```

## Exemples d'Usage

### Exemple 1: Analyse de marché
```
Tâche: "Analyse le marché français des solutions SaaS pour PME"

Réponse attendue:
- Taille du marché et croissance
- Principaux acteurs et parts de marché
- Tendances émergentes
- Barrières à l'entrée
- Opportunités non exploitées
- Recommandations stratégiques
```

### Exemple 2: Audit de code
```
Tâche: "Analyse ce code PHP et identifie les problèmes potentiels"

Réponse attendue:
- Problèmes de sécurité identifiés
- Issues de performance
- Violations de best practices
- Dette technique estimée
- Plan de remediation priorisé
```

### Exemple 3: Analyse financière
```
Tâche: "Analyse la santé financière de cette entreprise sur 3 ans"

Réponse attendue:
- Évolution du CA et marges
- Ratios financiers clés
- Points forts et faibles
- Comparaison sectorielle
- Risques identifiés
- Prévisions
```

### Exemple 4: Analyse UX
```
Tâche: "Analyse l'expérience utilisateur de cette application web"

Réponse attendue:
- Points de friction identifiés
- Parcours utilisateur typique
- Taux de conversion par étape
- Benchmarks industry
- Recommandations d'amélioration
- Impact estimé des changements
```

## Outils et Méthodes d'Analyse

### Quantitatif
- Statistiques descriptives
- Analyse de régression
- Segmentation
- Cohort analysis
- A/B testing analysis

### Qualitatif
- Entretiens utilisateurs
- Feedback analysis
- Sentiment analysis
- Thematic coding
- Journey mapping

### Stratégique
- SWOT
- PESTEL
- Porter's 5 Forces
- Business Model Canvas
- Value Chain Analysis

## Bonnes Pratiques d'Analyse

### Rigueur
- Toujours vérifier les sources
- Distinguer corrélation et causalité
- Quantifier quand possible
- Reconnaître les limites de l'analyse

### Communication
- Utiliser des visualisations claires
- Hiérarchiser les insights
- Être transparent sur les incertitudes
- Adapter le niveau de détail

### Actionnabilité
- Lier chaque insight à une action potentielle
- Prioriser par impact/faisabilité
- Estimer les ressources nécessaires
- Définir des KPIs de succès

## Notes Importantes
- Toujours contextualiser les résultats
- Mentionner les biais potentiels
- Fournir des preuves pour chaque affirmation
- Proposer des next steps clairs
- Utiliser `magistral-medium-2509` pour analyses complexes
- Respecter le rate limit: 1 requête/seconde

## Intégration DeerFlow PHP

```php
// Exemple d'appel dans subagent.php
$response = $mistralClient->chat([
    'model' => 'magistral-medium-2509',
    'messages' => [
        ['role' => 'system', 'content' => 'Tu es un analyste expert. Tu fournis des analyses rigoureuses, structurées et actionnables.'],
        ['role' => 'user', 'content' => $analysisRequest]
    ],
    'temperature' => 0.4,
    'max_tokens' => 16384
]);
```
