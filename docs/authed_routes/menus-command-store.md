# POST /menus/{menu}/command

**Description**
Crée une commande pour un menu.

**Paramètres de chemin**
- `menu` : identifiant du menu.

**Corps de la requête**
- `quantity` (integer, optionnel, défaut 1)

**Réponse**
HTTP 201

```json
{
  "message": "Order created",
  "order": {
    "id": 1,
    "status": "completed",
    "quantity": 1
  }
}
```
