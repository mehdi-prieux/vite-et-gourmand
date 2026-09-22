# Matrice de recette finale Studi — 22 septembre 2026

Référence : `énoncé studi.pdf` original (pages 9 à 12). Les résultats « validé » s'appuient sur le compte rendu de tests initial et les non-régressions du 22 septembre ; « partiel » n'est pas une validation complète. Aucune donnée de la base `vite_gourmand` n'a été modifiée.

| Exigence Studi | Implémentation réelle | Preuve | Résultat | Reste à faire |
|---|---|---|---|---|
| Site public, catalogue, menus, filtres, contact, horaires sur sept jours | `frontend/`, API de catalogue/contact/horaires | Recette API complète ; navigateur accueil, horaires lundi-dimanche et noms de liens | Validé localement | Confirmer les vrais horaires du week-end |
| Comptes, profils et mots de passe | Inscription, connexion, profil, réinitialisation | Scénario API, rôles et CSRF | Validé localement | Remplacer les comptes de démonstration en production |
| Commande, prix, livraison, suivi, modification et annulation | API et espaces client/personnel | Recette API complète ; base réelle vérifiée par `SELECT DATABASE()` = `vite_gourmand_test` | Validé localement | Refaire une recette de préproduction après déploiement |
| Avis, catalogue et personnel | Modération, gestion menus/plats/horaires, employés | Scénario API avec nettoyage des enregistrements temporaires | Validé localement | — |
| Retour de matériel : e-mail au changement de statut, dix jours ouvrés et 600 EUR | Transition et notification immédiate | Recette API et journal e-mail du test initial | Validé localement | Configurer un transport e-mail réel |
| Graphiques de commandes depuis une base non relationnelle | Lecture CouchDB exclusive ; projection automatique de test limitée à la destination/champs approuvés ; reprise idempotente préparée | Recette de défaillance contrôlée et reprise `--dry-run` ; aucun serveur CouchDB opérationnel local | Non validé de bout en bout | Démarrer CouchDB local, exécuter la reprise `--apply`, tester les graphiques, puis approuver la destination de production |
| Manuel utilisateur PDF avec comptes | `output/pdf/manuel_utilisateur_studi.pdf` | PDF A4 de 2 pages, extraction et inspection visuelle ; les trois mots de passe correspondent aux hachages du script SQL d'insertion | Produit et contrôlé | Les hachages des comptes déjà présents dans `vite_gourmand_test` diffèrent : ne pas utiliser cette base existante comme preuve de connexion des comptes de démonstration |
| Charte graphique et 3 maquettes ordinateur + 3 mobile | `output/pdf/charte_graphique_et_maquettes_studi.pdf` | PDF de 7 pages, inspection visuelle | Produit et contrôlé | — |
| Documentation technique, MCD/classes, cas d'usage, séquences, déploiement | `docs/documentation-technique-studi.md`, `docs/deploiement-studi.md` | Revue du contenu et diagrammes Mermaid | Produit ; déploiement à exécuter | Confirmer hébergement et paramètres réels |
| Gestion de projet et lien vers outil | `docs/gestion-projet-studi.md` et [GitHub Issues](https://github.com/mehdi-prieux/vite-et-gourmand/issues) | Tickets publics créés le 22 septembre, sans faux historique | Suivi public disponible | Maintenir les statuts honnêtes |
| Accessibilité RGAA | Corrections de repères, focus et liens ; `docs/audit-accessibilite-rgaa.md` | HTML Tidy, premier focus clavier, noms des champs/images, inspection AX, contrastes et 320 px sur les parcours clés | Contrôles pertinents effectués ; pas de conformité globale attestée | Tester les états connectés et lecteurs d'écran avant toute déclaration globale |
| Mentions légales, confidentialité et CGV définitives | Pages pédagogiques provisoires | Inspection des pages | Non validé | Fournir identité légale, hébergeur, contacts, responsable et durées de conservation ; validation juridique |
| Dépôt, outil de projet et application publics | Dépôt GitHub public : <https://github.com/mehdi-prieux/vite-et-gourmand> ; suivi public : <https://github.com/mehdi-prieux/vite-et-gourmand/issues> | `private=false` vérifié ; commits de livraison préparés localement ; aucune URL d'application publique | Partiel | Publier les commits, choisir l'hébergement gratuit et obtenir l'URL publique ; domaine personnalisé facultatif |
| Tests unitaires, tests navigateur automatisés, CI | Non ajoutés | L'énoncé ne les impose pas ; recette API et contrôle navigateur disponibles | Facultatif | Amélioration ultérieure si le temps le permet |
| Relance automatique dix jours après le statut matériel | Non ajoutée | L'énoncé demande le rappel dans l'e-mail immédiat, pas un second envoi programmé | Hors périmètre obligatoire | Aucune |

## Bilan de sécurité et Git

- Tests SQL avec écritures exécutés uniquement après vérification de `SELECT DATABASE()` sur `vite_gourmand_test`. Les campagnes après correction ont réussi ; la dernière commande temporaire (58) a été nettoyée.
- Correction `byId('order-form').reset()` de `frontend/client.html` conservée. `git diff --check`, syntaxe PHP et HTML Tidy sans erreur sur les fichiers contrôlés.
- Recherche de motifs de secrets : seulement mots de passe de démonstration/test et variables d'exemple ; aucun jeton ou clé privée détecté. Ne pas publier les comptes de démonstration actifs.
- Commits thématiques créés localement : (1) API, schéma SQL et recette ; (2) interfaces ; (3) documentation et livrables. L'intégration CouchDB reste explicitement partielle tant qu'une instance réelle n'a pas été testée. Aucun secret n'est volontairement ajouté ; la copie Word nominative est ignorée par Git.
- Branches : `main` et des branches de fonctionnalité existent ; `develop` peut être créée à partir de l'état réel, sans fabriquer d'historique. La PR n°2 ne doit pas être fusionnée pour la remise.
