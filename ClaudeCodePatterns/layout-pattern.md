# Layout Pattern

## Dateien

| Quelle | Ziel |
|---|---|
| `ClaudeCodePatterns/layout.php` | `views/layout.php` |
| `ClaudeCodePatterns/app.css`    | `public/css/app.css` |

Beide Dateien 1:1 kopieren -- nie neu generieren.

---

## Struktur

```
.app-wrapper (flex column, 100vh)
│
├── .app-header          flex-shrink: 0  -- immer sichtbar
│
├── .app-above-table     flex-shrink: 0  -- EIN Block, kein Border innen
│   ├── .toolbar-toggle  (nur schmale Devices)
│   ├── .app-toolbar     (optional -- weglassen wenn keine Filter)
│   └── .app-page-header (Seitentitel + Badge)
│
├── .app-main            flex: 1         -- NUR Tabelle, padding: 0
│
├── .app-pager           flex-shrink: 0  -- optional
│
└── .app-footer          flex-shrink: 0  -- immer sichtbar
```

## Schluessel-Regeln

1. `.app-main` hat `padding: 0` -- sonst Luecke beim sticky Header
2. `.app-above-table` ist EIN Block -- kein Border zwischen Toolbar und Page-Header
3. `thead th` hat `border-top: none` -- sonst Luecke beim Hochscrollen
4. Kein `overflow-x` Wrapper um Tabellen -- `.app-main` scrollt bereits
5. Aktions-Spalte IMMER erste Spalte (links)

---

## Controller-Muster

```php
public function index(): void
{
    $result = \api_post('/adressen/getAdressen', [
        'fields'  => ['kennziffer', 'name2', 'name1', 'ort'],
        'orderby' => 'name2',
    ]);

    // Toolbar (optional)
    $toolbar = '
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm-6 col-lg-auto">
            <label class="filter-label">Suche</label>
            <input type="text" class="form-control form-control-sm" name="suche">
        </div>
        <div class="col-12 col-sm-6 col-lg-auto ms-lg-auto">
            <a href="' . APP_BASE . '/adressen/neu"
               class="btn btn-sm fw-semibold w-100"
               style="background:var(--app-gold);color:var(--app-text-dark);border:none;">
                <i class="bi bi-plus-lg me-1"></i>Neue Adresse
            </a>
        </div>
    </div>';

    // Page-Header
    $page_header = '
    <h1 class="h5 fw-bold mb-0" style="color:var(--app-text-dark);">
        Adressen
    </h1>
    <span class="badge rounded-pill"
          style="background:var(--app-gold-light);color:var(--app-text-dark);">
        ' . count($result['data'] ?? []) . ' Eintraege
    </span>';

    // Content: nur Tabelle, kein Wrapper-Div mit overflow
    $rows = '';
    foreach ($result['data'] ?? [] as $row) {
        $rows .= '<tr>
            <td class="act">
                <a href="' . APP_BASE . '/adressen/bearbeiten?kennziffer=' . $row['kennziffer'] . '"
                   class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem;margin-right:.2rem;">
                    <i class="bi bi-pencil"></i>
                </a>
            </td>
            <td class="dim">' . htmlspecialchars($row['kennziffer']) . '</td>
            <td>' . htmlspecialchars($row['name2'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['name1'] ?? '') . '</td>
            <td>' . htmlspecialchars($row['ort'] ?? '') . '</td>
        </tr>';
    }

    $content = '
    <table class="app-table" style="min-width:700px;">
        <thead>
            <tr>
                <th>Aktionen</th>
                <th>Nr.</th>
                <th>Name</th>
                <th>Vorname</th>
                <th>Ort</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
    </table>';

    $this->render('adressen/index', [
        'page_title'  => 'Adressen',
        'toolbar'     => $toolbar,
        'page_header' => $page_header,
        'content'     => $content,
    ]);
}
```

---

## Toolbar-Controls (Bootstrap Grid)

```html
<div class="row g-2 align-items-end">
    <!-- 1 pro Zeile unter 576px, 2 nebeneinander ab 576px, auto ab 992px -->
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label">Von</label>
        <input type="date" class="form-control form-control-sm">
    </div>
    <div class="col-12 col-sm-6 col-lg-auto">
        <label class="filter-label">Bis</label>
        <input type="date" class="form-control form-control-sm">
    </div>
    <!-- ms-lg-auto schiebt Aktions-Button nach rechts auf breiten Devices -->
    <div class="col-12 col-sm-6 col-lg-auto ms-lg-auto">
        <button class="btn btn-sm fw-semibold w-100"
                style="background:var(--app-gold);color:var(--app-text-dark);border:none;">
            <i class="bi bi-plus-lg me-1"></i>Neu
        </button>
    </div>
</div>
```

---

## Seitentypen

| Typ | toolbar | page_header | pager |
|---|---|---|---|
| Startseite | leer | leer | leer |
| Liste ohne Paging | gesetzt | gesetzt | leer |
| Liste mit Paging | gesetzt | gesetzt | gesetzt |
| Formular/Detail | leer | gesetzt | leer |

---

## Tabellen-Regeln

- Klasse `app-table` -- nie Bootstrap `table-striped`
- Kein `<div style="overflow-x:auto">` um die Tabelle
- `style="min-width:NNNpx"` direkt auf `<table>`
- Aktions-Spalte IMMER erste Spalte
- Keine Icons in Datenspalten
- Keine Badges in Datenspalten (nur `app-status` fuer echte Status-Felder)
- Hilfsklassen: `act` (Aktionen), `dim` (gedaempfte Werte wie ID/Kennziffer)

---

## Theming

Nur den `:root` Block in `public/css/app.css` anpassen:

```css
:root {
    --app-gold:        #C8960C;   /* Hauptfarbe */
    --app-gold-dark:   #A07808;   /* Dunklere Variante */
    --app-gold-light:  #F5C842;   /* Helle Variante */
    --app-text-dark:   #2C1C00;   /* Text auf Hauptfarbe */
    --app-border:      #F0E8CC;   /* Rahmenfarbe */
    --app-bg-warm:     #FFFDF0;   /* Hintergrund Toolbar/Hover */
}
```

Prompt fuer Claude Code:
```
Passe das Farbschema in public/css/app.css an. Hauptfarbe: [Farbe oder Beschreibung]
```
