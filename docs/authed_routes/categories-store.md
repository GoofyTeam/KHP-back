# POST /categories

**Description**
Crée une nouvelle catégorie avec ses durées de conservation.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `shelf_lives` (object, requis) :
  - `fridge` (integer, requis)
  - `freezer` (integer, requis)

**Réponse**
HTTP 201

```json
{
  "message": "Catégorie créée avec succès",
  "data": {
    "id": 1,
    "name": "Légumes",
    "location_types": [
      {"id": 1, "name": "Réfrigérateur", "pivot": {"shelf_life_hours": 72}},
      {"id": 2, "name": "Congélateur", "pivot": {"shelf_life_hours": 720}}
    ]
  }
}
```
