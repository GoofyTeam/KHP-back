# PUT /location-types/{id}

**Description**
Met à jour le nom d'un type de localisation.

**Paramètres de chemin**
- `id` : identifiant du type.

**Corps de la requête**
- `name` (string, requis, unique)

**Réponse**
HTTP 200

```json
{
  "message": "Type de localisation mis à jour avec succès",
  "data": {
    "id": 3,
    "name": "Arrière-boutique"
  }
}
```
