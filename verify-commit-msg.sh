#!/bin/sh

# Récupère le chemin du fichier de commit
MSG_PATH="$1"

# Vérifie qu'un argument a été passé
if [ -z "$MSG_PATH" ]; then
  echo "Chemin du fichier de commit non spécifié" >&2
  exit 1
fi

# Lit le message (suppression éventuelle du saut de ligne final)
message=$(tr -d '\n' < "$MSG_PATH")

# Regex pour valider le format :
# [KHP-<num>] <type>[ (scope)]: description
commit_regex='^\[KHP-[0-9]+\] (feat|fix|chore|ci)(\((front|back|global)\))?: .+$'

# Test du format
if echo "$message" | grep -E -q "$commit_regex"; then
  # OK
  exit 0
else
  # Erreur de format
  echo "\
❌ Mauvais format de commit.

Exemple attendu :
[KHP-123] feat(front): short description
" >&2
  exit 1
fi
