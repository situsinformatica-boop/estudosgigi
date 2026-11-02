-- ============================================================
--  StudyTracker v3 — Schema completo com usuários
--  MySQL 5.7+ / MySQL 8.x / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS estudos_gigi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE estudos_gigi;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    avatar_color  VARCHAR(7)    DEFAULT '#6366f1',
    role          ENUM('user','admin') DEFAULT 'user',
    is_active     TINYINT(1)    DEFAULT 1,
    last_login    DATETIME,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_remember_token (token_hash),
    INDEX idx_remember_expiry (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS subjects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    name        VARCHAR(100)  NOT NULL,
    description TEXT,
    color       VARCHAR(7)    DEFAULT '#6366f1',
    icon        VARCHAR(50)   DEFAULT 'book',
    weekly_goal_minutes INT   DEFAULT 300,
    priority    TINYINT       DEFAULT 2,
    is_active   TINYINT(1)    DEFAULT 1,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subjects_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS topics (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    subject_id   INT           NOT NULL,
    name         VARCHAR(150)  NOT NULL,
    description  TEXT,
    difficulty   TINYINT       DEFAULT 2,
    progress     TINYINT       DEFAULT 0,
    order_index  INT           DEFAULT 0,
    is_completed TINYINT(1)    DEFAULT 0,
    created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_topics_subject (subject_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS study_sessions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    subject_id     INT           NOT NULL,
    topic_id       INT,
    started_at     DATETIME      NOT NULL,
    ended_at       DATETIME,
    duration_min   INT           GENERATED ALWAYS AS (TIMESTAMPDIFF(MINUTE, started_at, ended_at)) STORED,
    quality        TINYINT,
    notes          TEXT,
    session_type   ENUM('study','review','exercise','reading') DEFAULT 'study',
    pomodoros_done INT           DEFAULT 0,
    created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sessions_user    (user_id),
    INDEX idx_sessions_started (started_at),
    INDEX idx_sessions_date    (started_at, user_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id)   REFERENCES topics(id)   ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS pomodoro_config (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNIQUE NOT NULL,
    work_minutes       INT DEFAULT 25,
    short_break_min    INT DEFAULT 5,
    long_break_min     INT DEFAULT 15,
    cycles_before_long INT DEFAULT 4,
    auto_start_break   TINYINT(1) DEFAULT 0,
    auto_start_work    TINYINT(1) DEFAULT 0,
    sound_enabled      TINYINT(1) DEFAULT 1,
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS spaced_reviews (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT           NOT NULL,
    topic_id      INT           NOT NULL,
    subject_id    INT           NOT NULL,
    next_review   DATE          NOT NULL,
    interval_days INT           DEFAULT 1,
    ease_factor   DECIMAL(4,2)  DEFAULT 2.50,
    repetitions   INT           DEFAULT 0,
    last_quality  TINYINT,
    last_reviewed DATE,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_next (next_review),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (topic_id)   REFERENCES topics(id)   ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS goals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    title          VARCHAR(150)  NOT NULL,
    description    TEXT,
    subject_id     INT,
    type           ENUM('daily','weekly','monthly','custom') DEFAULT 'weekly',
    target_minutes INT           DEFAULT 0,
    start_date     DATE          NOT NULL,
    end_date       DATE          NOT NULL,
    is_completed   TINYINT(1)    DEFAULT 0,
    created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_goals_user (user_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_stats (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNIQUE NOT NULL,
    total_xp       INT DEFAULT 0,
    level          INT DEFAULT 1,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    total_sessions INT DEFAULT 0,
    total_minutes  INT DEFAULT 0,
    last_study_date DATE,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS achievements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(60)   NOT NULL UNIQUE,
    title           VARCHAR(100)  NOT NULL,
    description     VARCHAR(255),
    icon            VARCHAR(50)   DEFAULT 'trophy',
    xp_reward       INT           DEFAULT 50,
    condition_type  VARCHAR(50),
    condition_value INT
);

CREATE TABLE IF NOT EXISTS user_achievements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_ach (user_id, achievement_id),
    INDEX idx_achievements_user (user_id),
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS xp_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT           NOT NULL,
    amount       INT           NOT NULL,
    reason       VARCHAR(100)  NOT NULL,
    reference_id INT,
    earned_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_xp_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    key_name    VARCHAR(60)   PRIMARY KEY,
    value       TEXT,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Dados iniciais ───────────────────────────────────────────
INSERT IGNORE INTO settings (key_name, value) VALUES
('daily_goal_minutes','120'),('timezone','America/Sao_Paulo'),
('xp_per_minute','2'),('xp_per_session','30'),('xp_per_pomodoro','15'),
('xp_per_review','20'),('level_xp_base','500');

INSERT IGNORE INTO achievements (slug,title,description,icon,xp_reward,condition_type,condition_value) VALUES
('first_session','Primeiro Passo','Completou a primeira sessão','play',50,'total_sessions',1),
('streak_3','Em Ritmo','3 dias seguidos estudando','flame',100,'streak',3),
('streak_7','Uma Semana Forte','7 dias seguidos estudando','fire',250,'streak',7),
('streak_30','Mês Imparável','30 dias seguidos estudando','bolt',1000,'streak',30),
('hours_10','Dedicado','10 horas totais de estudo','clock',150,'total_hours',10),
('hours_50','Estudioso','50 horas totais de estudo','star',500,'total_hours',50),
('hours_100','Centurião','100 horas totais de estudo','medal',1000,'total_hours',100),
('sessions_10','Constância','10 sessões completadas','check',100,'total_sessions',10),
('sessions_50','Veterano','50 sessões completadas','award',400,'total_sessions',50),
('pomodoro_10','Tomate Fiel','10 pomodoros completados','tomato',100,'pomodoros',10),
('topic_complete','Dominador','Concluiu um tópico 100%','target',200,'topics_done',1),
('night_owl','Coruja','Estudou após meia-noite','moon',75,'night_session',1),
('early_bird','Madrugador','Estudou antes das 6h','sun',75,'early_session',1),
('review_7','Revisor','7 revisões espaçadas realizadas','refresh',150,'reviews_done',7);
