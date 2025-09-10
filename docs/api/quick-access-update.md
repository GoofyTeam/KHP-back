# Quick Access — Update

- Méthode: PUT
- URL: `/quick-access`
- Nom de route: `quick-access.update`
- Authentification: Requise (Bearer token), route protégée

## Description
Met à jour, en une seule requête, soit:
- un ou plusieurs Quick Access (liste 1..4), et/ou
- le bouton spécial Quick Access

La mise à jour est partielle: seuls les champs fournis sont modifiés.

- Les enregistrements sont filtrés par `company_id` (ceux d’une autre société sont ignorés sans erreur).
- La réponse renvoie tous les Quick Access de la société, triés par `index`, ainsi que le `special_quick_access`.

## Corps de requête
Au moins un des deux blocs est requis: `quick_accesses` ou `special_quick_access`.

Bloc `quick_accesses` (array, min 1):
- `id` (required, integer, exists: `quick_accesses.id`)
- `name` (optional, string, max 255)
- `icon` (optional, string, max 255)
- `icon_color` (optional, string, enum: `primary` | `warning` | `error` | `info`)
- `url` (optional, string, max 255)

Bloc `special_quick_access` (object):
- `name` (optional, string, max 255)
- `url` (optional, string, max 255)

Exemple (mettre à jour certains Quick Access et le bouton spécial):
```json
{
  "quick_accesses": [
    { "id": 12, "name": "Stock", "icon": "Check", "icon_color": "primary", "url": "/stock" },
    { "id": 13, "name": "Menu Card", "icon": "Notebook", "icon_color": "info" }
  ],
  "special_quick_access": { "name": "Move Quantity", "url": "/movequantity" }
}
```

Exemple (mettre à jour uniquement le bouton spécial):
```json
{
  "special_quick_access": { "name": "Move Qty", "url": "/movequantity" }
}
```

## Réponses
- 200 OK: Retourne tous les Quick Access de la société (triés par `index`) et le `special_quick_access`.
- 401 Unauthorized: Token manquant/invalid.
- 422 Unprocessable Content: Erreurs de validation.

Exemple 200:
```json
{
  "message": "Quick accesses updated",
  "quick_accesses": [
    {
      "id": 12,
      "company_id": 3,
      "index": 1,
      "name": "Add to stock",
      "icon": "Plus",
      "icon_color": "primary",
      "url": "/stock/add",
      "created_at": "2025-09-08T10:00:00Z",
      "updated_at": "2025-09-10T12:34:56Z"
    },
    {
      "id": 13,
      "company_id": 3,
      "index": 2,
      "name": "Menu Card",
      "icon": "Notebook",
      "icon_color": "info",
      "url": "/menucard",
      "created_at": "2025-09-08T10:00:00Z",
      "updated_at": "2025-09-10T12:34:56Z"
    },
    {
      "id": 14,
      "company_id": 3,
      "index": 3,
      "name": "Stock",
      "icon": "Check",
      "icon_color": "primary",
      "url": "/stock",
      "created_at": "2025-09-08T10:00:00Z",
      "updated_at": "2025-09-10T12:34:56Z"
    },
    {
      "id": 15,
      "company_id": 3,
      "index": 4,
      "name": "Take Order",
      "icon": "Notebook",
      "icon_color": "primary",
      "url": "/takeorder",
      "created_at": "2025-09-08T10:00:00Z",
      "updated_at": "2025-09-10T12:34:56Z"
    }
  ],
  "special_quick_access": {
    "id": 99,
    "company_id": 3,
    "name": "Move Quantity",
    "url": "/movequantity",
    "created_at": "2025-09-08T10:00:00Z",
    "updated_at": "2025-09-10T12:34:56Z"
  }
}
```

Exemple 422:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "quick_accesses.0.id": ["The selected quick_accesses.0.id is invalid."],
    "quick_accesses.1.icon_color": ["The selected quick_accesses.1.icon_color is invalid."]
  }
}
```

## Remarques
- Les IDs qui n’appartiennent pas à la société de l’utilisateur sont ignorés (aucune erreur), et ne modifient rien.
- L’ordre (`index` 1..4) n’est pas modifié par cet endpoint.
- Le bouton spécial est mis à jour seulement sur les champs fournis (`name`, `url`).
