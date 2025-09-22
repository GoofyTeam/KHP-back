# BACK – Paramétrage entreprise

Gestion complète des informations publiques d'une entreprise : options de carte menus, coordonnées, horaires multi-créneaux et logo avec stockage interne.

---

## Endpoints métier

PUT /company/options → met à jour les préférences d'affichage de la carte menus (langue, URL publique, options de visibilité).
PUT /company/contact → synchronise les informations de contact et l'adresse.
PUT /company/business-hours → remplace l'ensemble des créneaux horaires sur la semaine (plusieurs services par jour, gestion des nuits).
POST /company/logo → importe ou met à jour le logo de l'entreprise via fichier uploadé ou URL distante.

---

## Options d'affichage des menus

- **open_food_facts_language** *(fr/en)* : langue utilisée pour l'autocomplétion Open Food Facts.
- **public_menu_card_url** *(slug unique)* : identifiant de la carte publique, contrôlé par l'API pour éviter les doublons.
- **show_out_of_stock_menus_on_card** *(boolean)* : affiche aussi les menus en rupture (inversé par le toggle `only_sufficient_stock`).
- **show_menu_images** *(boolean)* : active les visuels des menus (alias toggle `with_pictures`).
- **Réponse API** : renvoie également `only_sufficient_stock` et `with_pictures` pour simplifier les toggles UI.

---

## Coordonnées publiques

- **contact_name/contact_email/contact_phone** : champs optionnels nettoyés (trim, email en minuscules) et remis à `null` si vidés.
- **address_line/postal_code/city/country** : adresse formatée sur une ligne pour affichage, champs textuels optionnels.
- **Réponse API** : le payload des autres endpoints inclut systématiquement ces valeurs pour garder l'écran de paramètres synchronisé.

---

## Horaires d'ouverture

- Payload `business_hours` obligatoire : liste ou dictionnaire de créneaux `{ day_of_week|day, opens_at, closes_at, is_overnight }`.
- Validation anti-chevauchement et prise en charge des services passant minuit (`is_overnight` auto-ajusté si nécessaire).
- Chaque réponse renvoie les créneaux triés avec `sequence` pour l'ordre d'affichage quotidien.

---

## Logo

- Acceptation d'un fichier image (`jpeg`, `png`, `gif`, `webp`, `bmp`, `svg`, `heic`) ≤ 2 Mo ou d'une URL publique à importer.
- Le précédent logo est supprimé automatiquement lors d'une mise à jour réussie.
- La réponse fournit `logo_path` pour alimenter directement l'UI.

---

## GraphQL

- Le type `Company` expose désormais `public_menu_settings`, `logo_path`, les champs de contact et `businessHours`.
- Le type `CompanyBusinessHour` ajoute `sequence` pour reconstruire l'ordre des services côté client.
