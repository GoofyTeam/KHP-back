# POST /preparations

**Description**
Crée une nouvelle préparation composée d'ingrédients et/ou de sous-préparations.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `unit` (string, requis)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `entities` (array, requis, min 2) – chaque élément : `{ id, type }`
- `category_id` (integer, requis)

**Réponse**
HTTP 201

```json
{
  "message": "Préparation créée avec succès",
  "preparation": {
    "id": 1,
    "name": "Sauce tomate",
    "unit": "liter",
    "entities": []
  }
}
```
