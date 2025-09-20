# POST /menu-types

**Description**
Crée un nouveau type de menu rattaché à l'entreprise de l'utilisateur. Chaque type possède un indice public permettant d'ordonner les sections de la carte.

**Paramètres de chemin**
Aucun paramètre de chemin.

**Corps de la requête**
- `name` (string, requis) — nom unique du type de menu pour l'entreprise (<= 255 caractères).
- `public_index` (integer, optionnel) — position du type dans la carte publique (>= 0). Les types sont triés par cet indice puis par leur nom.

**Scénarios importants**
- Deux types d'une même entreprise ne peuvent pas partager le même nom.
- Si `public_index` est omis, il est initialisé à `0`.

**Réponse**
HTTP 201

```json
{
  "message": "Menu type created successfully",
  "data": {
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
  }
}
```

**Exemple de requête JSON**

```http
POST /menu-types
Content-Type: application/json

{
  "name": "Plats",
  "public_index": 1
}
```

**Codes d'erreur courants**
- **401 Unauthorized** — L'utilisateur n'est pas authentifié.
- **422 Unprocessable Entity** — Validation échouée (nom dupliqué, indice négatif, etc.).
