# BulletinLab Laravel Complete V3

Package complet Laravel/MySQL pour BulletinLab.

## Inclus

- Migrations complètes
- Models Eloquent
- Seeders de base
- Catalogues cycles/degrés/niveaux/options
- Layouts officiels
- Blocs spéciaux
- Calendrier scolaire exemple
- Catalogue des templates officiels connus
- Seeder détaillé Commerciale & Gestion 1ère à 4ème
- Services de base

## Seeders inclus

- `BulletinLabCoreSeeder`
- `BulletinLabAcademicSeeder`
- `BulletinLabLayoutsSeeder`
- `BulletinLabCalendarSeeder`
- `BulletinLabTemplateCatalogSeeder`
- `BulletinLabCommercialeSeeder`
- `BulletinLabBaremesSeeder`

## Important

Le seeder Commerciale & Gestion est détaillé avec les matières et maxima du PDF que tu as envoyé.

Les autres templates sont bien créés dans le catalogue, mais leurs matières complètes doivent encore être seedées de la même manière que `BulletinLabCommercialeSeeder`. La structure est prête pour ça.

## Installation

Copie les fichiers dans ton projet Laravel puis lance :

```bash
php artisan migrate
php artisan db:seed
```

## Prochaine étape recommandée

Créer des seeders détaillés par option :

- `BulletinLabPrimaireSeeder`
- `BulletinLabCtebSeeder`
- `BulletinLabScientifiqueSeeder`
- `BulletinLabPedagogieSeeder`
- `BulletinLabElectriciteSeeder`
- `BulletinLabConstructionSeeder`
- `BulletinLabMecaniqueSeeder`

La structure actuelle supporte déjà tout ça.
