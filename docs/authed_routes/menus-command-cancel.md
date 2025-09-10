# POST /menus/command/{id}/cancel

**Description**
Annule une commande de menu.

**Paramètres de chemin**
- `id` : identifiant de la commande.

**Corps de la requête**
Aucun

**Réponse**
HTTP 200

```json
{
  "message": "Order canceled",
  "order": {
    "id": 1,
    "status": "canceled"
  }
}
```
