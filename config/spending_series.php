<?php
declare(strict_types=1);
function mt_spending_daily_series(PDO $pdo, int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $stmt = $pdo->prepare(
        'SELECT data, COALESCE(SUM(importo), 0) AS t
         FROM spese
         WHERE user_id = :u AND data BETWEEN :d1 AND :d2
         GROUP BY data'
    );
    $stmt->execute([
        ':u' => $userId,
        ':d1' => $from->format('Y-m-d'),
        ':d2' => $to->format('Y-m-d'),
    ]);

    $byDay = [];
    while ($row = $stmt->fetch()) {
        $byDay[(string) $row['data']] = (float) $row['t'];
    }

    $isoDay = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    $labels = [];
    $values = [];
    $cursor = $from;
    while ($cursor <= $to) {
        $key = $cursor->format('Y-m-d');
        $values[] = $byDay[$key] ?? 0.0;
        $n = (int) $cursor->format('N');
        $labels[] = $isoDay[$n - 1] . ' ' . $cursor->format('j/n');
        $cursor = $cursor->modify('+1 day');
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'total' => array_sum($values),
    ];
}

function mt_spending_monthly_year_series(PDO $pdo, int $userId, int $year): array
{
    $from = sprintf('%04d-01-01', $year);
    $to = sprintf('%04d-12-31', $year);
    $stmt = $pdo->prepare(
        'SELECT MONTH(data) AS m, COALESCE(SUM(importo), 0) AS t
         FROM spese
         WHERE user_id = :u AND data BETWEEN :d1 AND :d2
         GROUP BY MONTH(data)'
    );
    $stmt->execute([':u' => $userId, ':d1' => $from, ':d2' => $to]);

    $byMonth = [];
    while ($row = $stmt->fetch()) {
        $byMonth[(int) $row['m']] = (float) $row['t'];
    }

    $mesi = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
    $labels = [];
    $values = [];
    for ($m = 1; $m <= 12; $m++) {
        $labels[] = $mesi[$m - 1];
        $values[] = $byMonth[$m] ?? 0.0;
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'total' => array_sum($values),
    ];
}

function mt_breakdown_chart_rows(array $rowsOrdered, float $periodTotal, array $categoryTheme): array
{
    // Prima troviamo il valore piu' alto: la barra piu' grande diventa il riferimento visivo.
    $max = 0.0;
    foreach ($rowsOrdered as $r) {
        if ($r['tot'] > $max) {
            $max = $r['tot'];
        }
    }

    $items = [];
    foreach ($rowsOrdered as $r) {
        if ($r['tot'] <= 0) {
            continue;
        }
        $slug = $categoryTheme[$r['categoria']] ?? 'custom';
        $items[] = [
            'categoria' => $r['categoria'],
            'tot' => $r['tot'],
            'slug' => $slug,
            'pct' => $periodTotal > 0 ? round(($r['tot'] / $periodTotal) * 100, 1) : 0.0,
            'bar' => $max > 0 ? min(100.0, ($r['tot'] / $max) * 100.0) : 0.0,
        ];
    }

    return $items;
}
