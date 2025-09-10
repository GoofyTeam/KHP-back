# DELETE /losses/rollback/{loss}

**Description**
Annule une perte enregistrée et restaure le stock.

**Paramètres de chemin**
- `loss` : identifiant de la perte.

**Corps de la requête**
Aucun

**Réponse**
HTTP 200

```json
{
  "message": "Perte annulée avec succès"
}
```
