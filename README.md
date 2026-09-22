# Vite & Gourmand

Application web de gestion de menus événementiels développée pour l’entreprise Vite & Gourmand.

## Présentation du projet

Vite & Gourmand est une application permettant aux clients de consulter les prestations proposées par l’entreprise, de créer un compte, de commander des menus événementiels et de suivre leurs commandes.

L’application possède plusieurs espaces selon les profils utilisateurs :

- Visiteur
- Client
- Employé
- Administrateur

Les employés peuvent gérer les menus, les plats, les commandes et les avis clients.

L’administrateur dispose de fonctionnalités supplémentaires :

- gestion des comptes employés ;
- consultation des statistiques commerciales ;
- analyse des commandes.

---

# Technologies utilisées

## Front-end

- HTML5
- CSS3
- JavaScript

## Back-end

- PHP

## Bases de données

- MySQL pour les données relationnelles ;
- MongoDB pour les données statistiques.

## Gestion du projet

- Git et GitHub

---

# Structure du projet

---

# Prérequis

Avant d’installer le projet, il faut disposer de :

- PHP 8 ou supérieur ;
- MySQL ;
- MongoDB ;
- un serveur local compatible PHP ;
- Git.

---

# Installation du projet

## 1. Cloner le dépôt

# Configuration du projet
git clone https://github.com/mehdi-prieux/vite-et-gourmand.git
cd vite-et-gourmand
```md
# Configuration de la base MySQL

Créer la base de données :

```sql
CREATE DATABASE vite_gourmand;
```
## Structure du projet

```text
vite_gourmand_stats
│
├── backend/
│   └── config/
│
├── frontend/
│
├── database/
│
├── diagrams/
│
├── docs/
│
└── tests/
```

---

## Lancement du projet

Démarrer le serveur PHP :

```bash
php -S localhost:8000
```

Puis ouvrir dans le navigateur :

```
http://localhost:8000
```
