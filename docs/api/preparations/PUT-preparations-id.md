# PUT /preparations/{id}

**Description**
Met à jour les métadonnées d'une préparation existante, gère ses composants (ajout/suppression) et ajuste ses quantités disponibles par emplacement.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation

**Corps de la requête**
- `name` (string, optionnel) — nouveau nom unique dans l'entreprise
- `unit` (string, optionnel, valeur de `MeasurementUnit`)
- `category_id` (integer, optionnel) — nouvelle catégorie
- `image` (fichier image, optionnel) — exclusif avec `image_url`
- `image_url` (url, optionnel) — exclusif avec `image`
- `entities_to_add` (array d'objets, optionnel)
  - `entities_to_add[].id` (integer, requis)
  - `entities_to_add[].type` (string, requis, `ingredient` ou `preparation`)
- `entities_to_remove` (array d'objets, optionnel)
  - `entities_to_remove[].id` (integer, requis)
  - `entities_to_remove[].type` (string, requis, `ingredient` ou `preparation`)
- `quantities` (array d'objets, optionnel) — ajuste la quantité finale disponible sur un emplacement donné
  - `quantities[].location_id` (integer, requis)
  - `quantities[].quantity` (numeric, requis, >= 0)

**Scénarios importants**
- Tenter de retirer ou d'ajouter une entité qui n'appartient pas à l'entreprise de l'utilisateur provoque une erreur 404.
- Chaque fois qu'une quantité par emplacement est modifiée, un mouvement de stock est journalisé (utile pour suivre les ajustements manuels).
- Comme pour la création, envoyer `image` et `image_url` simultanément retourne une erreur 422.

**Réponse**
HTTP 200

```json
{
  "message": "Préparation mise à jour avec succès",
  "preparation": {
    "id": 42,
    "name": "Pâte à crêpe maison",
    "entities": [
      { "entity_id": 7, "entity_type": "App\\Models\\Ingredient" }
    ],
    "locations": [
      {
        "id": 3,
        "pivot": { "quantity": 12 }
      }
    ]
  }
}
```
