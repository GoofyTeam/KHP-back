# PUT /user/update/password

**Description**
Change le mot de passe de l'utilisateur.

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `current_password` (string, requis)
- `new_password` (string, requis, min 8 caractères)
- `new_password_confirmation` (string, requis)

**Réponse**
HTTP 200

```json
{
  "message": "Password updated successfully"
}
```

Échec :

```json
{
  "message": "Password is the same as the current one"
}
```
