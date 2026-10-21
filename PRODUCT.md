# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Direction + enseignants d'école** : deux publics premiers, avec espaces distincts (`/ecole` et `/enseignant`).
  - Direction / gestionnaires / directeurs d'études / secrétariat : gèrent classes, élèves, enseignants, affectations, tituliats, bulletins, calendrier, pilotage.
  - Enseignants / titulaires : encodent les notes (feuilles de notes, import Excel), suivent la progression, le titulaire supervise sa classe.
- **SUPER_ADMIN plateforme** : admin séparé (onboarding et validation des écoles côté `/platform`), pas un public premier.
- Multi-tenant : une école (ou groupe d'écoles) = un espace ; une identité humaine = un numéro de téléphone vérifié, « mes espaces » relie N appartenance sans dupliquer les comptes.

## Product Purpose

Suite de gestion scolaire complète, de bout en bout : élèves et inscriptions (import Excel), classes, affectations et tituliats, encodage des notes (saisie + Excel), suivi de progression, bulletins et proclamations, calendrier scolaire, alertes de pilotage.

Succès : **l'école opère entièrement dans le produit** — de l'élève inscrit au bulletin édité — sans outils annexes (tableur bricolé, Word recopié, papier).

## Positioning

- **Isolation stricte multi-écoles décidée côté serveur** : 1 token = 1 tenant, le client n'envoie jamais son périmètre (`TenantContext`) ; le téléphone prouve qui l'on est, le tenant borne ce que l'on voit.
- **Identité unique, appartenance multiples** : rattachement d'un même numéro à plusieurs espaces et rôles sans dupliquer les comptes, bascule d'espace sans reconnexion.
- **Excel-native et format-officiel** : ce que l'école utilise déjà (tableur, modèles officiels RDC) entre et sort sans ressaisie ; catalogue de templates, layouts et barèmes aux référentiels officiels (trimestriel/semestriel, Humanités, Éducation de base, primaire).

## Operating Context

- **Référentiel observable** : écoles francophones sur le modèle officiel RDC — calendrier `RDC_2026_2027`, templates « Bulletin Officiel Secondaire RDC », téléphones normalisés E.164 `+243…`. UI intégralement en français.
- **Workflows** : inscription courte d'espace → construction descendante (admin → établissements → gestionnaires → enseignants → affectations) ; premier login avec changement forcé de mot de passe ; notes par période (trimestres/semestres) ; bulletins périodiques ; import d'élèves plafonné à 200 lignes ; rattachement de rôle additif en gestion des membres.
- **Profils mixtes** : un membre peut encadrer (direction/encadrement) et enseigner ; les deux espaces coexistent sur le même compte.
- **Développement** : Laravel Herd (`bulletinlab.test`), Vite + React/TypeScript, Windows.

## Capabilities and Constraints

- **Espaces et rôles** : plateforme (`/platform` : SUPER_ADMIN) ; école (`/ecole` : ADMIN_TENANT/ADMIN_ECOLE, GESTIONNAIRE, DIRECTEUR_ETUDES, SECRETARIAT) ; enseignant (`/enseignant` : ENSEIGNANT, TITULAIRE). Rôles gérés par Spatie.
- **Règles dures confirmées** : seul le titulaire d'une classe en voit bulletins et progression ; seul l'enseignant affecté saisit les notes (titulaire en lecture seule) ; les cartes de cours des autres enseignants sont en lecture seule (« Suivi titulaire », « Assuré par X »).
- **Rattachement vs mise à jour** : `POST /utilisateurs/rattacher` ajoute un rôle (additif) ; la mise à jour de membre remplace les rôles (`syncRoles`).
- **Terminologie** : espace, tenant, établissement, affectation (attribution cours), titulaire, feuille de notes, bulletin, proclamation, rattachement, « mes espaces ».
- **Décision ouverte — plans et paiement** : plans seedés STARTER / STANDARD / PREMIUM avec plafonds (300 / 1000 / illimité élèves…), mais **le paiement n'est pas un moment prioritaire du produit** : ne pas affirmer d'offre commerciale, de devise ni de politique tarifaire.
- **Décision ouverte — rôle PREFET** : prévu dans le modèle cible V2 (`AUTHENTIFICATION_ROLES_V2.md`) mais absent du code aujourd'hui.

## Brand Commitments

- **Nom** : BulletinLab (README, seeders `BulletinLab*`).
- **Voix** : français, vouvoiement, libellés courts et concrets (« Ajouter un rôle », « Assuré par… », « Un profil mixte suit l'encadrement ») ; ton opérationnel et administratif, pas promotionnel.
- Aucun logo, actif visuel ni charte n'est établi dans le dépôt.

## Evidence on Hand

- `AUTHENTIFICATION_ROLES_V2.md` : modèle d'authentification/rôles validé (philosophie téléphone + tenant, matrice de permissions).
- `README.md`, `PHASE_1..5_*.md`, `PLAN_*.md` : packaging, moteur de bulletins, encodage réel, PDF proclamation, PWA offline, architecture — statut de réalisation à vérifier avant de s'y fier.
- Seeders de données officielles : `BulletinLabTemplateCatalogSeeder`, `BulletinLabLayoutsSeeder`, `BulletinLabBaremesSeeder`, `BulletinLabCommercialeSeeder` (matières/maxima extraits d'un PDF officiel).
- Preuves de comportement : 527 tests Pest (backend), 308 tests Vitest (frontend).
- **Absences à ne pas fabriquer** : aucun témoignage, client, cas d'étude, presse, benchmark, prix affiché, ni actif de marque. Aucun `DESIGN.md` (monde visuel non documenté).

## Product Principles

1. **Le serveur décide du périmètre** — jamais le client ; l'isolation par tenant prime sur la convenance.
2. **Le flux complet d'abord** — chaque fonction rapproche du parcours élève → note → bulletin ; pas de silo qui force un outil annexe.
3. **Les deux espaces sont premiers** — `/ecole` et `/enseignant` avancent ensemble, le profil mixte (encadrement + enseignement) est la norme à supporter.
4. **Le pont avec le réel est Excel et le format officiel** — ce que l'école utilise déjà doit entrer et sortir sans ressaisie.
5. **Rattacher plutôt que dupliquer** — identités, comptes, rôles et espaces se relient, ne se recréent pas.
