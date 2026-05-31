<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

$categorieDefault = require __DIR__ . '/../config/categorie.php';
if (!is_array($categorieDefault)) {
    $categorieDefault = [];
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$ruolo = (string) ($_SESSION['ruolo'] ?? 'user');

$categorie = $categorieDefault;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descrizione = trim((string) ($_POST['descrizione'] ?? ''));
    $importoRaw = str_replace(',', '.', (string) ($_POST['importo'] ?? ''));
    $importo = (float) $importoRaw;
    $categoria = trim((string) ($_POST['categoria'] ?? ''));
    $data = trim((string) ($_POST['data'] ?? ''));

    if ($descrizione === '' || $categoria === '' || $data === '') {
        $error = 'Compila tutti i campi obbligatori.';
    } elseif ($importo <= 0) {
        $error = 'L’importo deve essere maggiore di zero.';
    } elseif (!in_array($categoria, $categorie, true)) {
        $error = 'Seleziona una categoria valida.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO spese (descrizione, importo, categoria, data, user_id)
             VALUES (:d, :i, :c, :dt, :uid)'
        );
        $stmt->execute([
            ':d' => $descrizione,
            ':i' => $importo,
            ':c' => $categoria,
            ':dt' => $data,
            ':uid' => $userId,
        ]);
        $success = 'Spesa registrata correttamente.';
    }
}

$h = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuova spesa – MoneyTracker</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="theme-light">
    <?php
    $navActive = 'add';
    require __DIR__ . '/_nav.php';
    ?>

    <main class="page narrow">
        <section class="card">
            <h1>Nuova spesa</h1>

            <?php if ($error !== ''): ?>
                <p class="alert alert-error"><?php echo $h($error); ?></p>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <p class="alert alert-success"><?php echo $h($success); ?></p>
            <?php endif; ?>

            <form method="post" action="add_expense.php" class="stack-form">
                <label for="descrizione">Descrizione</label>
                <input type="text" id="descrizione" name="descrizione" maxlength="255" required
                       value="<?php echo $h($_POST['descrizione'] ?? ''); ?>">

                <label for="importo">Importo (€)</label>
                <input type="number" step="0.01" min="0.01" id="importo" name="importo" required
                       value="<?php echo $h($_POST['importo'] ?? ''); ?>">

                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria" required>
                    <option value="">— seleziona —</option>
                    <?php foreach ($categorie as $cat): ?>
                        <option value="<?php echo $h($cat); ?>"
                            <?php echo (isset($_POST['categoria']) && $_POST['categoria'] === $cat) ? 'selected' : ''; ?>>
                            <?php echo $h($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="data">Data</label>
                <input type="date" id="data" name="data" required
                       value="<?php echo $h($_POST['data'] ?? date('Y-m-d')); ?>">

                <button type="submit" class="btn btn-primary">Salva spesa</button>
            </form>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
</body>
</html>
