# Samlade Nyheter - PHP Nyhetssida

En enkel och elegant PHP-baserad nyhetssida som samlar nyheter från flera svenska och internationella källor samt visar väderprognos för utvalda orter.

## Funktioner

- **Nyhetsaggregering**: Hämtar nyheter från DN, Sveriges Radio, DR, TV2 Bornholm och BBC News
- **Väderprognos**: Visar aktuellt väder för Åkarp, Västervik och Rønne via SMHI's API
- **Responsiv design**: Fungerar bra på både desktop och mobil
- **Auto-uppdatering**: Sidan uppdateras automatiskt var 15:e minut
- **Modern design**: Ren och tillgänglig design med Font Awesome ikoner

## Installation

1. **Ladda upp filerna** till din webbserver som stödjer PHP
2. **Kontrollera PHP-version**: Kräver PHP 7.4 eller senare
3. **Aktivera nödvändiga PHP-moduler**:
   - `curl` eller `allow_url_fopen`
   - `json`
   - `xml`
   - `dom`

## Filstruktur

```
/
├── index.php              # Huvudsidan
├── config.php             # Konfigurationsinställningar
├── styles.css             # CSS-styling
├── classes/
│   ├── NewsScraper.php    # Klass för att hämta nyheter
│   └── WeatherService.php # Klass för väderdata
└── README.md              # Denna fil
```

## Konfiguration

Redigera `config.php` för att anpassa:

- Nyhetskällor (aktivera/inaktivera)
- Väderplatser och koordinater
- Timeout-värden
- Antal nyheter som visas
- Debug-inställningar

## Felsökning

### Inga nyheter visas
- Kontrollera att `allow_url_fopen` är aktiverat i PHP
- Alternativt installera och aktivera cURL-modulen
- Kontrollera brandväggar som kan blockera utgående anslutningar

### Väderdata saknas
- SMHI's API kan ibland vara nedlagt för underhåll
- Kontrollera att koordinaterna i config.php är korrekta
- Kontrollera nätverksanslutning till opendata-download-metfcst.smhi.se

### Prestandaproblem
- Implementera caching (se config.php)
- Minska antalet nyhetskällor
- Öka timeout-värden om nätverket är långsamt

## Anpassningar

### Lägga till nya nyhetskällor
1. Uppdatera `$sources` array i `NewsScraper.php`
2. Lägg till specifik scraping-logik om RSS inte fungerar
3. Testa noggrant med den nya källan

### Ändra väderplatser
1. Redigera `weather_locations` i `config.php`
2. Hitta koordinater på [SMHI's sajt](https://opendata.smhi.se/)

### Anpassa design
1. Redigera `styles.css`
2. Ändra färgschema i CSS-variabler
3. Anpassa responsive breakpoints

## Tekniska detaljer

- **RSS-parsing**: Försöker först RSS/XML, faller tillbaka på HTML-scraping
- **Felhantering**: Loggar fel till PHP error log
- **Säkerhet**: Använder `htmlspecialchars()` för att förhindra XSS
- **Performance**: Begränsar antal nyheter och använder timeouts

## Webbläsarstöd

- Chrome/Edge 80+
- Firefox 75+
- Safari 13+
- IE inte stödt

## Licens

Open source - använd fritt för personliga och kommersiella projekt.

## Support

För frågor eller problem, kontrollera PHP error log och aktivera debug-läge i config.php.
