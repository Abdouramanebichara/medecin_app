# Application Web de Gestion des Rendez-vous Médicaux

---

## Description

Cette application web permet la prise, la gestion et le suivi des rendez-vous médicaux. Elle offre une interface moderne, ergonomique et sécurisée, adaptée à trois profils d’utilisateurs : patients, médecins et administrateurs.

Le projet s’appuie sur une base de données MySQL robuste (`medical_app`) et un backend PHP simple (procédural ou orienté objet sans framework). Le frontend utilise HTML5, CSS3, Bootstrap et JavaScript pour une expérience utilisateur fluide et agréable.

---

## Fonctionnalités principales

### Pour les Patients (rôle par défaut)
- Inscription et connexion sécurisées
- Tableau de bord personnalisé avec statistiques
- Prise, modification et annulation de rendez-vous par spécialité, médecin et date
- Consultation de l’historique des rendez-vous et consultations
- Réception de notifications automatiques (email et tableau de bord)

### Pour les Médecins
- Connexion à un espace dédié
- Consultation, validation et gestion des rendez-vous
- Ajout de notes médicales et prescriptions (consultations)
- Gestion des disponibilités
- Visualisation de statistiques d’activité

### Pour les Administrateurs
- Interface de gestion complète de la plateforme
- Gestion des utilisateurs (patients et médecins)
- Gestion des spécialités médicales
- Suivi dynamique de l’activité via des statistiques
- Export PDF des consultations

---

## Technologies utilisées

- **Frontend** : HTML5, CSS3, Bootstrap, JavaScript (animations avec ScrollReveal, effets visuels)
- **Backend** : PHP (procédural ou orienté objet, sans framework)
- **Base de données** : MySQL (base `medical_app`)
- **Librairies et outils complémentaires** :
  - Chart.js (statistiques graphiques)
  - PHPMailer (envoi d’emails)
  - Font Awesome / Lucide (icônes)
  - ScrollReveal (animations d’apparition)

---

## Structure de la base de données

La base `medical_app` contient les principales tables suivantes :

- `utilisateurs` : gestion des comptes utilisateurs (patients, médecins, administrateurs)
- `patients` : informations spécifiques aux patients
- `medecins` : informations spécifiques aux médecins et leur spécialité
- `specialites` : liste des spécialités médicales
- `disponibilites` : créneaux horaires disponibles des médecins
- `rendez_vous` : gestion des rendez-vous entre patients et médecins
- `consultations` : notes et ordonnances associées aux rendez-vous
- `notifications` : messages de notification pour les utilisateurs

---

## Installation

1. **Cloner le dépôt :**

git clone https://github.com/Abdouramanebichara/medecin_app.git
cd medical_app


2. **Importer la base de données :**

- Utilisez le fichier SQL fourni (`medical_app.sql`) pour créer la base et les tables.
- Exemple avec MySQL en ligne de commande :

  ```
  mysql -u root -p < medical_app.sql
  ```

3. **Configurer la connexion à la base :**

- Modifier le fichier de configuration PHP (ex. `config.php`) avec vos paramètres MySQL (hôte, utilisateur, mot de passe, nom de la base).

4. **Déployer les fichiers sur un serveur web compatible PHP (Apache, Nginx).**

5. **Accéder à l’application via un navigateur :**

- URL locale : `http://localhost/medical_app/` ou selon votre configuration.

---

## Sécurité

- Mots de passe stockés avec `password_hash()` (algorithme bcrypt)
- Contrôle d’accès basé sur les rôles (patient, médecin, administrateur)
- Protection CSRF sur les formulaires sensibles
- Validation côté client (JavaScript) et côté serveur (PHP)
- Gestion des erreurs et messages clairs pour l’utilisateur

---

## Design et expérience utilisateur

- Palette de couleurs élégante (bleu nuit, or pâle, blanc éclatant)
- Polices modernes et lisibles (Poppins, DM Sans)
- Animations douces et effets de scroll innovants (ScrollReveal)
- Interface responsive mobile-first
- Tableau de bord déstructuré mais fonctionnel pour une navigation intuitive

---

## Améliorations futures envisagées

- Intégration d’un système de téléconsultation audio/vidéo
- Paiement en ligne sécurisé (Mobile Money, Stripe)
- Développement d’une application mobile (Flutter, React Native)
- Intelligence artificielle pour recommandations personnalisées de rendez-vous

---

## Livrables

- Code source complet (PHP, CSS, JS, SQL)
- Export de la base de données (`medical_app.sql`)
- Manuel utilisateur (PDF)
- Documentation technique (PDF)
- Captures d’écran des interfaces principales

---

## Contact

Pour toute question ou contribution, merci de contacter :

- **Nom** : ABDOURAMANE BICHARA 23I00403 GI2 FI1 
- **Email** : abdouramanebichara@gmail.com  
- **Projet** : Application Web de Gestion des Rendez-vous Médicaux

---

*Merci d’utiliser cette application et de contribuer à l’amélioration des services médicaux numériques !*

