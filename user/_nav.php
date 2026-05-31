<?php
declare(strict_types=1);

$navActive = $navActive ?? 'dashboard';
$ruolo = $ruolo ?? 'user';

$h = static function (?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
?>
<header class="navbar">
    <div class="navbar-brand">MoneyTracker</div>
    <nav class="navbar-links" aria-label="Menu principale">
        <a class="<?php echo $navActive === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
        <a class="<?php echo $navActive === 'add' ? 'active' : ''; ?>" href="add_expense.php">Aggiungi spesa</a>
        <a class="<?php echo $navActive === 'manage' ? 'active' : ''; ?>" href="manage_expenses.php">Gestione spese</a>
        <?php if ($ruolo === 'admin'): ?>
            <a href="../admin/admin.php">Admin</a>
        <?php endif; ?>
        <a href="../auth/logout.php" class="logout-link">Esci</a>
        <button type="button" id="theme-toggle" class="btn btn-ghost" aria-label="Cambia tema">Tema</button>
    </nav>
</header>
