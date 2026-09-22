# Vite & Gourmand

Application web PHP/MySQL et CouchDB d’un traiteur bordelais : catalogue public, commandes client, suivi par le personnel et administration. L'intégration CouchDB n'est pas encore validée sur un serveur réel.

## Fonctionnalités

- site public responsive : accueil, menus, filtres, fiches détaillées, allergènes, avis validés, horaires et contact ;
- comptes : inscription, connexion, profil, déconnexion et réinitialisation du mot de passe ;
- client : commande, remise de 10 %, livraison hors Bordeaux, modification/annulation avant acceptation, suivi et avis après commande terminée ;
- personnel : filtres, statuts, modification/annulation après contact tracé, catalogue, plats, allergènes, galeries, horaires et modération des avis ;
- administrateur : création et activation/désactivation des employés, statistiques filtrables ;
- sécurité : mots de passe hachés, requêtes préparées, sessions protégées, contrôle des rôles et jetons CSRF sur les écritures authentifiées.

## Technologies

- HTML, CSS et JavaScript sans framework ;
- PHP 8.1 ou supérieur avec PDO MySQL et cURL ;
- MySQL 8 ou MariaDB compatible ;
- Apache CouchDB pour les statistiques documentaires ;
- envoi d’e-mails via `mail()` ou journal local pour les tests.

Les statistiques administrateur lisent CouchDB uniquement ; sans instance configurée, l'API répond 503. Les changements de commande sont projetés uniquement vers la destination locale de test approuvée (`http://127.0.0.1:5984/vite_gourmand_stats_test`) lorsque `APP_ENV=test` et que MySQL utilise réellement `vite_gourmand_test`. Aucun transfert automatique vers une destination de production n'est activé.

## Installation locale

1. Créer une base vide dédiée.
2. Adapter la première instruction `CREATE DATABASE`/`USE` de `database/mysql/create_database.sql`, puis exécuter ce script.
3. Adapter de la même façon `database/mysql/insert_data.sql` si les données de démonstration sont souhaitées.
4. Exporter les variables de connexion décrites dans `.env.example`.
5. Démarrer le serveur à la racine du dépôt :

```bash
php -S 127.0.0.1:8000
```

6. Ouvrir `http://127.0.0.1:8000/frontend/`.

Pour essayer les statistiques NoSQL, préparer une instance CouchDB locale protégée par un compte technique et une base `vite_gourmand_stats_test`, puis définir `APP_ENV=test`, `DB_NAME=vite_gourmand_test`, `COUCHDB_URL=http://127.0.0.1:5984`, `COUCHDB_DATABASE=vite_gourmand_stats_test`, `COUCHDB_USER` et `COUCHDB_PASSWORD`. Vérifier la base SQL, lancer `php scripts/synchroniser_statistiques_test.php --dry-run`, puis `--apply` pour reprendre l'historique. L'outil compare ensuite le nombre de commandes et le chiffre d'affaires lus dans CouchDB avec MySQL. Ne pas versionner les identifiants.

Le code ne charge pas automatiquement un fichier `.env`. Les variables doivent être injectées par le terminal, le serveur web ou l’hébergeur.

Si l'hébergeur gratuit ne permet pas de définir des variables d'environnement, copier `backend/config/local.php.example` vers `backend/config/local.php` **sur l'hébergement uniquement** et remplacer les exemples par les paramètres reçus. Ce fichier est ignoré par Git et son répertoire est fermé par `.htaccess` sous Apache. Les vraies variables d'environnement gardent la priorité sur ce fichier. Ne jamais envoyer `local.php` sur GitHub.

## Comptes de démonstration

Après chargement de `database/mysql/insert_data.sql` :

| Rôle | Adresse | Mot de passe local |
|---|---|---|
| Client | `client@test.com` | `Client!Demo2026` |
| Employé | `employee@test.com` | `Employee!Demo2026` |
| Administrateur | `admin@test.com` | `Admin!Demo2026` |

Ces comptes sont publics et ne doivent jamais être conservés sur une instance de production.

## Configuration

Les variables essentielles sont :

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` ;
- `APP_BASE_URL` pour les liens envoyés par e-mail ;
- `CONTACT_EMAIL` pour le destinataire du formulaire de contact ;
- `MAIL_TRANSPORT=mail` en exploitation ou `log` uniquement en test ;
- `GEOCODING_ENDPOINT` et `ROUTING_ENDPOINT` pour remplacer les services publics OpenStreetMap/OSRM si nécessaire.

En production, utiliser un compte MySQL dédié, HTTPS, une configuration de session adaptée et un service d’e-mail transactionnel fiable.

## Tests

La recette automatisée refuse toute base autre que `vite_gourmand_test`, puis exécute une seconde vérification avec `SELECT DATABASE()` avant le premier nettoyage ou la première écriture :

```bash
DB_NAME=vite_gourmand_test ./tests/functional/api_http.sh
```

Elle couvre le parcours public, les trois rôles, les commandes, la livraison, les e-mails journalisés, les avis, le catalogue, les horaires, l’administration, la défaillance contrôlée des statistiques lorsque CouchDB est absent et la réinitialisation du mot de passe. Toutes les données temporaires SQL sont supprimées à la sortie ; si la destination CouchDB locale approuvée est active, les documents correspondants sont également retirés.

Pour contrôler la syntaxe PHP :

```bash
find backend -name '*.php' -print0 | xargs -0 -n1 php -l
```

Le bilan détaillé et les écarts Studi sont conservés dans `docs/compte-rendu-tests-et-audit-2026-09-22.md`.

## Arborescence

```text
backend/              API, modèles, configuration et services
database/mysql/       création, données initiales et migrations
docs/                 configuration et compte rendu de recette
frontend/             pages et ressources publiques/métier
tests/functional/     recette HTTP automatisée
tests/manual/         scénarios de contrôle complémentaires
```

## Déploiement

Le déploiement public n’est pas effectué automatiquement. Avant une exploitation réelle : remplacer les contenus légaux provisoires, configurer l'e-mail et les services de distance, installer la base NoSQL attendue, appliquer les migrations sur une sauvegarde, supprimer les comptes de démonstration et rejouer la recette sur une base de préproduction isolée.

Pour une **démonstration gratuite jetable**, une instance de type InfinityFree peut héberger PHP, MySQL et HTTPS avec sous-domaine gratuit, sous réserve de créer le compte, de vérifier les limites réelles et de n'y placer aucune donnée client réelle. Sa fonction `mail()` est désactivée : contact, notifications et réinitialisation par e-mail ne doivent pas être annoncés comme fonctionnels avant adaptation. L'intégration CouchDB n'est actuellement active que pour la base locale de test approuvée ; les statistiques ne seront donc pas disponibles sur cette démonstration. Voir `docs/deploiement-studi.md` pour les étapes et restrictions. Le dépôt public de version jury est la branche `livraison-studi` ; la PR n°2 reste non fusionnée.
