<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['ruolo'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$adminId = (int) $_SESSION['user_id'];
$flashError = '';
$flashOk = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_non_admin'])) {
        $stmt = $pdo->prepare('DELETE FROM utenti WHERE ruolo <> :admin');
        $stmt->execute([':admin' => 'admin']);
        $flashOk = 'Eliminati tutti gli utenti non admin.';
    } elseif (isset($_POST['admin_action'], $_POST['target_user'])) {
        $action = (string) $_POST['admin_action'];
        $targetId = (int) $_POST['target_user'];

        if ($targetId <= 0) {
            $flashError = 'Utente non valido.';
        } elseif ($action === 'delete_user') {
            if ($targetId === $adminId) {
                $flashError = 'Non puoi eliminare il tuo account admin mentre sei collegato.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM utenti WHERE id = :id AND id <> :aid');
                $stmt->execute([':id' => $targetId, ':aid' => $adminId]);
                if ($stmt->rowCount() > 0) {
                    $flashOk = 'Utente eliminato.';
                } else {
                    $flashError = 'Eliminazione non riuscita.';
                }
            }
        } elseif ($action === 'reset_password') {
            $newPlain = '1234@';
            $hash = password_hash($newPlain, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE utenti SET password = :p WHERE id = :id');
            $stmt->execute([':p' => $hash, ':id' => $targetId]);
            if ($stmt->rowCount() > 0) {
                $flashOk = 'Password aggiornata a "1234@".';
            } else {
                $flashError = 'Reset password non riuscito.';
            }
        }
    }
}

$countStmt = $pdo->query('SELECT COUNT(*) AS c FROM utenti');
$totalUsers = (int) ($countStmt->fetch()['c'] ?? 0);

$userStmt = $pdo->query('SELECT id, username, ruolo FROM utenti ORDER BY username ASC');
$users = $userStmt->fetchAll();

$h = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – MoneyTracker</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="theme-light">
    <header class="navbar">
        <div class="navbar-brand">MoneyTracker · Admin</div>
        <nav class="navbar-links" aria-label="Menu principale">
            <a href="../user/dashboard.php">Dashboard</a>
            <a href="../user/add_expense.php">Aggiungi spesa</a>
            <a href="../user/manage_expenses.php">Gestione spese</a>
            <a class="active" href="admin.php">Admin</a>
            <a href="../auth/logout.php" class="logout-link">Esci</a>
            <button type="button" id="theme-toggle" class="btn btn-ghost" aria-label="Cambia tema">Tema</button>
        </nav>
    </header>

    <main class="page">
        <section class="page-head">
            <h1>Pannello amministratore</h1>
            <p class="muted">Gestione utenti e statistiche globali.</p>
        </section>

        <?php if ($flashError !== ''): ?>
            <p class="alert alert-error"><?php echo $h($flashError); ?></p>
        <?php endif; ?>
        <?php if ($flashOk !== ''): ?>
            <p class="alert alert-success"><?php echo $h($flashOk); ?></p>
        <?php endif; ?>

        <section class="card">
            <h2>Statistiche</h2>
            <p class="lead">Utenti registrati: <strong><?php echo $totalUsers; ?></strong></p>
        </section>

        <section class="card">
            <h2>Azioni su utente selezionato</h2>

            <form method="post" action="admin.php" class="stack-form">
                <label for="target_user">Utente</label>
                <select id="target_user" name="target_user" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>">
                            <?php echo $h($u['username']); ?> (<?php echo $h($u['ruolo']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="button-row">
                    <button type="submit" name="admin_action" value="delete_user"
                            class="btn btn-danger"
                            onclick="return confirm('Eliminare definitivamente questo utente?');">
                        Elimina utente
                    </button>
                    <button type="submit" name="admin_action" value="reset_password"
                            class="btn btn-secondary"
                            onclick="return confirm('Resettare la password di questo utente a 1234@ ?');">
                        Reset password (1234@)
                    </button>
                </div>
            </form>
        </section>

        <section class="card danger-zone">
            <h2>Zona pericolosa</h2>
            <form method="post" action="admin.php"
                  onsubmit="return confirm('Eliminare TUTTI gli utenti che non sono admin? Operazione irreversibile.');">
                <input type="hidden" name="delete_non_admin" value="1">
                <button type="submit" class="btn btn-danger">Elimina tutti gli utenti non admin</button>
            </form>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
</body>
</html>
