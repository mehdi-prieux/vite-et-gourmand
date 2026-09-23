# Gestion de projet - Vite & Gourmand

## Méthode

Le besoin a été découpé par parcours : visiteur, client, employé et administrateur. Chaque fonctionnalité suit le flux `à faire -> en cours -> recette -> terminé`. Les anomalies sont enregistrées dans le compte rendu de tests avec leur impact et la preuve de correction. Aucun ticket Jira, Notion ou Trello antérieur n'est inventé dans ce document.

Au 22 septembre, le dépôt est public : <https://github.com/mehdi-prieux/vite-et-gourmand>. La [version de livraison](https://github.com/mehdi-prieux/vite-et-gourmand/tree/livraison-studi) et `develop` ont été publiées sans réécrire l'historique ; `main` reste inchangée. La branche de travail historique est `fix/database-config-env`. La PR n°2 reste ouverte et non fusionnée. Le suivi public de livraison est [GitHub Issues](https://github.com/mehdi-prieux/vite-et-gourmand/issues) ; aucun historique antérieur de tableau n'est revendiqué.

## Backlog traçable au 22 septembre 2026

| Lot | Contenu | État | Preuve |
|---|---|---|---|
| B1 | Site public, catalogue, filtres, fiches, contact | Recette effectuée | `tests/functional/api_http.sh` et contrôle navigateur |
| B2 | Comptes, profil, commandes, livraison, suivi, avis | Recette effectuée | Scénario API complet sur `vite_gourmand_test` |
| B3 | Personnel : commandes, catalogue, horaires, avis | Recette effectuée | Scénario API et contrôle navigateur connecté |
| B4 | Administration : employés et statistiques | Partiel | Gestion employés validée ; CouchDB non validé sur un service réel |
| B5 | Manuel, charte et six maquettes | Produit | `output/pdf/` |
| B6 | Documentation technique, MCD et diagrammes | Produit | `docs/documentation-technique-studi.md` |
| B7 | Accessibilité RGAA | Contrôles ciblés et corrections effectués ; conformité globale non attestée | `docs/audit-accessibilite-rgaa.md` |
| B8 | Identité légale, hébergement public, lien projet | En attente d'informations du propriétaire | `docs/deploiement-studi.md` |

## Décisions et risques

1. MySQL conserve les transactions métier. CouchDB est la technologie documentaire retenue pour satisfaire l'exigence NoSQL sans extension PHP spécifique.
2. Les campagnes SQL écrivent uniquement dans `vite_gourmand_test` après contrôle effectif de la connexion. Les données temporaires sont nettoyées.
3. Les comptes de démonstration ont des mots de passe connus : ils sont exclus de la production.
4. Le déploiement n'est pas effectué sans choix d'hébergement, accès, URL publique et validation des contenus juridiques. Un domaine personnalisé n'est pas nécessaire.
5. La projection NoSQL est limitée à la destination locale et aux champs approuvés ; son exécution réelle et sa destination de production restent à valider.

## Suivi demandé pour la remise

Le suivi public de livraison est désormais dans [GitHub Issues](https://github.com/mehdi-prieux/vite-et-gourmand/issues) : une issue fermée pour les parcours et livrables contrôlés, trois issues ouvertes pour CouchDB, le déploiement et les informations légales. Ces tickets décrivent l'état constaté le 22 septembre 2026 et ne prétendent pas reconstituer un historique de gestion qui n'existait pas. Le jeton GitHub actuel ne dispose pas des droits `read:project`/`project` nécessaires à la création d'un GitHub Project ; GitHub Issues est donc l'outil public de suivi retenu pour la remise urgente.
