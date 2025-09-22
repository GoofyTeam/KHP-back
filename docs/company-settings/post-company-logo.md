# POST /company/logo

## Description
Met à jour le logo de l'entreprise en acceptant soit un fichier uploadé soit une URL distante à rapatrier sur le stockage interne.

## Paramètres de chemin
Aucun

## Corps de la requête
- `image` (file, optionnel mais requis si `image_url` absent) — Fichier image accepté (`jpeg`, `jpg`, `png`, `gif`, `webp`, `bmp`, `svg`, `heic`) jusqu'à 2 Mo.
- `image_url` (string, optionnel mais requis si `image` absent) — URL publique à partir de laquelle récupérer le logo.

## Règles métier
- Un seul des deux champs est nécessaire; l'API renverra une erreur si les deux sont manquants.
- Lorsqu'un nouveau logo est validé, l'ancien est supprimé du stockage.

## Réponse
HTTP 200

```json
{
  "message": "Image de l'entreprise mise à jour avec succès",
  "data": {
    "logo_path": "companies/demo/logo.png",
    "open_food_facts_language": "fr",
    "public_menu_card_url": "khp-burger",
    "show_out_of_stock_menus_on_card": false,
    "show_menu_images": true,
    "only_sufficient_stock": true,
    "with_pictures": true,
    "contact_name": "John Doe",
    "contact_email": "contact@khp.test",
    "contact_phone": "+33 6 00 00 00 00",
    "address_line": "12 rue des Saveurs",
    "postal_code": "75001",
    "city": "Paris",
    "country": "France",
    "business_hours": []
  }
}
```

### Notes
- Le champ `logo_path` pointe vers le chemin interne du fichier à utiliser côté front.
- Toutes les autres propriétés de profil sont renvoyées pour garder la synchro avec l'interface de paramétrage.
