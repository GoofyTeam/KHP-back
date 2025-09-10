# POST /menu-categories

**Description**
Crée une catégorie de menus.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)

**Réponse**
HTTP 201

```json
{
  "message": "Category created successfully",
  "data": {
    "id": 1,
    "name": "Entrées"
  }
}
```
