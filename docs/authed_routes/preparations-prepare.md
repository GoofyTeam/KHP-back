# POST /preparations/{id}/prepare

**Description**
Déclare la préparation d'un lot et met à jour les stocks des composants utilisés.

**Paramètres de chemin**
- `id` : identifiant de la préparation.

**Corps de la requête**
- `quantity` (numeric, requis)
- `location_id` (integer, requis)
- `components` (array, requis) : chaque élément contient
  - `entity_id` (integer)
  - `entity_type` (string `ingredient` ou `preparation`)
  - `quantity` (numeric)
  - `sources` (array `{location_id, quantity}`)

**Réponse**
HTTP 200

```json
{
  "message": "Préparation de 5 unit de Sauce tomate effectuée avec succès",
  "preparation": {
    "id": 1,
    "name": "Sauce tomate"
  }
}
```
