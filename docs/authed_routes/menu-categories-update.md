# PUT /menu-categories/{id}

**Description**
Met à jour une catégorie de menus.

**Paramètres de chemin**
- `id` : identifiant de la catégorie.

**Corps de la requête**
- `name` (string, optionnel)

**Réponse**
HTTP 200

```json
{
  "message": "Category updated successfully",
  "data": {
    "id": 1,
    "name": "Entrées"
  }
}
```
