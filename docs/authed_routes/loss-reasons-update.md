# PUT /loss-reasons/{id}

**Description**
Met à jour le nom d'une raison de perte.

**Paramètres de chemin**
- `id` : identifiant de la raison.

**Corps de la requête**
- `name` (string, requis, unique)

**Réponse**
HTTP 200

```json
{
  "message": "Raison mise à jour avec succès",
  "data": {
    "id": 1,
    "name": "Casse"
  }
}
```
