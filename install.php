<?php
declare(strict_types=1);

// Serve solo al primo avvio: crea o aggiorna l'admin con una password hashata.

require __DIR__ . '/config/db.php';

$plain = 'admin123';
$hash = password_hash($plain, PASSWORD_DEFAULT);

$upd = $pdo->prepare('UPDATE utenti SET password = :p WHERE username = :u LIMIT 1');
$upd->execute([':p' => $hash, ':u' => 'admin']);

if ($upd->rowCount() === 0) {
    $ins = $pdo->prepare(
        'INSERT INTO utenti (username, password, ruolo) VALUES (:username, :password, :ruolo)'
    );
    $ins->execute([
        ':username' => 'admin',
        ':password' => $hash,
        ':ruolo' => 'admin',
    ]);
}

header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Installazione MoneyTracker</title></head><body>';
echo '<p>Installazione completata: utente <strong>admin</strong> aggiornato con password <strong>admin123</strong>.</p>';
echo '<p><strong>Elimina ora il file install.php</strong> dalla cartella del progetto.</p>';
echo '</body></html>';
