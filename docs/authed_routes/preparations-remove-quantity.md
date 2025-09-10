# POST /preparations/{id}/remove-quantity

**Description**
Retire une quantité de préparation d'un emplacement.

**Paramètres de chemin**
- `id` : identifiant de la préparation.

**Corps de la requête**
- `location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
Message et préparation avec stocks mis à jour.
