# PUT /company/options

**Description**
Modifie certaines options de configuration de l'entreprise.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `auto_complete_menu_orders` (boolean, optionnel)
- `open_food_facts_language` (string, optionnel, valeurs: `fr`, `en`)

**Réponse**
HTTP 200

```json
{
  "message": "Options mises à jour avec succès",
  "data": {
    "auto_complete_menu_orders": true,
    "open_food_facts_language": "fr"
  }
}
```
