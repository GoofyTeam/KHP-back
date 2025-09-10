# PUT /user/update/info

**Description**
Met à jour les informations personnelles de l'utilisateur (nom ou email).

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, optionnel)
- `email` (string, optionnel, unique)

**Réponse**
HTTP 200

```json
{
  "message": "User updated successfully",
  "user": {
    "id": 1,
    "name": "Alice",
    "email": "alice@example.com"
  }
}
```
