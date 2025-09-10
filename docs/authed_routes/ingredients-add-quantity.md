# POST /ingredients/{ingredient}/add-quantity

**Description**
Ajoute une quantité d'ingrédient sur un emplacement.

**Paramètres de chemin**
- `ingredient` : identifiant de l'ingrédient.

**Corps de la requête**
- `location_id` (integer, requis)
- `quantity` (numeric, requis)

**Réponse**
Message et ingrédient avec stocks mis à jour.
