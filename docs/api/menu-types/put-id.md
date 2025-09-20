# PUT /menu-types/{id}

**Description**
Met à jour le nom et/ou l'indice public d'un type de menu existant de l'entreprise de l'utilisateur.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant du type de menu à modifier. Doit appartenir à l'entreprise de l'utilisateur.

**Corps de la requête**
- `name` (string, optionnel) — nouveau nom unique pour le type (<= 255 caractères).
- `public_index` (integer, optionnel) — nouvelle position du type dans la carte publique (>= 0). Les types restent triés par indice puis par nom.

**Scénarios importants**
- Les champs absents restent inchangés.
- La combinaison (`company_id`, `name`) doit rester unique.
- Mettre à jour `public_index` crée ou remplace automatiquement l'ordre public associé.

**Réponse**
HTTP 200

```json
{
  "message": "Menu type updated successfully",
  "data": {
    "id": 3,
    "company_id": 7,
    "name": "Desserts",
    "public_index": 2,
    "created_at": "2024-05-10T08:00:00.000000Z",
    "updated_at": "2024-05-18T09:30:00.000000Z",
    "public_order": {
      "id": 12,
      "menu_type_id": 3,
      "company_id": 7,
      "position": 2,
      "created_at": "2024-05-10T08:00:00.000000Z",
      "updated_at": "2024-05-18T09:30:00.000000Z"
    }
  }
}
```

**Exemple de requête JSON**

```http
PUT /menu-types/3
Content-Type: application/json

{
  "name": "Desserts",
  "public_index": 2
}
```

**Codes d'erreur courants**
- **401 Unauthorized** — L'utilisateur n'est pas authentifié.
- **404 Not Found** — Type de menu introuvable pour l'entreprise de l'utilisateur.
- **422 Unprocessable Entity** — Validation échouée (nom dupliqué, indice négatif, etc.).
