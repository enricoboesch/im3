<?php
/**
 * Hotellerie Schweiz – Anzeige von hotel_daten.csv
 * Erwartet die CSV im selben Ordner wie diese Datei.
 * Filter per GET: ?jahr=2026 | ?kanton=Graubünden | &sort=beds&dir=desc
 */

declare(strict_types=1);

const CSV_DATEI = __DIR__ . '/hotel_daten.csv';

// Französische Kantonsnamen (2006–2007) auf die deutschen Namen (ab 2008) vereinheitlichen
const KANTON_ALIAS = [
    'Fribourg'  => 'Freiburg',
    'Genève'    => 'Genf',
    'Neuchâtel' => 'Neuenburg',
    'Ticino'    => 'Tessin',
    'Valais'    => 'Wallis',
    'Vaud'      => 'Waadt',
];

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** "3'961" → 3961, "44.8%" → 44.8 */
function zahl(string $wert): float
{
    return (float) str_replace(["'", '’', '%', ' '], '', trim($wert));
}

function ladeDaten(string $pfad): array
{
    if (!is_readable($pfad)) {
        return [];
    }
    $fh = fopen($pfad, 'r');
    // UTF-8-BOM überspringen
    if (fread($fh, 3) !== "\xEF\xBB\xBF") {
        rewind($fh);
    }
    $kopf = fgetcsv($fh, 0, ',', '"', '\\');
    $zeilen = [];
    while (($z = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        if (count($z) < 5) {
            continue;
        }
        $r = array_combine($kopf, $z);
        $kanton = trim($r['canton']);
        $zeilen[] = [
            'year'   => (int) $r['year'],
            'canton' => KANTON_ALIAS[$kanton] ?? $kanton,
            'hotels' => zahl($r['registered_hotels']),
            'beds'   => zahl($r['available_beds']),
            'occ'    => zahl($r['bed_occupancy']),
        ];
    }
    fclose($fh);
    return $zeilen;
}

$daten = ladeDaten(CSV_DATEI);

$jahre    = array_values(array_unique(array_column($daten, 'year')));
rsort($jahre);
$kantone  = array_values(array_unique(array_column($daten, 'canton')));
sort($kantone, SORT_LOCALE_STRING);
$kantone  = array_values(array_diff($kantone, ['Schweiz']));

// Eingaben prüfen
$kanton = isset($_GET['kanton']) && in_array($_GET['kanton'], $kantone, true) ? $_GET['kanton'] : '';
$jahr   = isset($_GET['jahr']) && in_array((int) $_GET['jahr'], $jahre, true) ? (int) $_GET['jahr'] : ($jahre[0] ?? 0);
$spalten = ['year' => 'Jahr', 'canton' => 'Kanton', 'hotels' => 'Betriebe', 'beds' => 'Betten', 'occ' => 'Auslastung'];
$sort   = isset($_GET['sort']) && isset($spalten[$_GET['sort']]) ? $_GET['sort'] : ($kanton ? 'year' : 'occ');
$dir    = ($_GET['dir'] ?? ($kanton ? 'desc' : 'desc')) === 'asc' ? 'asc' : 'desc';

// Ansicht: ein Kanton über alle Jahre ODER alle Kantone eines Jahres
if ($kanton !== '') {
    $zeilen = array_values(array_filter($daten, fn($r) => $r['canton'] === $kanton));
    $total  = null;
    $titel  = $kanton . ', ' . min($jahre) . '–' . max($jahre);
} else {
    $jahrDaten = array_filter($daten, fn($r) => $r['year'] === $jahr);
    $total     = current(array_filter($jahrDaten, fn($r) => $r['canton'] === 'Schweiz')) ?: null;
    $zeilen    = array_values(array_filter($jahrDaten, fn($r) => $r['canton'] !== 'Schweiz'));
    $titel     = 'Alle Kantone, ' . $jahr;
}

usort($zeilen, function ($a, $b) use ($sort, $dir) {
    $c = is_string($a[$sort]) ? strcoll($a[$sort], $b[$sort]) : $a[$sort] <=> $b[$sort];
    return $dir === 'asc' ? $c : -$c;
});

$maxOcc = $daten ? max(array_column($daten, 'occ')) : 100;

function tausend(float $n): string
{
    return number_format($n, 0, '.', '’');
}

function sortLink(string $key, string $label, string $sort, string $dir, int $jahr, string $kanton): string
{
    $neuDir = ($sort === $key && $dir === 'desc') ? 'asc' : 'desc';
    $q = ['sort' => $key, 'dir' => $neuDir];
    $kanton !== '' ? $q['kanton'] = $kanton : $q['jahr'] = $jahr;
    $pfeil = $sort === $key ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $aria  = $sort === $key ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th scope="col" aria-sort="' . $aria . '"' . (in_array($key, ['year', 'canton']) ? '' : ' class="num"') . '>'
        . '<a href="?' . e(http_build_query($q)) . '">' . e($label) . $pfeil . '</a></th>';
}
?>
<!DOCTYPE html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hotellerie Schweiz – <?= e($titel) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --fels: #2b3440;      /* Text */
            --schnee: #f7f9fb;    /* Hintergrund */
            --eis: #dce6ee;       /* Linien, Balkenspur */
            --gletscher: #3f7f99; /* Auslastungsbalken */
            --flagge: #c8102e;    /* einziger Akzent: Schweiz-Total */
            --grau: #6b7785;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font: 400 16px/1.5 "Public Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--fels);
            background: var(--schnee);
        }
        main { max-width: 960px; margin: 0 auto; padding: 2.5rem 1.25rem 4rem; }
        h1 { font-weight: 800; font-size: clamp(1.8rem, 4vw, 2.6rem); line-height: 1.1; margin: 0 0 .35rem; letter-spacing: -.01em; }
        .untertitel { color: var(--grau); margin: 0 0 2rem; }

        form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; margin-bottom: 1.75rem; }
        label { display: grid; gap: .3rem; font-size: .85rem; color: var(--grau); }
        select, button {
            font: inherit; font-size: 1rem; color: var(--fels);
            padding: .5rem .75rem; border: 1px solid #b9c6d1; border-radius: 6px; background: #fff;
        }
        button { background: var(--fels); color: #fff; border-color: var(--fels); cursor: pointer; }
        :focus-visible { outline: 3px solid var(--gletscher); outline-offset: 2px; }
        .zurueck { font-size: .9rem; color: var(--gletscher); }

        .total {
            display: flex; flex-wrap: wrap; gap: .5rem 2rem; align-items: baseline;
            border-left: 4px solid var(--flagge); padding: .75rem 1rem; margin-bottom: 1.5rem; background: #fff;
        }
        .total strong { font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .total span { color: var(--grau); font-size: .9rem; }

        .tabelle { overflow-x: auto; background: #fff; border: 1px solid var(--eis); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        th, td { padding: .6rem .9rem; text-align: left; white-space: nowrap; }
        th { font-size: .85rem; font-weight: 600; border-bottom: 2px solid var(--eis); position: sticky; top: 0; background: #fff; }
        th a { color: var(--fels); text-decoration: none; }
        th a:hover { text-decoration: underline; }
        td { border-bottom: 1px solid var(--eis); }
        tr:last-child td { border-bottom: 0; }
        .num { text-align: right; }
        td a { color: inherit; text-decoration-color: var(--eis); text-underline-offset: 3px; }
        td a:hover { text-decoration-color: var(--gletscher); }

        .occ { display: flex; align-items: center; gap: .75rem; justify-content: flex-end; }
        .balken { width: 140px; height: 8px; background: var(--eis); border-radius: 4px; overflow: hidden; }
        .balken i { display: block; height: 100%; background: var(--gletscher); }
        .quelle { margin-top: 1.25rem; font-size: .85rem; color: var(--grau); }

        @media (max-width: 560px) { .balken { width: 70px; } th, td { padding: .5rem .6rem; } }
    </style>
</head>
<body>
<main>
    <h1>Hotellerie Schweiz</h1>
    <p class="untertitel"><?= e($titel) ?></p>

    <?php if (!$daten): ?>
        <p>Die Datei <code>hotel_daten.csv</code> wurde nicht gefunden. Lege sie in denselben Ordner wie diese PHP-Datei.</p>
    <?php else: ?>
        <form method="get">
            <label>Jahr
                <select name="jahr" <?= $kanton ? 'disabled' : '' ?>>
                    <?php foreach ($jahre as $j): ?>
                        <option value="<?= $j ?>" <?= $j === $jahr ? 'selected' : '' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kanton
                <select name="kanton">
                    <option value="">Alle Kantone</option>
                    <?php foreach ($kantone as $k): ?>
                        <option value="<?= e($k) ?>" <?= $k === $kanton ? 'selected' : '' ?>><?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Anzeigen</button>
            <?php if ($kanton): ?><a class="zurueck" href="?">Alle Kantone anzeigen</a><?php endif; ?>
        </form>

        <?php if ($total): ?>
            <div class="total">
                <div><strong><?= tausend($total['hotels']) ?></strong> <span>Betriebe</span></div>
                <div><strong><?= tausend($total['beds']) ?></strong> <span>Betten</span></div>
                <div><strong><?= number_format($total['occ'], 1, '.', '') ?>&thinsp;%</strong> <span>Bettenauslastung Schweiz</span></div>
            </div>
        <?php endif; ?>

        <div class="tabelle">
            <table>
                <thead>
                <tr>
                    <?php
                    $zeigen = $kanton ? ['year', 'hotels', 'beds', 'occ'] : ['canton', 'hotels', 'beds', 'occ'];
                    foreach ($zeigen as $key) {
                        echo sortLink($key, $spalten[$key], $sort, $dir, $jahr, $kanton);
                    }
                    ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($zeilen as $r): ?>
                    <tr>
                        <?php if ($kanton): ?>
                            <td><?= $r['year'] ?></td>
                        <?php else: ?>
                            <td><a href="?kanton=<?= urlencode($r['canton']) ?>"><?= e($r['canton']) ?></a></td>
                        <?php endif; ?>
                        <td class="num"><?= tausend($r['hotels']) ?></td>
                        <td class="num"><?= tausend($r['beds']) ?></td>
                        <td class="num">
                            <div class="occ">
                                <?= number_format($r['occ'], 1, '.', '') ?>&thinsp;%
                                <span class="balken" aria-hidden="true"><i style="width:<?= round($r['occ'] / $maxOcc * 100, 1) ?>%"></i></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="quelle"><?= count($daten) ?> Datensätze aus hotel_daten.csv. Kantonsnamen 2006–2007 (Französisch/Italienisch) wurden auf die deutschen Namen vereinheitlicht.</p>
    <?php endif; ?>
</main>
</body>
</html>