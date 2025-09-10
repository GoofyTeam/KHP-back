# POST /losses

**Description**
Enregistre une perte de stock sur un emplacement.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `loss_item_type` (string `ingredient` ou `preparation`, requis)
- `loss_item_id` (integer, requis)
- `location_id` (integer, requis)
- `quantity` (numeric, requis)
- `reason` (string, requis)

**Réponse**
Message et perte enregistrée.
