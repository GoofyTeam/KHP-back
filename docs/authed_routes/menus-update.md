# PUT /menus/{id}

**Description**
Met à jour un menu existant.

**Paramètres de chemin**
- `id` : identifiant du menu.

**Corps de la requête**
- `name` (string, optionnel)
- `type` (string, optionnel)
- `price` (numeric, optionnel)
- `category_ids_to_add` (array d'entiers, optionnel)
- `category_ids_to_remove` (array d'entiers, optionnel)
- `description` (string, optionnel)
- `is_a_la_carte` (boolean, optionnel)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `items_to_add` (array `{entity_id, entity_type, quantity, unit, location_id}`, optionnel)
- `items_to_remove` (array `{entity_id, entity_type}`, optionnel)
- `items_to_update` (array `{entity_id, entity_type, quantity, unit?, location_id?}`, optionnel)

**Réponse**
Message et menu mis à jour.
