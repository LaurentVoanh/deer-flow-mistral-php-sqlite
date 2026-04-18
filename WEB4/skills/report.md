# Skill: Report Generation

## Description
Compétence de génération de rapports structurés et professionnels pour synthétiser des informations complexes.

## Capabilities
- Synthèse de données multiples
- Création de documents structurés
- Génération de résumés exécutifs
- Mise en forme professionnelle
- Adaptation au public cible

## Modèle Mistral Recommandé
- **Principal:** `mistral-medium-2505` ou `mistral-medium-2508` (documentation - 375k tokens)
- **Secondaire:** `mistral-small-2603` (contexte large - 375k tokens)
- **Rapide:** `ministral-14b-2512` (rapports simples - 50k tokens)

## Instructions pour l'Agent

### Quand utiliser ce skill
- L'utilisateur demande un rapport complet
- Synthèse d'analyse ou de recherche
- Documentation technique
- Compte-rendu de réunion
- Rapport d'activité

### Méthodologie

1. **Collecte des informations**
   - Rassembler toutes les données pertinentes
   - Identifier les points clés
   - Déterminer la structure appropriée

2. **Organisation**
   - Structurer logiquement le contenu
   - Hiérarchiser les informations
   - Créer des sections claires

3. **Rédaction**
   - Utiliser un ton professionnel
   - Être concis et précis
   - Inclure des exemples si pertinent

4. **Relecture**
   - Vérifier la cohérence
   - Corriger les erreurs
   - S'assurer de la complétude

### Format de Sortie Standard

```markdown
# [Titre du Rapport]

**Date:** [Date]
**Auteur:** DeerFlow AI
**Destinataire:** [Public cible]

## Résumé Exécutif
[Synthèse en 5-10 lignes des points principaux]

## Table des Matières
1. [Section 1](#section-1)
2. [Section 2](#section-2)
...

## 1. Introduction
[Contexte et objectifs]

## 2. [Section Principale 1]
[Contenu détaillé]

### 2.1 Sous-section
[Contenu]

## 3. [Section Principale 2]
[Contenu détaillé]

## 4. Analyse et Résultats
[Données, chiffres, constats]

## 5. Recommandations
- Recommandation 1
- Recommandation 2
- Recommandation 3

## 6. Conclusion
[Synthèse finale et perspectives]

## Annexes
- Annexe A: [Description]
- Annexe B: [Description]

## Sources
1. [Source 1]
2. [Source 2]
```

## Paramètres API Mistral Recommandés
```php
$model = 'mistral-medium-2505'; // Pour documentation longue
$temperature = 0.5; // Équilibré
$max_tokens = 32768; // Pour rapports complets
```

## Types de Rapports

### 1. Rapport d'Analyse
```
Structure:
- Contexte
- Méthodologie
- Données collectées
- Analyse
- Conclusions
- Recommandations
```

### 2. Rapport Technique
```
Structure:
- Spécifications
- Architecture
- Implémentation
- Tests
- Documentation API
- Maintenance
```

### 3. Rapport d'Activité
```
Structure:
- Période couverte
- Objectifs initiaux
- Réalisations
- KPIs et métriques
- Difficultés rencontrées
- Prochaines étapes
```

### 4. Étude de Marché
```
Structure:
- Vue d'ensemble du marché
- Taille et croissance
- Concurrents
- Tendances
- Opportunités
- Risques
```

## Exemples d'Usage

### Exemple 1: Rapport d'analyse de code
```
Tâche: "Génère un rapport d'audit de sécurité pour cette application PHP"

Réponse attendue:
- Vulnérabilités identifiées
- Niveau de criticité
- Preuves de concept
- Correctifs recommandés
- Priorisation des actions
```

### Exemple 2: Synthèse documentaire
```
Tâche: "Crée un rapport de synthèse sur ces 10 articles concernant l'IA"

Réponse attendue:
- Thèmes communs
- Points de convergence/divergence
- Tendances principales
- Citations clés
- Bibliographie structurée
```

### Exemple 3: Rapport commercial
```
Tâche: "Rédige un rapport trimestriel de ventes avec analyse"

Réponse attendue:
- Chiffres clés
- Comparaison N/N-1
- Analyse par segment
- Facteurs explicatifs
- Prévisions
```

## Bonnes Pratiques de Rédaction

### Style
- Phrases courtes et claires
- Voix active privilégiée
- Terminologie cohérente
- Ton adapté au public

### Structure
- Titres hiérarchisés (H1, H2, H3)
- Paragraphes aérés
- Listes à puces pour énumérations
- Tableaux pour données comparatives

### Visuel
- Gras pour termes importants
- Italique pour emphase
- Code blocks pour extraits techniques
- Liens internes pour navigation

## Notes Importantes
- Adapter le niveau de détail au public
- Inclure toujours un résumé exécutif
- Numéroter les pages/sections
- Citer toutes les sources
- Utiliser `mistral-medium-2505` pour rapports longs
- Respecter le rate limit: 1 requête/seconde

## Intégration DeerFlow PHP

```php
// Exemple d'appel dans subagent.php
$response = $mistralClient->chat([
    'model' => 'mistral-medium-2505',
    'messages' => [
        ['role' => 'system', 'content' => 'Tu es un rédacteur professionnel de rapports. Tu produis des documents structurés, clairs et professionnels.'],
        ['role' => 'user', 'content' => $reportRequest]
    ],
    'temperature' => 0.5,
    'max_tokens' => 32768
]);
```
