<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

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
$ruolo = (string) ($_SESSION['ruolo'] ?? 'user');

$categorie = $categorieDefault;

$flashError = '';
$flashOk = '';
if (!empty($_SESSION['mt_flash_ok'])) {
    $flashOk = (string) $_SESSION['mt_flash_ok'];
    unset($_SESSION['mt_flash_ok']);
}
if (!empty($_SESSION['mt_flash_err'])) {
    $flashError = (string) $_SESSION['mt_flash_err'];
    unset($_SESSION['mt_flash_err']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete') {
        $expenseId = (int) ($_POST['expense_id'] ?? 0);
        if ($expenseId <= 0) {
            $flashError = 'Spesa non valida.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM spese WHERE id = :id AND user_id = :uid');
            $stmt->execute([':id' => $expenseId, ':uid' => $userId]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['mt_flash_ok'] = 'Spesa eliminata.';
                header('Location: manage_expenses.php');
                exit;
            }
            $flashError = 'Impossibile eliminare questa spesa.';
        }
    } elseif ($action === 'update') {
        $expenseId = (int) ($_POST['expense_id'] ?? 0);
        $descrizione = trim((string) ($_POST['descrizione'] ?? ''));
        $importoRaw = str_replace(',', '.', (string) ($_POST['importo'] ?? ''));
        $importo = (float) $importoRaw;
        $categoria = trim((string) ($_POST['categoria'] ?? ''));
        $data = trim((string) ($_POST['data'] ?? ''));

        if ($expenseId <= 0) {
            $_SESSION['mt_flash_err'] = 'Spesa non valida.';
            header('Location: manage_expenses.php');
            exit;
        }
        if ($descrizione === '' || $categoria === '' || $data === '') {
            $_SESSION['mt_flash_err'] = 'Compila tutti i campi.';
            header('Location: manage_expenses.php?edit=' . $expenseId . '#modifica');
            exit;
        }
        if ($importo <= 0) {
            $_SESSION['mt_flash_err'] = 'L’importo deve essere maggiore di zero.';
            header('Location: manage_expenses.php?edit=' . $expenseId . '#modifica');
            exit;
        }
        $allowCats = $categorieDefault;
        $stmtPrev = $pdo->prepare('SELECT categoria FROM spese WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmtPrev->execute([':id' => $expenseId, ':uid' => $userId]);
        $prevRow = $stmtPrev->fetch();
        if ($prevRow) {
            $pc = (string) $prevRow['categoria'];
            if (!in_array($pc, $allowCats, true)) {
                $allowCats[] = $pc;
            }
        }
        if (!in_array($categoria, $allowCats, true)) {
            $_SESSION['mt_flash_err'] = 'Seleziona una categoria valida.';
            header('Location: manage_expenses.php?edit=' . $expenseId . '#modifica');
            exit;
        }
        $own = $pdo->prepare('SELECT id FROM spese WHERE id = :id AND user_id = :uid LIMIT 1');
        $own->execute([':id' => $expenseId, ':uid' => $userId]);
        if (!$own->fetch()) {
            $_SESSION['mt_flash_err'] = 'Spesa non trovata.';
            header('Location: manage_expenses.php');
            exit;
        }
        $stmt = $pdo->prepare(
            'UPDATE spese
             SET descrizione = :d, importo = :i, categoria = :c, data = :dt
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            ':d' => $descrizione,
            ':i' => $importo,
            ':c' => $categoria,
            ':dt' => $data,
            ':id' => $expenseId,
            ':uid' => $userId,
        ]);
        $_SESSION['mt_flash_ok'] = 'Spesa aggiornata.';
        header('Location: manage_expenses.php');
        exit;
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editing = null;
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, descrizione, importo, categoria, data
         FROM spese WHERE id = :id AND user_id = :uid LIMIT 1'
    );
    $stmt->execute([':id' => $editId, ':uid' => $userId]);
    $editing = $stmt->fetch() ?: null;
    if ($editing === null) {
        $flashError = 'Spesa non trovata.';
    }
}

