# PUT /ingredients/{ingredient}

**Description**
Met à jour un ingrédient existant.

**Paramètres de chemin**
- `ingredient` : identifiant de l'ingrédient.

**Corps de la requête**
- `name` (string, optionnel)
- `unit` (string, optionnel)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `category_id` (integer, optionnel)
- `quantities` (array `{quantity, location_id}`, optionnel)
- `barcode` (string, optionnel)
- `base_quantity` (numeric, optionnel)
- `base_unit` (string, optionnel)
- `allergens` (array, optionnel)

**Réponse**
Message et ingrédient mis à jour.
