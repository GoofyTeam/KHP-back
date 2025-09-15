# POST /preparations/{id}/move-quantity

**Description**
Déplace une quantité de préparation d'un emplacement source vers un emplacement destination tout en retraçant les mouvements et en conservant les lots périssables pour les ingrédients.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation concernée

**Corps de la requête**
- `from_location_id` (integer, requis) — emplacement à débiter
- `to_location_id` (integer, requis, différent de `from_location_id`) — emplacement à créditer
- `quantity` (numeric, requis, > 0)
- `unit` (string, optionnel, valeur de `MeasurementUnit`) — unité saisie si différente de celle de la préparation

**Scénarios importants**
- Si le stock disponible sur l'emplacement source est insuffisant (après conversion d'unité), une erreur HTTP 422 avec le message `Quantity cannot be negative` est renvoyée et aucun mouvement n'est enregistré.
- Les deux emplacements doivent appartenir à l'entreprise de l'utilisateur sinon une erreur 404 est renvoyée.
- Deux mouvements sont journalisés : un pour le retrait sur la source et un pour l'ajout sur la destination avec le motif "Moved from {source} to {destination}".

**Réponse**
HTTP 200

```json
{
  "message": "Quantité de la préparation déplacée avec succès",
  "preparation": {
    "id": 42,
    "locations": [
      {
        "id": 2,
        "pivot": { "quantity": 4 }
      },
      {
        "id": 5,
        "pivot": { "quantity": 9 }
      }
    ]
  }
}
```
