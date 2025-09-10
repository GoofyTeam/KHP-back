# POST /preparations/{id}/remove-quantity

**Description**
Retire une quantité de préparation d'un emplacement.

**Paramètres de chemin**
- `id` : identifiant de la préparation.

**Corps de la requête**
- `location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
HTTP 200

```json
{
  "message": "Quantité de la préparation mise à jour avec succès",
  "preparation": {
    "id": 1,
    "name": "Sauce tomate"
  }
}
```
