# POST /ingredients/bulk

**Description**
Crée plusieurs ingrédients en une seule requête.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `ingredients` (array, requis) – chaque ingrédient contient :
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
Liste des identifiants créés.
