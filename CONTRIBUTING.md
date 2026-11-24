# Bijdragen

- Werk op een branch en open een pull request naar `main`.
- Zet nooit API-sleutels, wachtwoorden of persoonsgegevens van atleten in de code of in commits. Sleutels staan alleen in de WordPress-instellingen.
- Controleer syntax met `php -l bestand.php`.
- Houd de interne prefix `sta_` / `STA_` aan tot er een migratie is gemaakt; hernoemen zonder migratie breekt bestaande sites.
- Een release: verhoog `Version` in `wp-splashconnect.php` en `STA_VERSION`, werk `CHANGELOG.md` bij, tag `vX.Y.Z` en publiceer een GitHub Release.
