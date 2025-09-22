# PUT /company/contact

## Description
Met à jour les coordonnées publiques de l'entreprise (personne référente, moyens de contact et adresse affichée sur la carte des menus).

## Paramètres de chemin
Aucun

## Corps de la requête
- `contact_name` (string, optionnel) — Nom de la personne à afficher pour les contacts (trim automatique, champ vidé → valeur `null`).
- `contact_email` (string, optionnel) — Adresse e-mail publique (normalisée en minuscules, champ vidé → valeur `null`).
- `contact_phone` (string, optionnel) — Numéro de téléphone ou WhatsApp visible par les clients.
- `address_line` (string, optionnel) — Adresse textuelle (une seule ligne pour affichage).
- `postal_code` (string, optionnel) — Code postal du restaurant.
- `city` (string, optionnel) — Ville affichée aux clients.
- `country` (string, optionnel) — Pays affiché aux clients.

## Réponse
HTTP 200

```json
{
  "message": "Coordonnées mises à jour avec succès",
  "data": {
    "contact_name": "John Doe",
    "contact_email": "contact@khp.test",
    "contact_phone": "+33 6 00 00 00 00",
    "address_line": "12 rue des Saveurs",
    "postal_code": "75001",
    "city": "Paris",
    "country": "France",
    "open_food_facts_language": "fr",
    "public_menu_card_url": "khp-burger",
    "show_out_of_stock_menus_on_card": false,
    "show_menu_images": true,
    "only_sufficient_stock": true,
    "with_pictures": true,
    "logo_path": "companies/demo/logo.png",
    "business_hours": []
  }
}
```

### Notes
- Les champs absents ou fournis vides sont enregistrés à `null` afin de pouvoir effacer une information.
- La réponse renvoie également les autres réglages de l'entreprise pour rester cohérente avec les endpoints `/company/options`, `/company/business-hours` et `/company/logo`.
