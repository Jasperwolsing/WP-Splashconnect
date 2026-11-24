# Changelog

## 1.9.0
- Menu-item heeft nu een zwemmer-icoon (SVG, kleurt mee met het admin-kleurenschema). Het eerdere `dashicons-swimming` bestaat niet in WordPress, waardoor er geen icoon was.

## 1.8.2
- Paginacache (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache) wordt automatisch geleegd als je Pagina-opmaak, modules, programma-tijden/-namen of sharecard-instellingen opslaat. Hiermee verschijnen wijzigingen direct voor bezoekers.

## 1.8.1
- Updater betrouwbaarder: terugval op de website-redirect als de GitHub-API de aanvraag weigert (rate limit op gedeelde hosting), cache van 6 naar 1 uur, "Opnieuw controleren" in WordPress leegt nu ook de plugin-cache, en in de pluginlijst staat de link **Controleer op updates**.

## 1.8.0
- Pagina-opmaak, profielpagina: naam-badge (tekstkleur, tekstgrootte), vlag (vorm volgt de bestaande vlag-instelling, nieuwe grootte), landnaam (tonen/verbergen, kleur, grootte) en deel-knop (tekst, achtergrond, tekstkleur, afronding) zijn instelbaar. Standaardwaarden zijn gelijk aan het oude uiterlijk.
- De deel-knop op de profielpagina verschijnt alleen als de module Share card aan staat.

## 1.7.1
- Updater: een verouderd "nieuwe versie beschikbaar"-bericht voor de reeds geïnstalleerde versie (bijv. na handmatig uploaden) wordt niet meer getoond.

## 1.7.0
- Pagina-opmaak → Header: nieuwe optie "Headerbalk tonen bovenaan de pagina's" (standaard aan). Uit = geen blauwe balk/afbeelding op atleten-, afstands-, club- en clubs-overzichtspagina's.

## 1.6.0
- Programma: sessienamen zijn aan te passen (veld "Naam" per sessie; leeg = automatische naam). De eigen naam wordt overal getoond: programma-shortcode, afstandspagina (ook bij de uitslagenblokken), atleetpagina en uitslagen.

## 1.5.0
- Overzicht: kaart "Standaardpagina's" toont of Clubs (`/clubs/`) en Programma (`[sta_program]`) bestaan en maakt ontbrekende pagina's aan met één knop. Bestaande pagina's worden nooit gewijzigd.

## 1.4.1
- Foto-integratie uit: foto's en placeholders (🏊) worden nergens meer getoond: afstandspagina, atletenoverzicht, clubpagina, atleetpagina (incl. toernooifoto's) en de uitgelichte-atleten-ticker. De standaardtekst onderaan de atleetpagina noemt dan alleen de bio.

## 1.4.0
- Heroshot: de map voor `videos/` en `data/` is instelbaar bij Instellingen → Heroshot (standaard `DQR2026`), met statuscontrole. Vaste mappen `DQR2026/` en `EST2026/` zijn uit de code.
- Video op de atletenpagina verschijnt alleen als de Heroshot-module aan staat.

## 1.3.2
- Heroshot-sleutel is nu in te vullen bij Instellingen → Heroshot (wachtwoordveld, nooit voorgevuld; leeg laten behoudt de waarde). `STA_HEROSHOT_SECRET` in wp-config.php blijft voorrang houden.

## 1.3.1
- Geschiedenis en releases opgeschoond na het verwijderen van de vaste Heroshot-sleutel; geen functionele wijzigingen t.o.v. 1.3.0.

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
