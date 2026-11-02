-- ============================================================
--  Estudos GiGi — Migration: Agenda, Flashcards, Caderno, Post-its
--  Execute no phpMyAdmin: Banco studytracker > Importar
-- ============================================================
USE studytracker;

-- ── AGENDA (substituiu metas) ────────────────────────────────
CREATE TABLE IF NOT EXISTS agenda_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    title       VARCHAR(200)  NOT NULL,
    description TEXT,
    study_date  DATE          NOT NULL,
    subject_id  INT,
    start_time  TIME,
    end_time    TIME,
    priority    TINYINT       DEFAULT 2,   -- 1=baixa 2=média 3=alta
    is_done     TINYINT(1)    DEFAULT 0,
    color       VARCHAR(7)    DEFAULT '#6366f1',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agenda_user_date (user_id, study_date),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

-- ── FLASHCARDS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS flashcard_decks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    description TEXT,
    subject_id  INT,
    color       VARCHAR(7)    DEFAULT '#6366f1',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_decks_user (user_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS flashcards (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    deck_id     INT           NOT NULL,
    user_id     INT           NOT NULL,
    question    TEXT          NOT NULL,
    answer      TEXT          NOT NULL,
    hint        VARCHAR(255),
    difficulty  TINYINT       DEFAULT 2,
    times_seen  INT           DEFAULT 0,
    times_right INT           DEFAULT 0,
    last_seen   DATETIME,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cards_deck (deck_id),
    FOREIGN KEY (deck_id) REFERENCES flashcard_decks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)            ON DELETE CASCADE
);

-- ── CADERNO DIGITAL ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notebooks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    title       VARCHAR(200)  NOT NULL,
    subject_id  INT,
    color       VARCHAR(7)    DEFAULT '#6366f1',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notebooks_user (user_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notebook_pages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    notebook_id INT           NOT NULL,
    user_id     INT           NOT NULL,
    title       VARCHAR(200)  NOT NULL DEFAULT 'Nova página',
    content     LONGTEXT,
    page_order  INT           DEFAULT 0,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pages_notebook (notebook_id),
    FOREIGN KEY (notebook_id) REFERENCES notebooks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
);

-- ── POST-ITS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stickies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT           NOT NULL,
    content     TEXT          NOT NULL,
    color       VARCHAR(7)    DEFAULT '#fbbf24',
    context     ENUM('agenda','notebook','global') DEFAULT 'global',
    context_id  INT,          -- agenda_item_id ou notebook_id
    pos_x       INT           DEFAULT 0,
    pos_y       INT           DEFAULT 0,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stickies_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
