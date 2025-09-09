# POST /ingredients — Créer un ingrédient

**Objectif** : Ajouter un ingrédient à la compagnie avec catégories, image (optionnelle) et quantités par emplacement.

## Headers
- Authorization: Bearer {{token}}
- Content-Type: multipart/form-data (si image) ou application/json

## Body (requis)
- `name` *(string)* : max 255, unique dans la compagnie  
- `unit` *(string)* : max 50  
- `categories` *(array, min 1)* : liste de noms de catégories (string, max 255)  
- `quantities` *(array)* : objets `{ location_id:int, quantity:numeric (>=0) }`  
- `barcode` *(string, optional)* : max 255  
- `base_quantity` *(numeric, optional)* : >= 0  
- `image` *(file, optional)* : image à uploader (max 2MB)  
- `image_url` *(string, optional)* : URL d’image déjà hébergée  

⚠️ Utiliser **soit** `image`, **soit** `image_url`, mais pas les deux.

## Règles métier
- L’ingrédient est lié à la compagnie de l’utilisateur.  
- Les catégories sont créées ou récupérées (avec format ucfirst) pour la même compagnie.  
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
  "categories": ["Basiques", "Pâtisserie"],
  "quantities": [
    { "location_id": 1, "quantity": 100 },
    { "location_id": 2, "quantity": 50 }
  ],
  "barcode": "123456789",
  "base_quantity": 1,
  "image_url": "https://cdn.exemple.com/images/farine.jpg"
}
```
