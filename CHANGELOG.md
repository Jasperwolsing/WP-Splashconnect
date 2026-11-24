# Changelog

## 1.3.0
- Sjablonen worden nu door de plugin zelf geladen (map `templates/`): atleetpagina, atletenoverzicht, afstandspagina (`/afstand/...`), clubpagina (`/club/...`) en paginasjabloon "Club overzicht". Een bestand met dezelfde naam in het (child)thema blijft voorrang houden.
- Beveiliging: de vaste Heroshot-HMAC-sleutel is uit de code gehaald. Stel `STA_HEROSHOT_SECRET` in via wp-config.php (of optie `sta_heroshot_secret`).
- Heroshot-video-URL gebruikt het domein van de site in plaats van een vast domein.
- Verouderd `taxonomy-afstand.php` uit de pluginroot verwijderd.

## 1.2.0
- Nieuwe hoofdpagina **Overzicht** met aan/uit-schakelaars per module.
- Optionele modules: Foto-integratie, Heroshot video, Share card, Livestream, Naam opnamen, Speakers. Uitgeschakeld = uit het menu en geen hooks, AJAX of shortcodes.
- Foto-integratie uit: "Foto zoeken" en de foto-knop bij Verrijk atleten zijn uitgeschakeld en de Flickr-instellingen verborgen (opgeslagen waarden blijven behouden).
- Altijd actief: SwimRankings import, Programma, Atleten, Verrijk atleten, Pagina-opmaak, Instellingen.

## 1.1.0
- Plugin hernoemd van "Swimming Trials AI Athletes" naar **WP Splashconnect** (map/bestand: `wp-splashconnect`).
- Pluginpad in de sharecard-generator niet meer hardcoded.
- Eerste publicatie in een GitHub-repository.
- Automatische updates via GitHub Releases (`includes/class-github-updater.php`).
- GitHub Action bouwt bij een tag `vX.Y.Z` de zip en publiceert de release.

## 1.0.5
- Laatste versie onder de naam Swimming Trials AI Athletes.
