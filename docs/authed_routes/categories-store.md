# POST /categories

**Description**
Crée une nouvelle catégorie avec ses durées de conservation.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, requis, unique)
- `shelf_lives` (object, requis) :
  - `fridge` (integer, requis)
  - `freezer` (integer, requis)

**Réponse**
Message et catégorie créée.
