<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ../user/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Inserisci username e password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password, ruolo FROM utenti WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ruolo'] = $user['ruolo'];

            header('Location: ../user/dashboard.php');
            exit;
        }

        $error = 'Credenziali non valide.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – MoneyTracker</title>
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
            <h1>Accedi</h1>

            <?php if ($error !== ''): ?>
                <p class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form method="post" action="login.php" id="login-form" class="stack-form" autocomplete="on">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required maxlength="50"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

                <label class="checkbox-row">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    Ricordami (salva solo lo username nel browser)
                </label>

                <button type="submit" class="btn btn-primary">Entra</button>
            </form>

            <p class="muted small">
                Non hai un account? <a href="register.php">Registrati</a>
            </p>
        </section>
    </main>

    <script src="../js/script.js" defer></script>
  </body>
</html>