# DELETE /preparations/{id}

**Description**
Supprime définitivement une préparation et toutes ses associations (composants, emplacements) pour l'entreprise courante.

**Paramètres de chemin**
- `id` (integer, requis) — identifiant de la préparation à supprimer

**Corps de la requête**
Aucun

**Scénarios importants**
- La suppression échoue avec une erreur 404 si la préparation n'appartient pas à l'entreprise de l'utilisateur.
- Les liaisons avec les ingrédients, sous-préparations et emplacements sont supprimées automatiquement par les contraintes de base de données.

**Réponse**
HTTP 204 (contenu vide)