$categorieSelect = $categorie;
if ($editing !== null) {
    $curCat = (string) $editing['categoria'];
    if (!in_array($curCat, $categorieSelect, true)) {
        $categorieSelect[] = $curCat;
    }
}

$listStmt = $pdo->prepare(
    'SELECT id, descrizione, importo, categoria, data
     FROM spese
     WHERE user_id = :uid
     ORDER BY data DESC, id DESC'
);
$listStmt->execute([':uid' => $userId]);
$spese = $listStmt->fetchAll();

$h = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};

$navActive = 'manage';

$catSlug = static function (string $cat) use ($categoryTheme): string {
    return $categoryTheme[$cat] ?? 'custom';
};
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione spese – MoneyTracker</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="theme-light">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="page">
        <section class="page-head">
            <h1>Gestione spese</h1>
            <p class="muted">Modifica o elimina le voci già registrate. Per nuove spese usa <a href="add_expense.php">Aggiungi spesa</a>.</p>
        </section>

        <?php if ($flashError !== ''): ?>
            <p class="alert alert-error"><?php echo $h($flashError); ?></p>
        <?php endif; ?>
        <?php if ($flashOk !== ''): ?>
            <p class="alert alert-success"><?php echo $h($flashOk); ?></p>
        <?php endif; ?>

        <?php if ($editing !== null): ?>
            <section class="card card-edit" id="modifica">
                <h2>Modifica spesa</h2>
                <form method="post" action="manage_expenses.php" class="stack-form">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="expense_id" value="<?php echo (int) $editing['id']; ?>">

                    <label for="descrizione">Descrizione</label>
                    <input type="text" id="descrizione" name="descrizione" maxlength="255" required
                           value="<?php echo $h((string) $editing['descrizione']); ?>">

                    <label for="importo">Importo (€)</label>
                    <input type="number" step="0.01" min="0.01" id="importo" name="importo" required
                           value="<?php echo $h(number_format((float) $editing['importo'], 2, '.', '')); ?>">

                    <label for="categoria">Categoria</label>
                    <select id="categoria" name="categoria" required>
                        <?php foreach ($categorieSelect as $cat): ?>
                            <option value="<?php echo $h($cat); ?>"
                                <?php echo ((string) $editing['categoria'] === $cat) ? 'selected' : ''; ?>>
                                <?php echo $h($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="data">Data</label>
                    <input type="date" id="data" name="data" required
                           value="<?php echo $h((string) $editing['data']); ?>">

                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">Salva modifiche</button>
                        <a class="btn btn-secondary" href="manage_expenses.php">Annulla</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
        <section class="card">
            <div class="section-title">
                <h2>Elenco spese</h2>
                <span class="muted small"><?php echo count($spese); ?> voci</span>
            </div>

            <?php if (count($spese) === 0): ?>
                <p class="muted">Nessuna spesa. <a href="add_expense.php">Aggiungi la prima</a>.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Data</th>
                                <th scope="col">Descrizione</th>
                                <th scope="col">Categoria</th>
                                <th scope="col" class="num">Importo</th>
                                <th scope="col" class="actions">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($spese as $s): ?>
                                <?php $slug = $catSlug((string) $s['categoria']); ?>
                                <tr class="row-cat row-cat--<?php echo $h($slug); ?>">
                                    <td class="nowrap"><?php echo $h((string) $s['data']); ?></td>
                                    <td><?php echo $h((string) $s['descrizione']); ?></td>
                                    <td>
                                        <span class="badge-cat badge-cat--<?php echo $h($slug); ?>">
                                            <?php echo $h((string) $s['categoria']); ?>
                                        </span>
                                    </td>
                                    <td class="num strong"><?php echo number_format((float) $s['importo'], 2, ',', '.'); ?> €</td>
                                    <td class="actions">
                                        <a class="btn btn-small btn-secondary" href="manage_expenses.php?edit=<?php echo (int) $s['id']; ?>#modifica">Modifica</a>
                                        <form method="post" action="manage_expenses.php" class="inline-delete"
                                              onsubmit="return confirm('Eliminare questa spesa?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?php echo (int) $s['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-small">Elimina</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
</body>
</html>
