<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ../user/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_confirm'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Compila tutti i campi obbligatori.';
    } elseif ($password !== $password2) {
        $error = 'Le password non coincidono.';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere lunga almeno 6 caratteri.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO utenti (username, password, ruolo) VALUES (:username, :password, \'user\')'
            );
            $stmt->execute([
                ':username' => $username,
                ':password' => $hash,
            ]);
            $success = 'Registrazione completata. Ora puoi effettuare il login.';
        } catch (PDOException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                $error = 'Username già in uso.';
            } else {
                $error = 'Errore durante la registrazione. Riprova più tardi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione – MoneyTracker</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="theme-light">
    <header class="site-header">
        <div class="site-header-row">
            <div>
                <div class="brand">MoneyTracker</div>
                <div class="tagline">Gestione Spese e Risparmio</div>
            </div>
            <button type="button" id="theme-toggle" class="btn btn-ghost" aria-label="Cambia tema">Tema</button>
        </div>
    </header>

    <main class="auth-layout">
        <section class="card auth-card">
            <h1>Crea account</h1>

            <?php if ($error !== ''): ?>
                <p class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <p class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
                <p><a class="btn btn-secondary" href="login.php">Vai al login</a></p>
            <?php else: ?>
                <form method="post" action="register.php" class="stack-form" autocomplete="off">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required maxlength="50"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="6">

                    <label for="password_confirm">Ripeti password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="6">

                    <button type="submit" class="btn btn-primary">Registrati</button>
                </form>

                <p class="muted small">
                    Hai già un account? <a href="login.php">Accedi</a>
                </p>
            <?php endif; ?>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
</body>
</html>