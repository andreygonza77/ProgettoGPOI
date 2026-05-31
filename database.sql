-- Schema MySQL di MoneyTracker.
-- Dopo l'import, visita una volta install.php per impostare l'admin con password hashata.

CREATE DATABASE IF NOT EXISTS moneytracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE moneytracker;

CREATE TABLE IF NOT EXISTS utenti (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    ruolo ENUM('user','admin') NOT NULL DEFAULT 'user',
    PRIMARY KEY (id),
    UNIQUE KEY uq_utenti_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spese (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    descrizione VARCHAR(255) NOT NULL,
    importo DECIMAL(10,2) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    data DATE NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_spese_user_data (user_id, data),
    CONSTRAINT fk_spese_utente FOREIGN KEY (user_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Placeholder: install.php lo sostituisce con l'hash reale di admin123.
INSERT INTO utenti (username, password, ruolo) VALUES (
    'admin',
    '$2y$10$12345678901234567890121234567890123456789012345678901',
    'admin'
);
