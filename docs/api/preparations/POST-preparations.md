# POST /preparations

**Description**
Crée une nouvelle préparation en définissant sa fiche (nom, unité de sortie, image, catégorie) et la liste des ingrédients ou sous-préparations nécessaires avec leur quantité de référence et l'emplacement de prélèvement.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique dans l'entreprise)
- `unit` (string, requis, valeur de l'énumération `MeasurementUnit`)
- `base_quantity` (numeric, requis) — quantité produite pour une unité de préparation
- `base_unit` (string, requis, valeur de `MeasurementUnit`) — unité de mesure de la quantité produite
- `entities` (array, requis, au moins 1 entrée)
  - `entities[].id` (integer, requis) — identifiant de l'ingrédient ou de la sous-préparation utilisée
  - `entities[].type` (string, requis, `ingredient` ou `preparation`)
  - `entities[].quantity` (numeric, requis) — quantité consommée par unité produite
  - `entities[].unit` (string, requis, valeur de `MeasurementUnit`) — unité de la quantité consommée
  - `entities[].location_id` (integer, requis) — emplacement depuis lequel le stock est prélevé
- `category_id` (integer, requis) — catégorie de la préparation
- `image` (fichier image, optionnel) — exclusif avec `image_url`
- `image_url` (url, optionnel) — exclusif avec `image`

**Scénarios importants**
- Fournir à la fois `image` et `image_url` entraîne une erreur de validation 422 (`Ne fournissez pas "image" et "image_url" en même temps`).
- Les identifiants d'entités et d'emplacements doivent appartenir à la même entreprise que l'utilisateur authentifié sous peine d'erreur 404.

**Réponse**
HTTP 201

```json
{
  "message": "Préparation créée avec succès",
  "preparation": {
    "id": 42,
    "name": "Pâte à crêpe",
    "unit": "portion",
    "base_quantity": 1,
    "base_unit": "portion",
    "entities": [
      {
        "entity_type": "App\\Models\\Ingredient",
        "entity": { "id": 7, "name": "Farine" },
        "quantity": 0.250,
        "unit": "kilogram",
        "location_id": 3
      }
    ],
    "category": { "id": 5, "name": "Préparations de base" }
  }
}
```
