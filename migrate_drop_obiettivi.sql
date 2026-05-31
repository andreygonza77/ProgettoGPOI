-- Opzionale: esegui su database già esistenti se non usi più la tabella obiettivi.
-- Le nuove installazioni possono usare database.sql senza questa tabella.

USE moneytracker;

DROP TABLE IF EXISTS obiettivi;
