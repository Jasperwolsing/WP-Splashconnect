# WP Splashconnect — Installatiegids

## Bestandsstructuur

```
wp-splashconnect/
├── wp-splashconnect.php          ← hoofdbestand plugin
├── includes/
│   ├── class-cpt-athlete.php       ← custom post type + meta velden
│   ├── class-pdf-importer.php      ← PDF → Claude → athlete posts
│   ├── class-flickr-client.php     ← Flickr foto zoeken
│   └── class-bio-generator.php     ← webzoek + Claude bio
├── admin/
│   └── admin-menu.php              ← admin pagina's
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

## Installatie

1. Upload de map `wp-splashconnect` naar `/wp-content/plugins/`
2. Activeer de plugin via **WordPress Admin → Plugins**
3. Ga naar **WP Splashconnect → Instellingen** en voer je API-sleutels in

## Benodigde API-sleutels

### 1. Anthropic (Claude) — verplicht
- Ga naar https://console.anthropic.com
- Maak een API-sleutel aan onder "API Keys"
- Wordt gebruikt voor: PDF-parsing + bio genereren

### 2. Flickr — verplicht voor foto's
- Ga naar https://www.flickr.com/services/apps/create/apply/
- Maak een "Non-Commercial" app aan
- Kopieer de **API Key** (niet de Secret)
- Wordt gebruikt voor: zoeken op naam in de stream van kjvoverbeeke

### 3. Serper.dev — optioneel (verbetert bio-kwaliteit)
- Ga naar https://serper.dev
- Gratis tier = 2.500 zoekopdrachten/maand
- Wordt gebruikt voor: webzoekresultaten als context voor de bio

## Workflow

1. **Importeer PDF** → WP Splashconnect → Importeer PDF
   - Upload de inschrijflijst
   - Claude analyseert het document en maakt athlete-posts aan als 'concept'

2. **Verrijk atleten** → WP Splashconnect → Verrijk atleten
   - Klik per atleet op "Verrijk" of gebruik "Alles verrijken"
   - Per atleet: Flickr-foto wordt gezocht + als uitgelichte afbeelding ingesteld
   - Bio wordt gegenereerd en opgeslagen als post-content + meta veld `sta_bio`

3. **Publiceer** → ga naar Atleten in het menu en publiceer de gewenste posts

## Meta-velden (beschikbaar via shortcodes / Elementor / ACF-compatible)

| Meta-sleutel           | Inhoud                                       |
|------------------------|----------------------------------------------|
| `sta_club`             | Naam van de zwemvereniging                   |
| `sta_events`           | JSON-array met afstanden: `["100m Vrije slag"]` |
| `sta_bio`              | AI-gegenereerde bio (platte tekst)           |
| `sta_flickr_photo_url` | Directe URL naar Flickr-foto                 |
| `sta_flickr_photo_id`  | Flickr foto-ID                               |
| `sta_flickr_photo_page`| URL naar de Flickr-fotopagina                |

## Kosten schatting (per 100 atleten)

| Dienst    | Gebruik                     | Geschatte kosten |
|-----------|-----------------------------|------------------|
| Claude    | PDF parsing + 100 bio's     | ~$0,50–$1,50     |
| Flickr    | 100 zoekopdrachten          | Gratis           |
| Serper    | 200 zoekopdrachten          | Gratis tier      |

## Flickr tag-formaat

De plugin zoekt op tags gevormd als: `voornaamachternaam` (lowercase, geen spaties).
Bijv. voor "Jan de Vries" → zoekt op tag `jandevries`.

Als de fotograaf andere tag-conventies gebruikt, pas dan `find_photo_for_athlete()` aan
in `includes/class-flickr-client.php`.
