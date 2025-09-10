# POST /location

**Description**
Crée un nouvel emplacement de stockage.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `location_type_id` (integer, requis)

**Réponse**
HTTP 201

```json
{
  "message": "Emplacement créé avec succès",
  "data": {
    "id": 1,
    "name": "Réserve",
    "location_type_id": 2
  }
}
```
