# POST /menus

**Description**
Crée un nouveau menu avec ses éléments.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `type` (string, requis)
- `price` (numeric, requis)
- `category_ids` (array d'entiers, optionnel)
- `description` (string, optionnel)
- `is_a_la_carte` (boolean, optionnel)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `items` (array, requis) : `{entity_id, entity_type, quantity, unit, location_id}`

**Réponse**
Message et menu créé.
