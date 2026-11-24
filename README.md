# WP Splashconnect

WordPress-plugin voor zwemwedstrijden (oorspronkelijk gebouwd voor DutchQualifiers.nl / Swimming Trials). Eerder bekend als *Swimming Trials AI Athletes*.

## Functies

- Atleten importeren uit een PDF-inschrijflijst of CSV (via Claude)
- Foto's zoeken op Flickr en automatisch koppelen
- AI-gegenereerde bio's, optioneel verrijkt met webzoekresultaten (Serper.dev)
- Programma, startlijsten en uitslagen (SwimRankings-koppeling)
- Sharecards, heroshots, naamopnames en livestream-beheer
- Ticker met uitgelichte atleten, spreker-rol en shortcodes

## Installatie

1. Download de nieuwste `wp-splashconnect.zip` onder [Releases](https://github.com/Jasperwolsing/WP-Splashconnect/releases).
2. WordPress-admin: **Plugins → Nieuwe plugin → Plugin uploaden**.
3. Activeer en vul de API-sleutels in onder **WP Splashconnect → Instellingen**.

Zie [INSTALLATIE.md](INSTALLATIE.md) voor de API-sleutels en de workflow.

## Vereisten

- WordPress 6.0+, PHP 8.0+
- Anthropic API-sleutel (verplicht), Flickr API-sleutel (voor foto's), Serper.dev (optioneel)

## Let op bij upgraden vanaf "Swimming Trials AI Athletes"

De pluginmap en het hoofdbestand zijn hernoemd (`swimming-trials-ai` → `wp-splashconnect`). Deactiveer en verwijder de oude plugin, installeer daarna deze versie. Opgeslagen instellingen en atleten blijven behouden, want de interne database-prefix `sta_` is bewust ongewijzigd.

## Ontwikkelen

Zie [CONTRIBUTING.md](CONTRIBUTING.md). Wijzigingen staan in [CHANGELOG.md](CHANGELOG.md).

## Licentie

GPL-3.0-or-later (zie [LICENSE](LICENSE)). Meegeleverde lettertypen (DejaVu Sans, Roboto) vallen onder hun eigen licenties.
