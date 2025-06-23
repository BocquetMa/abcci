# 📊 ABCCI - WebApp

Une solution de visualisation de données pour le suivi des performances

## 🌐 Aperçu

ABCCI est une application web de visualisation de données conçue pour offrir aux entreprises un tableau de bord interactif. Elle permet un suivi des performances, des formations, et facilite la communication entre apprenants et formateurs.

---

## 📌 Fonctionnalités principales

- ✅ Visualisation en temps réel des formations disponibles
- 💬 Système de discussion instantanée entre formateurs et apprenants
- 🏅 Quiz interactifs avec système de badges
- 🔔 Système de notifications personnalisables
- 🔐 Authentification et gestion des rôles (utilisateur, formateur, administrateur)

---

## ⚙️ Stack Technique

| Partie       | Technologie                                |
|--------------|--------------------------------------------|
| Frontend     | HTML, CSS, JavaScript (Twig)               |
| Backend      | PHP avec le framework Symfony              |
| Base de données | MySQL                                   |
| Authentification | Symfony Security, rôles personnalisés  |
| Durée de développement | 2 mois                           |

---

## 🧩 Fonctionnalités détaillées

### 🎓 Formations
- Affichage des formations avec filtrage avancé (catégorie, date, niveau)
- Visualisation en temps réel des places disponibles
- Notifications automatiques lors des changements de statut

### 💬 Chat en temps réel
- Discussions instantanées entre utilisateurs et formateurs
- Historique des messages, envoi de fichiers
- Intégré proprement dans l'interface

### 🧠 Quiz & Badges
- Interface intuitive pour créer des quiz
- Types de questions variés (QCM, Vrai/Faux, Texte libre)
- Attribution automatique de badges de compétences

### 🔔 Notifications
- Création de notifications ciblées (formation disponible, rappel, etc.)
- Sélection des destinataires (tout le monde ou groupe spécifique)
- Icône de notification dans l'en-tête

### 🔐 Authentification & rôles
- Rôles disponibles :
  - Utilisateur (par défaut)
  - Formateur
  - Administrateur
- Possibilité pour l’admin de gérer les comptes et rôles
- Sécurisation via le système d’auth Symfony

---

## 🖥️ Aperçu visuel

- Dashboard des formations
- Messagerie en ligne
- Affichage responsive sur mobile
- Quiz interactif avec badges
- Notifications utilisateur

---

## 🧠 Défis rencontrés

### UX/UI des données
Créer une interface claire pour représenter des données complexes a nécessité une collaboration UX/UI étroite, avec des éléments visuels épurés et personnalisables.

### Performances et requêtes
Pour éviter les lenteurs sur de gros volumes de données :
- Requêtes SQL optimisées
- Système de cache mis en place pour accélérer le rendu

---

## 🚀 Lancer le projet en local


# Cloner le dépôt
git clone https://github.com/BocquetMa/abcci.git
cd abcci

# Installer les dépendances Symfony
composer install

# Copier le fichier d'environnement
cp .env.example .env

# Configurer la base de données dans le fichier .env

# Créer la base et lancer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Lancer le serveur
symfony server:start
📂 Structure du projet

├── public/             # Fichiers accessibles (CSS, JS, images)
├── src/                # Code PHP (contrôleurs, entités, services)
├── templates/          # Vues Twig
├── migrations/         # Fichiers de migration
├── config/             # Configuration Symfony
└── .env                # Variables d'environnement

📜 Licence
2025 Bocquet Mathéo

🙋‍♂️ Besoin d'aide ?

N'hésitez pas à me contacter pour plus d'informations ou des précisions sur :

La structure de la base de données

L'architecture du code

L'intégration frontend/backend

Ou toute autre question liée au projet

📧 matheo.bocquet@outlook.fr
🌐 https://bocquetma.github.io/portfolio/html/projet/abcci.html

---