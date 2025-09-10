# PUT /preparations/{id}

**Description**
Met à jour une préparation existante.

**Paramètres de chemin**
- `id` : identifiant de la préparation.

**Corps de la requête**
- `name` (string, optionnel)
- `unit` (string, optionnel)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `entities_to_remove` (array d'objets `{id, type}`, optionnel)
- `entities_to_add` (array d'objets `{id, type}`, optionnel)
- `category_id` (integer, optionnel)
- `quantities` (array `{location_id, quantity}`, optionnel)

**Réponse**
HTTP 200

```json
{
  "message": "Préparation mise à jour avec succès",
  "preparation": {
    "id": 1,
    "name": "Sauce tomate"
  }
}
```
