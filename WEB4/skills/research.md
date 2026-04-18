# Skill: Research

## Description
Compétence de recherche et d'investigation pour collecter, analyser et synthétiser des informations.

## Capabilities
- Recherche d'informations structurées
- Analyse de sources multiples
- Vérification de faits
- Synthèse de documents
- Extraction de données clés

## Modèle Mistral Recommandé
- **Principal:** `mistral-small-2603` (contexte large - 375k tokens)
- **Secondaire:** `magistral-medium-2509` (analyse de données - 75k tokens)
- **Rapide:** `ministral-8b-2512` (recherches simples - 50k tokens)

## Instructions pour l'Agent

### Quand utiliser ce skill
- L'utilisateur demande une recherche sur un sujet
- Besoin de collecter des informations factuelles
- Analyse comparative requise
- Investigation approfondie nécessaire
- Étude de marché ou analyse concurrentielle

### Méthodologie

1. **Définir le périmètre**
   - Identifier les questions clés
   - Déterminer les sources pertinentes
   - Établir les critères de qualité

2. **Collecte**
   - Explorer les sources disponibles
   - Noter les informations pertinentes
   - Conserver les références

3. **Analyse**
   - Croiser les informations
   - Identifier les convergences/divergences
   - Évaluer la fiabilité des sources

4. **Synthèse**
   - Structurer les résultats
   - Hiérarchiser par importance
   - Formuler des conclusions claires

### Format de Sortie

```markdown
# Rapport de Recherche : [Sujet]

## Résumé Exécutif
[2-3 phrases résumant les principales découvertes]

## Points Clés
- Point 1
- Point 2
- Point 3

## Analyse Détaillée

### Sous-section 1
Contenu...

### Sous-section 2
Contenu...

## Sources
1. [Source 1]
2. [Source 2]

## Conclusion
[Conclusion principale]
```

## Paramètres API Mistral Recommandés
```php
$model = 'mistral-small-2603'; // Pour contexte large
$temperature = 0.3; // Faible pour factualité
$max_tokens = 8192;
```

## Exemples d'Usage

### Exemple 1: Recherche de marché
```
Tâche: "Fais une recherche sur le marché des véhicules électriques en Europe 2024"

Réponse attendue:
- Taille du marché
- Principaux acteurs
- Tendances de croissance
- Réglementations
- Projections futures
```

### Exemple 2: Analyse comparative
```
Tâche: "Compare les solutions PHP Laravel vs Symfony pour un projet e-commerce"

Réponse attendue:
- Critères de comparaison définis
- Avantages/inconvénients de chaque solution
- Cas d'usage recommandés
- Conclusion argumentée
```

### Exemple 3: Veille technologique
```
Tâche: "Quelles sont les dernières tendances en développement PHP en 2025?"

Réponse attendue:
- Nouvelles fonctionnalités PHP 8.3+
- Frameworks populaires
- Bonnes pratiques émergentes
- Outils de développement
```

## Notes Importantes
- Toujours citer les sources quand disponibles
- Distinguer faits et opinions
- Signaler les incertitudes
- Mettre à jour si nouvelles informations
- Utiliser `mistral-small-2603` pour les contextes > 50k tokens
- Respecter le rate limit: 1 requête/seconde

## Intégration DeerFlow PHP

```php
// Exemple d'appel dans subagent.php
$response = $mistralClient->chat([
    'model' => 'mistral-small-2603',
    'messages' => [
        ['role' => 'system', 'content' => 'Tu es un expert en recherche. Tu fournis des informations précises et sourcées.'],
        ['role' => 'user', 'content' => $taskDescription]
    ],
    'temperature' => 0.3,
    'max_tokens' => 8192
]);
```
