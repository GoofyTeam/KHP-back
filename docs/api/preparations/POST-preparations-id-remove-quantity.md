# POST /preparations/{id}/remove-quantity

**Description**
Retire manuellement une quantité de préparation d'un emplacement (périmé, inventaire négatif, erreur de production).

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation concernée

**Corps de la requête**
- `location_id` (integer, requis) — emplacement à débiter
- `quantity` (numeric, requis, > 0)
- `unit` (string, optionnel, valeur de `MeasurementUnit`) — unité de saisie si différente de l'unité de la préparation

**Scénarios importants**
- Si la quantité demandée dépasse le stock disponible (après conversion), une erreur HTTP 422 avec le message `Quantity cannot be negative` est renvoyée.
- L'emplacement doit appartenir à l'entreprise de l'utilisateur, sinon une erreur 404 est levée.
- Chaque retrait journalise un mouvement de stock avec le motif "Manual Withdrawal" par défaut.

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
        "pivot": { "quantity": 10 }
      }
    ]
  }
}
```
