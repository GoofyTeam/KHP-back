# POST /loss-reasons

**Description**
Crée une raison de perte personnalisée.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)

**Réponse**
HTTP 201

```json
{
  "message": "Raison créée avec succès",
  "data": {
    "id": 1,
    "name": "Casse"
  }
}
```
