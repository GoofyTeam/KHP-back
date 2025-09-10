# POST /location-types

**Description**
Crée un nouveau type de localisation pour l'entreprise.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)

**Réponse**
HTTP 201

```json
{
  "message": "Type de localisation créé avec succès",
  "data": {
    "id": 3,
    "name": "Arrière-boutique"
  }
}
```
