# PUT /categories/{id}

**Description**
Met à jour une catégorie et ses durées de conservation.

**Paramètres de chemin**
- `id` : identifiant de la catégorie.

**Corps de la requête**
- `name` (string, optionnel)
- `shelf_lives` (object, optionnel) :
  - `fridge` (integer, requis si fourni)
  - `freezer` (integer, requis si fourni)
  - `{location_type_id}` (integer ou null, optionnel)

**Réponse**
Message et catégorie mise à jour.
