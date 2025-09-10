# GET /image-proxy/{bucket}/{path}

**Description**
Récupère une image stockée sur S3 via une URL temporaire.

**Paramètres de chemin**
- `bucket` : nom du bucket.
- `path` : chemin complet de l'image.

**Corps de la requête**
Aucun

**Réponse**
HTTP 200

Image binaire du fichier demandé.

En cas d'erreur :

```json
{
  "error": "Image not found"
}
```
