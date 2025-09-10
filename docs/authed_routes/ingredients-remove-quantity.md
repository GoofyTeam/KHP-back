# POST /ingredients/{ingredient}/remove-quantity

**Description**
Retire une quantité d'ingrédient d'un emplacement.

**Paramètres de chemin**
- `ingredient` : identifiant de l'ingrédient.

**Corps de la requête**
- `location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
Message et ingrédient avec stocks mis à jour.
