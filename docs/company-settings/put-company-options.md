# PUT /company/options

## Description
Met à jour les préférences d'affichage et d'exposition des menus pour l'entreprise authentifiée (langue Open Food Facts, URL publique et options d'affichage sur la carte des menus).

## Paramètres de chemin
Aucun

## Corps de la requête
- `open_food_facts_language` (string, optionnel, valeurs: `fr`, `en`) — Langue utilisée pour interroger Open Food Facts et alimenter l'enrichissement automatique des produits.
- `public_menu_card_url` (string, optionnel) — Identifiant unique de la carte des menus publique (slug alphanumérique partagé dans l'URL).
- `show_out_of_stock_menus_on_card` (boolean, optionnel) — Contrôle l'affichage des menus en rupture sur la carte publique.
- `show_menu_images` (boolean, optionnel) — Active ou désactive l'affichage des photos des menus.
- `only_sufficient_stock` (boolean, optionnel) — Raccourci d'écriture; si présent il inverse `show_out_of_stock_menus_on_card` (true signifie masquer les ruptures).
- `with_pictures` (boolean, optionnel) — Alias pratique de `show_menu_images` pour les clients qui pilotent l'interface via un toggle.

## Réponse
HTTP 200

```json
{
  "message": "Options mises à jour avec succès",
  "data": {
    "open_food_facts_language": "fr",
    "public_menu_card_url": "khp-burger",
    "show_out_of_stock_menus_on_card": false,
    "show_menu_images": true,
    "only_sufficient_stock": true,
    "with_pictures": true,
    "logo_path": "companies/demo/logo.png",
    "contact_name": "John Doe",
    "contact_email": "contact@khp.test",
    "contact_phone": "+33 6 00 00 00 00",
    "address_line": "12 rue des Saveurs",
    "postal_code": "75001",
    "city": "Paris",
    "country": "France",
    "business_hours": [
      {
        "id": 1,
        "day_of_week": 1,
        "opens_at": "09:00",
        "closes_at": "12:00",
        "is_overnight": false,
        "sequence": 1
      }
    ]
  }
}
```

### Notes
- Le tableau `business_hours` restitue l'intégralité des créneaux horaires enregistrés pour chaque jour (les champs sont détaillés dans la documentation dédiée à `/company/business-hours`).
- Les champs de contact sont renvoyés pour garder une réponse cohérente avec les autres mises à jour de profil.
