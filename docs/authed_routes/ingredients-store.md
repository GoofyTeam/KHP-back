# POST /ingredients

**Description**
Ajoute un ingrédient au catalogue de l'entreprise.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `unit` (string, requis)
- `image` (fichier image, optionnel)
- `image_url` (url, optionnel)
- `category_id` (integer, requis)
- `quantities` (array `{quantity, location_id}`, requis)
- `barcode` (string, optionnel)
- `base_quantity` (numeric, requis)
- `base_unit` (string, requis)
- `allergens` (array, optionnel)

**Réponse**
Message et identifiant de l'ingrédient créé.
