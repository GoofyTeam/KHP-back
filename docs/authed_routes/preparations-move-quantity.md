# POST /preparations/{id}/move-quantity

**Description**
Déplace une quantité de préparation d'un emplacement à un autre.

**Paramètres de chemin**
- `id` : identifiant de la préparation.

**Corps de la requête**
- `from_location_id` (integer, requis)
- `to_location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
HTTP 200

```json
{
  "message": "Quantité de la préparation déplacée avec succès",
  "preparation": {
    "id": 1,
    "name": "Sauce tomate"
  }
}
```
