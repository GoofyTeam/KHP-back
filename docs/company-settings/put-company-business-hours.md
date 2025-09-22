# PUT /company/business-hours

## Description
Synchronise l'ensemble des créneaux horaires de la semaine pour l'entreprise connectée (plusieurs services par jour, gestion des nuitées après minuit et validation anti-chevauchement).

## Paramètres de chemin
Aucun

## Corps de la requête
- `business_hours` (array, requis) — Tableau ou dictionnaire décrivant chaque créneau.
  - `day_of_week` (integer 1-7, requis) — Jour numérique (1 = lundi … 7 = dimanche). Les valeurs textuelles `monday`, `mon`, `lundi`, etc. sont également acceptées via `day` ou `day_of_week`.
  - `opens_at` (string, requis) — Heure d'ouverture au format `HH:MM` 24h.
  - `closes_at` (string, requis) — Heure de fermeture au format `HH:MM` 24h. Doit être différente de `opens_at`.
  - `is_overnight` (boolean, optionnel) — À `true` pour préciser qu'un service se termine le jour suivant (activé automatiquement si `closes_at` < `opens_at`).

## Contraintes métier
- Tous les créneaux sont remplacés : envoyez l'intégralité de la semaine à chaque mise à jour.
- Aucun chevauchement n'est autorisé ni dans la journée ni entre dimanche et lundi (validation automatique côté API).
- Vous pouvez envoyer plusieurs créneaux pour un même jour afin de représenter des coupures de service.

## Réponse
HTTP 200

```json
{
  "message": "Horaires mis à jour avec succès",
  "data": {
    "business_hours": [
      {
        "id": 10,
        "day_of_week": 1,
        "opens_at": "09:00",
        "closes_at": "12:00",
        "is_overnight": false,
        "sequence": 1
      },
      {
        "id": 11,
        "day_of_week": 1,
        "opens_at": "18:00",
        "closes_at": "02:00",
        "is_overnight": true,
        "sequence": 2
      }
    ],
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
    "country": "France"
  }
}
```

### Notes
- Le champ `sequence` correspond à l'ordre du créneau dans la journée (1 pour le premier service, 2 pour le second, etc.).
- Les créneaux renvoyés sont déjà triés par jour puis par ordre de service pour faciliter l'affichage.
