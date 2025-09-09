# POST /ingredients — Créer un ingrédient

**Objectif** : Ajouter un ingrédient à la compagnie avec catégorie, allergènes, image (optionnelle) et quantités par emplacement.

## Headers
- Authorization: Bearer {{token}}
- Content-Type: multipart/form-data (si image) ou application/json

## Body
- `name` *(string, requis)* : max 255, unique dans la compagnie
- `unit` *(string, requis)* : valeur parmi l’énum `MeasurementUnit`
- `category_id` *(int, requis)* : identifiant d’une catégorie existante de la compagnie
- `quantities` *(array, requis)* : objets `{ location_id:int, quantity:numeric (>=0) }`
- `barcode` *(string, optionnel)* : max 255
- `base_quantity` *(numeric, requis)* : >= 0
- `base_unit` *(string, requis)* : valeur parmi `MeasurementUnit`
- `allergens` *(array<string>, optionnel)* : valeurs parmi l’énum `Allergen`
- `image` *(file, optionnel)* : image à uploader (max 2MB)
- `image_url` *(string, optionnel)* : URL d’image déjà hébergée

⚠️ Utiliser **soit** `image`, **soit** `image_url`, mais pas les deux. Si aucune n’est fournie, une image générique est utilisée.

## Règles métier
- L’ingrédient est lié à la compagnie de l’utilisateur.
- `category_id` doit correspondre à une catégorie de la même compagnie.
- Les allergènes doivent appartenir à l’énum `Allergen` et se propagent aux préparations et menus qui utilisent l’ingrédient.
- Les quantités sont synchronisées par emplacement (création ou update).
- `name` doit être unique dans la compagnie.

## Réponses
- `201` : créé (`ingredient_id`)  
- `422` : validation échouée  
- `401` : non authentifié

## Exemple (JSON)
```json
POST /ingredients
{
  "name": "Farine",
  "unit": "kg",
  "category_id": 3,
  "allergens": ["gluten"],
  "quantities": [
    { "location_id": 1, "quantity": 100 },
    { "location_id": 2, "quantity": 50 }
  ],
  "barcode": "123456789",
  "base_quantity": 1,
  "base_unit": "kg",
  "image_url": "https://cdn.exemple.com/images/farine.jpg"
}
```
