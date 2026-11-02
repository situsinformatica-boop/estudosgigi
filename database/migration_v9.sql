-- ============================================================
--  Estudos GiGi — Migration v9
--  1. Renomeia banco (se ainda for studytracker)
--  2. Adiciona duration_sec para precisão em segundos
--  3. Garante compatibilidade com horas contabilizadas
-- ============================================================

-- Se seu banco ainda se chama 'studytracker', execute:
-- RENAME DATABASE studytracker TO estudos_gigi;
-- (No WAMP/phpMyAdmin: crie o banco 'estudos_gigi' manualmente,
--  exporte o studytracker e importe no estudos_gigi)

USE estudos_gigi;

-- Adiciona coluna de duração em segundos (mais precisa)
-- Evita o problema de sessões < 1 min não serem contabilizadas
ALTER TABLE study_sessions
    ADD COLUMN IF NOT EXISTS duration_sec INT GENERATED ALWAYS AS
        (TIMESTAMPDIFF(SECOND, started_at, ended_at)) STORED AFTER duration_min;

-- Índice para queries de relatório por data
CREATE INDEX IF NOT EXISTS idx_sessions_user_date
    ON study_sessions(user_id, started_at, ended_at);

-- Garante que user_stats tenha total_minutes correto
-- (recalcula a partir das sessões reais)
UPDATE user_stats us
SET us.total_minutes = (
    SELECT COALESCE(SUM(GREATEST(1, CEIL(duration_sec / 60))), 0)
    FROM study_sessions ss
    WHERE ss.user_id = us.user_id AND ss.ended_at IS NOT NULL
),
us.total_sessions = (
    SELECT COUNT(*) FROM study_sessions ss
    WHERE ss.user_id = us.user_id AND ss.ended_at IS NOT NULL
);
