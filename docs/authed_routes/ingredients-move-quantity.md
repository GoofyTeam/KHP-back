# POST /ingredients/{ingredient}/move-quantity

**Description**
Déplace une quantité d'ingrédient entre deux emplacements.

**Paramètres de chemin**
- `ingredient` : identifiant de l'ingrédient.

**Corps de la requête**
- `from_location_id` (integer, requis)
- `to_location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
Message et ingrédient avec stocks mis à jour.
