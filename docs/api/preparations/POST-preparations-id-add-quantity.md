# POST /preparations/{id}/add-quantity

**Description**
Ajoute manuellement du stock disponible pour une préparation sur un emplacement donné (réception, comptage, production hors workflow automatisé).

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation concernée

**Corps de la requête**
- `location_id` (integer, requis) — emplacement sur lequel ajouter le stock
- `quantity` (numeric, requis, > 0)
- `unit` (string, optionnel, valeur de `MeasurementUnit`) — unité de la quantité saisie si différente de l'unité de la préparation

**Scénarios importants**
- Lorsque `unit` est fourni et diffère de l'unité de référence de la préparation, la quantité est automatiquement convertie avant l'ajout.
- En cas d'emplacement inexistant ou appartenant à une autre entreprise, une erreur 404 est renvoyée.
- Chaque ajout crée un mouvement de stock avec le motif "Manual Addition" ou celui passé au service si personnalisé.

**Réponse**
HTTP 200

```json
{
  "message": "Quantité de la préparation mise à jour avec succès",
  "preparation": {
    "id": 42,
    "locations": [
      {
        "id": 3,
        "pivot": { "quantity": 28 }
      }
    ]
  }
}
```
