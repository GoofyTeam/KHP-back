# POST /ingredients/{ingredient}/add-quantity

**Description**
Ajoute une quantité d'ingrédient sur un emplacement.

**Paramètres de chemin**
- `ingredient` : identifiant de l'ingrédient.

**Corps de la requête**
- `location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
HTTP 200

```json
{
  "message": "Ingredient quantity updated successfully",
  "ingredient": {
    "id": 1,
    "name": "Tomate",
    "locations": [
      {"id": 1, "name": "Réserve", "pivot": {"quantity": 15}}
    ]
  }
}
```
