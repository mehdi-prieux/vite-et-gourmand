# Vérifications HTTP des API (à exécuter)

Préparer une base MySQL de test isolée ; ne pas réinitialiser la base locale existante.

- `GET /backend/api/menu.php` : code 200, `Content-Type: application/json; charset=utf-8`, liste des menus au format JSON.
- `GET /backend/api/plats.php` : code 200, même type de contenu, liste des plats au format JSON.
- `POST` sur chacun de ces deux chemins : code 405, en-tête `Allow: GET`, réponse JSON avec `error`.
- Avec des identifiants MySQL incorrects : code 500, réponse JSON générique sans mot de passe, nom d'utilisateur ni détail PDO.
- Vérifier que les caractères accentués restent lisibles et que les erreurs détaillées ne figurent que dans les journaux serveur.

Aucun de ces résultats n'a encore été constaté en exécution : cette liste est une procédure de recette, pas un compte rendu de tests réussis.
