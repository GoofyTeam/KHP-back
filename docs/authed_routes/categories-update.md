# PUT /categories/{id}

**Description**
Met à jour une catégorie et ses durées de conservation.

**Paramètres de chemin**
- `id` : identifiant de la catégorie.

**Corps de la requête**
- `name` (string, optionnel)
- `shelf_lives` (object, optionnel) :
  - `fridge` (integer, requis si fourni)
  - `freezer` (integer, requis si fourni)
  - `{location_type_id}` (integer ou null, optionnel)

**Réponse**
HTTP 200

```json
{
  "message": "Catégorie mise à jour avec succès",
  "data": {
    "id": 1,
    "name": "Légumes",
    "location_types": [
      {"id": 1, "name": "Réfrigérateur", "pivot": {"shelf_life_hours": 72}}
    ]
  }
}
```
