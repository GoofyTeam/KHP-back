# DELETE /menu-types/{id}

**Description**
Supprime un type de menu de l'entreprise de l'utilisateur lorsqu'il n'est plus associé à aucun menu.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant du type de menu à supprimer. Doit appartenir à l'entreprise de l'utilisateur.

**Corps de la requête**
Aucun corps de requête.

**Scénarios importants**
- Un type référencé par au moins un menu ne peut pas être supprimé (erreur 422).
- La suppression retire automatiquement l'ordre public associé.

**Réponse**
HTTP 200

```json
{
  "message": "Menu type deleted successfully"
}
```

**Exemple de requête**

```http
DELETE /menu-types/3
```

**Codes d'erreur courants**
- **401 Unauthorized** — L'utilisateur n'est pas authentifié.
- **404 Not Found** — Type inexistant ou n'appartenant pas à l'entreprise de l'utilisateur.
- **422 Unprocessable Entity** — Le type est encore lié à un ou plusieurs menus.
