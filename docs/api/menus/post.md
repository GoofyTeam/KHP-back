# POST /menus

**Description**
Crée un nouveau menu pour l'entreprise de l'utilisateur avec l'intégralité de ses métadonnées (nom, type, prix, image, catégories) et la composition complète en ingrédients ou préparations. Les éléments transmis constituent la référence du menu et doivent couvrir l'ensemble des composants nécessaires à sa production.

`MenuServiceType` : PREP, DIRECT

**Paramètres de chemin**
Aucun paramètre de chemin.

**Corps de la requête**
- `name` (string, requis) — nom unique du menu au sein de l'entreprise (<= 255 caractères).
- `menu_type_id` (integer, requis) — identifiant d'un type de menu appartenant à l'entreprise. Voir [`MenuTypeController`](../menu-types/post.md) pour la gestion des types.
- `priority` (integer, optionnel) — indice de priorité dans le menu public (>= 0). À priorité égale pour un type donné, le tri se fait par ordre alphabétique du nom.
- `service_type` (string, requis) — mode de service. Valeur parmi [`MenuServiceType`](../../../app/Enums/MenuServiceType.php) (`PREP`, `DIRECT`).
- `is_returnable` (boolean, requis) — indique si le contenant doit être retourné.
- `price` (numeric, requis) — prix TTC du menu (>= 0).
- `category_ids` (array d'entiers, optionnel) — identifiants de catégories de menus appartenant à l'entreprise. Peut être vide.
- `description` (string, optionnel) — description marketing (texte libre).
- `is_a_la_carte` (boolean, optionnel) — vrai si le menu peut être commandé à l'unité (défaut : `false`).
- `image` (fichier image, optionnel) — image à téléverser (`multipart/form-data`, <= 2 Mo). Exclusif avec `image_url`.
- `image_url` (url, optionnel) — URL d'une image distante à rapatrier. Exclusif avec `image`.
- `items` (array d'objets, requis) — composition complète du menu (au moins un élément).
  - `items[].entity_id` (integer, requis) — identifiant d'un ingrédient ou d'une préparation appartenant à l'entreprise.
  - `items[].entity_type` (string, requis) — `ingredient` ou `preparation`.
  - `items[].quantity` (numeric, requis) — quantité consommée pour servir une unité du menu (>= 0.01).
  - `items[].unit` (string, requis) — unité associée à la quantité. Valeur parmi [`MeasurementUnit`](../../../app/Enums/MeasurementUnit.php).
  - `items[].location_id` (integer, requis) — identifiant d'un emplacement de stock appartenant à l'entreprise.

_La requête peut être envoyée en `application/json` ou en `multipart/form-data` lorsqu'un fichier est téléversé._

**Scénarios importants**
- Fournir simultanément `image` et `image_url` génère une erreur 422 avec un message expliquant l'exclusivité.
- Chaque couple (`entity_type`, `entity_id`) ne peut apparaître qu'une seule fois dans `items`; un doublon déclenche une erreur 422.
- Les ingrédients, préparations, catégories, emplacements et le type de menu doivent appartenir à la même entreprise que l'utilisateur. En cas d'identifiant invalide, une erreur 422 est renvoyée.
- L'absence d'au moins un élément dans `items` entraîne une erreur 422 (`min:1`).

**Réponse**
HTTP 201

```json
{
  "message": "Menu created",
  "menu": {
    "id": 42,
    "company_id": 7,
    "menu_type_id": 3,
    "name": "Menu Burger Maison",
    "description": "Pain artisanal, frites et boisson.",
    "image_url": "menus/menu-burger.jpg",
    "is_a_la_carte": false,
    "public_priority": 1,
    "type": "Plats",
    "service_type": "DIRECT",
    "is_returnable": false,
    "price": 14.9,
    "created_at": "2024-05-16T09:15:23.000000Z",
    "updated_at": "2024-05-16T09:15:23.000000Z",
    "categories": [
      {
        "id": 3,
        "name": "Formules déjeuner"
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
        "id": 130,
        "menu_id": 42,
        "entity_id": 8,
        "entity_type": "App\\Models\\Ingredient",
        "quantity": 1,
        "unit": "unit",
        "location_id": 2,
        "created_at": "2024-05-16T09:15:23.000000Z",
        "updated_at": "2024-05-16T09:15:23.000000Z",
        "entity": {
          "id": 8,
          "name": "Pain burger"
        }
      },
      {
        "id": 131,
        "menu_id": 42,
        "entity_id": 15,
        "entity_type": "App\\Models\\Preparation",
        "quantity": 0.2,
        "unit": "kg",
        "location_id": 5,
        "created_at": "2024-05-16T09:15:23.000000Z",
        "updated_at": "2024-05-16T09:15:23.000000Z",
        "entity": {
          "id": 15,
          "name": "Frites maison"
        }
      }
    ]
  }
}
```

**Exemple de requête JSON**

```http
POST /menus
Content-Type: application/json

{
  "name": "Menu Burger Maison",
  "menu_type_id": 3,
  "priority": 1,
  "service_type": "DIRECT",
  "is_returnable": false,
  "price": 14.9,
  "category_ids": [3],
  "description": "Pain artisanal, frites et boisson.",
  "items": [
    {
      "entity_id": 8,
      "entity_type": "ingredient",
      "quantity": 1,
      "unit": "unit",
      "location_id": 2
    },
    {
      "entity_id": 15,
      "entity_type": "preparation",
      "quantity": 0.2,
      "unit": "kg",
      "location_id": 5
    }
  ]
}
```

**Codes d'erreur courants**
- **401 Unauthorized** — L'utilisateur n'est pas authentifié.
- **422 Unprocessable Entity** — Validation échouée (doublon dans `items`, identifiant d'entité, d'emplacement ou de type invalide, image et URL fournies ensemble, etc.).
