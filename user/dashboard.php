<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/spending_series.php';

$categorieDefault = require __DIR__ . '/../config/categorie.php';
if (!is_array($categorieDefault)) {
    $categorieDefault = [];
}

$categoryTheme = require __DIR__ . '/../config/category_theme.php';
if (!is_array($categoryTheme)) {
    $categoryTheme = [];
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$username = (string) $_SESSION['username'];
$ruolo = (string) ($_SESSION['ruolo'] ?? 'user');

$categorieMerged = $categorieDefault;

function spendMapByCategory(PDO $pdo, int $userId, string $dateFrom, string $dateTo): array
{
    $stmt = $pdo->prepare(
        'SELECT categoria, COALESCE(SUM(importo), 0) AS tot
         FROM spese
         WHERE user_id = :uid AND data BETWEEN :df AND :dt
         GROUP BY categoria'
    );
    $stmt->execute([':uid' => $userId, ':df' => $dateFrom, ':dt' => $dateTo]);
    $map = [];
    while ($row = $stmt->fetch()) {
        $map[(string) $row['categoria']] = (float) $row['tot'];
    }

    return $map;
}

function buildBreakdownOrdered(array $categorieNota, array $map): array
{
    // Partiamo dalle categorie ufficiali, poi aggiungiamo solo quelle gia' presenti nei dati storici.
    $merged = [];

    foreach ($categorieNota as $nome) {
        $merged[(string) $nome] = $map[(string) $nome] ?? 0.0;
    }

    foreach ($map as $cat => $tot) {
        if (!array_key_exists($cat, $merged)) {
            $merged[(string) $cat] = (float) $tot;
        }
    }

    $rows = [];
    foreach ($merged as $categoria => $tot) {
        $rows[] = ['categoria' => $categoria, 'tot' => $tot];
    }

    usort($rows, static function (array $a, array $b): int {
        $byTot = $b['tot'] <=> $a['tot'];

        return $byTot !== 0 ? $byTot : strcasecmp((string) $a['categoria'], (string) $b['categoria']);
    });

    return $rows;
}

$today = new DateTimeImmutable('today');
$dow = (int) $today->format('N');
$weekStart = $today->modify('-' . ($dow - 1) . ' days');
$weekEnd = $weekStart->modify('+6 days');
$rangeWeek = [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')];

$monthStart = $today->modify('first day of this month');
$monthEnd = $today->modify('last day of this month');
$rangeMonth = [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')];

$y = (int) $today->format('Y');
$yearStart = new DateTimeImmutable(sprintf('%04d-01-01', $y));
$yearEnd = new DateTimeImmutable(sprintf('%04d-12-31', $y));
$rangeYear = [$yearStart->format('Y-m-d'), $yearEnd->format('Y-m-d')];

$mapWeek = spendMapByCategory($pdo, $userId, $rangeWeek[0], $rangeWeek[1]);
$mapMonth = spendMapByCategory($pdo, $userId, $rangeMonth[0], $rangeMonth[1]);
$mapYear = spendMapByCategory($pdo, $userId, $rangeYear[0], $rangeYear[1]);

$breakWeek = buildBreakdownOrdered($categorieMerged, $mapWeek);
$breakMonth = buildBreakdownOrdered($categorieMerged, $mapMonth);
$breakYear = buildBreakdownOrdered($categorieMerged, $mapYear);

$totalWeek = array_sum($mapWeek);
$totalMonth = array_sum($mapMonth);
$totalYear = array_sum($mapYear);

$seriesWeek = mt_spending_daily_series($pdo, $userId, $weekStart, $weekEnd);
$seriesMonth = mt_spending_daily_series($pdo, $userId, $monthStart, $monthEnd);
$seriesYear = mt_spending_monthly_year_series($pdo, $userId, $y);

$rangeLabelWeek = sprintf(
    '%s – %s',
    $weekStart->format('d/m/Y'),
    $weekEnd->format('d/m/Y')
);

$mesiIt = [
    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
];
$monthLabelIt = $mesiIt[(int) $monthStart->format('n')] . ' ' . $monthStart->format('Y');

$chartPayload = [
    'week' => [
        'periodTitle' => 'Settimana corrente',
        'periodRange' => $rangeLabelWeek,
        'series' => $seriesWeek,
        'breakdown' => mt_breakdown_chart_rows($breakWeek, $totalWeek, $categoryTheme),
    ],
    'month' => [
        'periodTitle' => 'Mese corrente',
        'periodRange' => $monthLabelIt,
        'series' => $seriesMonth,
        'breakdown' => mt_breakdown_chart_rows($breakMonth, $totalMonth, $categoryTheme),
    ],
    'year' => [
        'periodTitle' => 'Anno corrente',
        'periodRange' => (string) $y,
        'series' => $seriesYear,
        'breakdown' => mt_breakdown_chart_rows($breakYear, $totalYear, $categoryTheme),
    ],
];

$totalStmt = $pdo->prepare('SELECT COALESCE(SUM(importo), 0) AS tot FROM spese WHERE user_id = :uid');
$totalStmt->execute([':uid' => $userId]);
$totalSpesoStorico = (float) ($totalStmt->fetch()['tot'] ?? 0);

$allCatStmt = $pdo->prepare(
    'SELECT categoria, COALESCE(SUM(importo), 0) AS tot
     FROM spese
     WHERE user_id = :uid
     GROUP BY categoria'
);
$allCatStmt->execute([':uid' => $userId]);
$mapAlltime = [];
while ($row = $allCatStmt->fetch()) {
    $mapAlltime[(string) $row['categoria']] = (float) $row['tot'];
}
$breakAlltime = buildBreakdownOrdered($categorieMerged, $mapAlltime);

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM spese WHERE user_id = :uid');
$countStmt->execute([':uid' => $userId]);
$nSpese = (int) ($countStmt->fetch()['c'] ?? 0);

$h = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$chartJson = '{}';
try {
    $chartJson = json_encode(
        $chartPayload,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS
    );
} catch (\JsonException $e) {
    $chartJson = '{}';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – MoneyTracker</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="theme-light">
    <?php
    $navActive = 'dashboard';
    require __DIR__ . '/_nav.php';
    ?>

    <main class="page">
        <section class="page-head">
            <h1>Ciao, <?php echo $h($username); ?></h1>
            <p class="muted">Andamento delle spese nel periodo scelto e riparto per categoria. Per modificare le voci: <a href="manage_expenses.php">Gestione spese</a>.</p>
        </section>

        <section class="page-section">
            <div class="card chart-card">
                <div class="chart-head">
                    <h2 class="chart-title">Andamento spese</h2>
                    <div class="chart-controls">
                        <label for="chart-period">Periodo</label>
                        <select id="chart-period" name="chart_period" aria-label="Scegli il periodo del grafico">
                            <option value="week">Settimana</option>
                            <option value="month">Mese</option>
                            <option value="year">Anno</option>
                        </select>
                    </div>
                </div>
                <p class="muted small chart-sub" id="chart-period-desc" aria-live="polite"></p>
                <div class="chart-wrap">
                    <canvas id="chart-spese" aria-label="Grafico lineare delle spese"></canvas>
                </div>
                <h3 class="chart-breakdown-title">Spese per categoria (stesso periodo)</h3>
                <div id="chart-breakdown" class="chart-breakdown"></div>
            </div>
        </section>

        <section class="page-section">
            <h2 class="page-section-title">Storico complessivo</h2>
            <div class="grid-2">
            <article class="card">
                <h2>Riepilogo storico</h2>
                <p class="lead">
                    Movimenti registrati: <strong><?php echo $nSpese; ?></strong>
                </p>
                <p class="muted small">
                    Totale da sempre: <strong><?php echo number_format($totalSpesoStorico, 2, ',', '.'); ?> €</strong>
                </p>
                <?php if ($totalSpesoStorico <= 0): ?>
                    <p class="muted small">Aggiungi spese per vedere la ripartizione per categoria.</p>
                <?php else: ?>
                    <p class="muted small">Ripartizione complessiva per categoria</p>
                    <ul class="category-list compact" aria-label="Storico per categoria">
                        <?php
                        $maxAll = 0.0;
                        foreach ($breakAlltime as $r) {
                            if ($r['tot'] > $maxAll) {
                                $maxAll = $r['tot'];
                            }
                        }
                        ?>
                        <?php foreach ($breakAlltime as $r): ?>
                            <?php if ($r['tot'] <= 0) {
                                continue;
                            } ?>
                            <?php
                            $pct = $totalSpesoStorico > 0 ? ($r['tot'] / $totalSpesoStorico) * 100.0 : 0.0;
                            $w = $maxAll > 0 ? ($r['tot'] / $maxAll) * 100.0 : 0.0;
                            $slug = $categoryTheme[$r['categoria']] ?? 'custom';
                            ?>
                            <li class="category-row cat-<?php echo $h($slug); ?>">
                                <div class="category-row-head">
                                    <span class="badge-cat badge-cat--<?php echo $h($slug); ?>"><?php echo $h($r['categoria']); ?></span>
                                    <span class="category-amount"><?php echo number_format($r['tot'], 2, ',', '.'); ?> €</span>
                                </div>
                                <div class="category-meta muted small"><?php echo number_format($pct, 1, ',', '.'); ?>% del totale</div>
                                <div class="mini-bar-track" role="presentation">
                                    <div class="mini-bar-fill mini-bar-fill--<?php echo $h($slug); ?> mini-bar-fill--soft" style="width: <?php echo $h((string) min(100, max(0, $w))); ?>%;"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>

            <article class="card card-cta">
                <h2>Gestione spese</h2>
                <p class="muted small">
                    Correggi importi, date, categorie o elimina voci dalla pagina dedicata.
                </p>
                <p class="cta-row">
                    <a class="btn btn-primary" href="manage_expenses.php">Gestione spese</a>
                    <a class="btn btn-secondary" href="add_expense.php">Nuova spesa</a>
                </p>
            </article>
            </div>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script>
        window.MT_DASHBOARD_CHART = <?php echo $chartJson; ?>;
    </script>
    <script src="../js/dashboard-chart.js" defer></script>
</body>
</html>
