# Quick Access — Reset

- Méthode: POST
- URL: `/quick-access/reset`
- Nom de route: `quick-access.reset`
- Authentification: Requise (Bearer token), route protégée

## Description
Réinitialise les 4 boutons Quick Access (index 1..4) de la société de l’utilisateur avec des valeurs par défaut, et crée/met à jour le Quick Access spécial.

Valeurs par défaut créées/mises à jour par index:
- 1: { name: "Add to stock", icon: "Plus", icon_color: "primary", url: "/stock/add" }
- 2: { name: "Menu Card", icon: "Notebook", icon_color: "info", url: "/menucard" }
- 3: { name: "Stock", icon: "Check", icon_color: "primary", url: "/stock" }
- 4: { name: "Take Order", icon: "Notebook", icon_color: "primary", url: "/takeorder" }

Quick Access spécial (unique par société):
- `{ name: "Move Quantity", url: "/movequantity" }`

Opérations effectuées:
- `updateOrCreate` sur `quick_accesses` pour chaque `index` (1..4) de la société.
- `updateOrCreate` sur `special_quick_accesses` pour la société.

## Réponses
- 200 OK: Retourne la liste complète des Quick Access (triés par `index`) et le Quick Access spécial.
- 401 Unauthorized: Token manquant/invalid.

Exemple 200:
```json
{
  "message": "Quick access reset",
  "quick_accesses": [
    { "id": 12, "company_id": 3, "index": 1, "name": "Add to stock", "icon": "Plus", "icon_color": "primary", "url": "/stock/add", "created_at": "2025-09-08T10:00:00Z", "updated_at": "2025-09-10T12:34:56Z" },
    { "id": 13, "company_id": 3, "index": 2, "name": "Menu Card", "icon": "Notebook", "icon_color": "info", "url": "/menucard", "created_at": "2025-09-08T10:00:00Z", "updated_at": "2025-09-10T12:34:56Z" },
    { "id": 14, "company_id": 3, "index": 3, "name": "Stock", "icon": "Check", "icon_color": "primary", "url": "/stock", "created_at": "2025-09-08T10:00:00Z", "updated_at": "2025-09-10T12:34:56Z" },
    { "id": 15, "company_id": 3, "index": 4, "name": "Take Order", "icon": "Notebook", "icon_color": "primary", "url": "/takeorder", "created_at": "2025-09-08T10:00:00Z", "updated_at": "2025-09-10T12:34:56Z" }
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

## Remarques
- Opération idempotente: appeler plusieurs fois produit le même résultat.
- Les valeurs existantes aux index 1..4 sont écrasées par les valeurs par défaut.
- L’ensemble des Quick Access reste limité aux index 1..4 (selon le schéma de base de données).
