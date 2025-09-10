# PUT /user/update/info

**Description**
Met à jour les informations personnelles de l'utilisateur (nom ou email).

**Paramètres de chemin**
Aucun

**Corps de la requête**
- `name` (string, optionnel)
- `email` (string, optionnel, unique)

**Réponse**
Message de confirmation et objet `user` mis à jour.
