# PUT /menus/{id}

**Description**
Met à jour un menu existant en remplaçant l'ensemble de ses informations (métadonnées, catégories, composition). La requête attend la version complète du menu : les éléments absents sont supprimés et seuls les champs fournis sont recalculés.

`MenuServiceType` : PREP, DIRECT

**Paramètres de chemin**
- `id` (integer, requis) — identifiant du menu à modifier. Le menu doit appartenir à l'entreprise de l'utilisateur.

**Corps de la requête**
- `name` (string, optionnel) — nouveau nom unique du menu (<= 255 caractères).
- `menu_type_id` (integer, optionnel) — type de menu à associer. Doit appartenir à l'entreprise de l'utilisateur.
- `priority` (integer, optionnel) — nouvelle priorité publique du menu (>= 0). À priorité égale pour un type donné, le tri reste alphabétique.
- `service_type` (string, optionnel) — mode de service. Valeur parmi [`MenuServiceType`](../../../app/Enums/MenuServiceType.php).
- `is_returnable` (boolean, optionnel) — indique si le contenant doit être retourné.
- `price` (numeric, optionnel) — prix TTC du menu (>= 0).
- `category_ids` (array d'entiers, optionnel) — liste complète des catégories à associer. Leur absence laisse les catégories inchangées.
- `description` (string, optionnel) — description marketing.
- `is_a_la_carte` (boolean, optionnel) — disponibilité à la carte.
- `image` (fichier image, optionnel) — nouvelle image à téléverser (`multipart/form-data`, <= 2 Mo). Exclusif avec `image_url`.
- `image_url` (url, optionnel) — URL d'une image distante à rapatrier. Exclusif avec `image`.
- `items` (array d'objets, requis) — composition complète remplaçant la précédente.
  - `items[].entity_id` (integer, requis) — identifiant d'un ingrédient ou d'une préparation appartenant à l'entreprise.
  - `items[].entity_type` (string, requis) — `ingredient` ou `preparation`.
  - `items[].quantity` (numeric, requis) — quantité nécessaire pour une unité (>= 0.01).
  - `items[].unit` (string, requis) — unité de la quantité. Valeur parmi [`MeasurementUnit`](../../../app/Enums/MeasurementUnit.php).
  - `items[].location_id` (integer, requis) — identifiant d'un emplacement de stock appartenant à l'entreprise.

**Scénarios importants**
- `items` doit contenir l'intégralité de la nouvelle composition : les éléments non transmis sont supprimés du menu.
- Fournir `image` et `image_url` simultanément renvoie une erreur 422.
- Les couples (`entity_type`, `entity_id`) doivent être uniques. Un doublon déclenche une erreur 422.
- Ingrédients, préparations, catégories, emplacements et type de menu doivent appartenir à l'entreprise de l'utilisateur. Des identifiants invalides renvoient une erreur 422.
- Mettre à jour `category_ids` remplace entièrement les associations existantes (synchronisation).

**Réponse**
HTTP 200

```json
{
  "message": "Menu updated",
  "menu": {
    "id": 42,
    "company_id": 7,
    "menu_type_id": 3,
    "name": "Menu Burger Maison",
    "description": "Pain artisanal, frites et boisson.",
    "image_url": "menus/menu-burger-v2.jpg",
    "is_a_la_carte": true,
    "public_priority": 2,
    "type": "Plats",
    "service_type": "PREP",
    "is_returnable": false,
    "price": 15.5,
    "created_at": "2024-05-01T10:02:11.000000Z",
    "updated_at": "2024-05-17T08:45:00.000000Z",
    "categories": [
      {
        "id": 5,
        "name": "Cartes du soir"
      }
    ],
    "menu_type": {
      "id": 3,
      "company_id": 7,
      "name": "Plats",
      "public_index": 1,
      "created_at": "2024-05-10T08:00:00.000000Z",
      "updated_at": "2024-05-10T08:00:00.000000Z",
      "public_order": {
        "id": 12,
        "menu_type_id": 3,
        "company_id": 7,
        "position": 1,
        "created_at": "2024-05-10T08:00:00.000000Z",
        "updated_at": "2024-05-10T08:00:00.000000Z"
      }
    },
    "items": [
      {
        "id": 140,
        "menu_id": 42,
        "entity_id": 8,
        "entity_type": "App\\Models\\Ingredient",
        "quantity": 1,
        "unit": "unit",
        "location_id": 2,
        "created_at": "2024-05-17T08:45:00.000000Z",
        "updated_at": "2024-05-17T08:45:00.000000Z",
        "entity": {
          "id": 8,
          "name": "Pain burger"
        }
      },
      {
        "id": 141,
        "menu_id": 42,
        "entity_id": 21,
        "entity_type": "App\\Models\\Ingredient",
        "quantity": 0.15,
        "unit": "kg",
        "location_id": 5,
        "created_at": "2024-05-17T08:45:00.000000Z",
        "updated_at": "2024-05-17T08:45:00.000000Z",
        "entity": {
          "id": 21,
          "name": "Frites fraîches"
        }
      }
    ]
  }
}
```

**Exemple de requête JSON**

```http
PUT /menus/42
Content-Type: application/json

{
  "name": "Menu Burger Maison",
  "menu_type_id": 3,
  "priority": 2,
  "service_type": "PREP",
  "is_a_la_carte": true,
  "price": 15.5,
  "category_ids": [5],
  "items": [
    {
      "entity_id": 8,
      "entity_type": "ingredient",
      "quantity": 1,
      "unit": "unit",
      "location_id": 2
    },
    {
      "entity_id": 21,
      "entity_type": "ingredient",
      "quantity": 0.15,
      "unit": "kg",
      "location_id": 5
    }
  ]
}
```

**Codes d'erreur courants**
- **401 Unauthorized** — L'utilisateur n'est pas authentifié.
- **404 Not Found** — Menu inexistant ou n'appartenant pas à l'entreprise de l'utilisateur.
- **422 Unprocessable Entity** — Validation échouée (doublons d'items, entité, emplacement ou type invalide, image et URL fournies ensemble, etc.).
