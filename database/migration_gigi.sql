-- ============================================================
--  Estudos GiGi — Migration: tema + foto de perfil
--  Execute no phpMyAdmin em: Banco studytracker > Importar
-- ============================================================

USE studytracker;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS theme      VARCHAR(20) DEFAULT 'dark'  AFTER avatar_color,
    ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL  AFTER theme;
