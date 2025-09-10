# POST /location/assign-type

**Description**
Associe un emplacement à un type de localisation.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `location_id` (integer, requis)
- `location_type_id` (integer, requis)

**Réponse**
HTTP 200

```json
{
  "message": "L'emplacement 'Frigo A' a été associé au type 'Réfrigérateur'",
  "data": {
    "id": 1,
    "name": "Frigo A",
    "location_type_id": 1
  }
}
```
