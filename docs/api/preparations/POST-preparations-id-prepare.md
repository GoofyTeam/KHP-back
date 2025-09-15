# POST /preparations/{id}/prepare

**Description**
Fabrique une quantité donnée d'une préparation en consommant automatiquement les composants requis (ingrédients ou sous-préparations) depuis leurs emplacements et en ajoutant le stock produit sur l'emplacement cible.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation à fabriquer

**Corps de la requête**
- `quantity` (numeric, requis, > 0) — quantité finale à produire
- `location_id` (integer, requis) — emplacement où stocker le résultat
- `overrides` (array d'objets, optionnel) — permet d'ajuster quantité ou emplacement pour certains composants
  - `overrides[].id` (integer, requis) — identifiant de l'ingrédient ou de la sous-préparation cible
  - `overrides[].type` (string, requis, `ingredient` ou `preparation`)
  - `overrides[].quantity` (numeric, optionnel, >= 0) — nouvelle quantité par unité produite
  - `overrides[].unit` (string, optionnel, valeur de `MeasurementUnit`) — unité dans laquelle la quantité override est exprimée
  - `overrides[].location_id` (integer, optionnel) — emplacement source alternatif

**Scénarios importants**
- Chaque override doit fournir au moins `quantity` ou `location_id` sinon une erreur 422 est renvoyée.
- Les conversions d'unités sont gérées automatiquement via `MeasurementUnit` lorsque `overrides[].unit` diffère de l'unité configurée sur le composant.
- Si un composant n'a pas suffisamment de stock sur l'emplacement prévu (après conversion), l'opération est annulée et une réponse HTTP 400 est renvoyée avec le message d'erreur (`Stock insuffisant pour '...'`).
- Lors de la production, les stocks consommés enregistrent un mouvement de type "withdrawal" et le stock produit est ajouté sur l'emplacement cible avec un mouvement "Preparation ... Produced".

**Réponse**
HTTP 200

```json
{
  "message": "Préparation de 5 portion de Pâte à crêpe effectuée avec succès",
  "preparation": {
    "id": 42,
    "name": "Pâte à crêpe",
    "locations": [
      {
        "id": 8,
        "pivot": { "quantity": 17 }
      }
    ]
  }
}
```

**Réponses d'erreur courantes**
- HTTP 400 — stock insuffisant sur l'un des composants.
- HTTP 422 — données invalides (ex. `overrides` sans quantité ni emplacement, `quantity` <= 0).
