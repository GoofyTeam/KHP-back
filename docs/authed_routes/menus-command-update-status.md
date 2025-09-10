# PUT /menus/command/{id}/status

**Description**
Change le statut d'une commande de menu.

**Paramètres de chemin**
- `id` : identifiant de la commande.

**Corps de la requête**
- `status` (string, requis, valeurs : `pending`, `completed`)

**Réponse**
HTTP 200

```json
{
  "message": "Order updated",
  "order": {
    "id": 1,
    "status": "completed"
  }
}
```
