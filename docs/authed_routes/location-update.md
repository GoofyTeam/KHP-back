# PUT /location/{id}

**Description**
Met à jour un emplacement existant.

**Paramètres de chemin**
- `id` : identifiant de l'emplacement.

**Corps de la requête**
- `name` (string, optionnel)
- `location_type_id` (integer, optionnel)

**Réponse**
HTTP 200

```json
{
  "message": "Emplacement mis à jour avec succès",
  "data": {
    "id": 1,
    "name": "Réserve centrale",
    "location_type_id": 2
  }
}
```
