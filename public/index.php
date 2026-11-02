<?php
/**
 * public/index.php — único ponto de entrada
 *
 * REGRA: Todo POST termina com header(Location) + exit AQUI,
 * antes de qualquer HTML ser enviado.
 * O ob_start() em app.php garante que warnings não quebrem headers.
 */
require_once dirname(__DIR__) . '/config/app.php';

$page = $_GET['page'] ?? 'dashboard';

// ── 1. PÁGINAS PÚBLICAS ──────────────────────────────────────
if (in_array($page, ['login', 'register', 'logout'])) {
    if ($page === 'logout') {
        (new UserModel())->logout();
        redirect('login'); exit;
    }
    if (!empty($_SESSION['user_id'])) {
        redirect('dashboard'); exit;
    }
    require_once APP_ROOT . '/views/auth/' . ($page === 'login' ? 'login' : 'register') . '.php';
    exit;
}

// ── 2. AUTH OBRIGATÓRIA ──────────────────────────────────────
requireAuth();

// ── 3. API AJAX ──────────────────────────────────────────────
if ($page === 'api') {
    require_once APP_ROOT . '/src/controllers/ApiController.php';
    exit;
}

// ── 4. TODO POST É PROCESSADO AQUI ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = trim($_POST['_action'] ?? '');

    // ── NOTEBOOK ─────────────────────────────────────────────
    if ($page === 'notebook') {
        $nbId   = (int)($_POST['notebook_id'] ?? 0);
        $pageId = (int)($_POST['page_id']     ?? 0);

        if ($act === 'create_notebook') {
            $title = trim($_POST['title'] ?? '');
            if ($title !== '') {
                db()->prepare(
                    "INSERT INTO notebooks (user_id,title,subject_id,color) VALUES (?,?,?,?)"
                )->execute([uid(), $title, $_POST['subject_id'] ?: null, $_POST['color'] ?? '#6366f1']);
                $newId = (int)db()->lastInsertId();
                db()->prepare(
                    "INSERT INTO notebook_pages (notebook_id,user_id,title,page_order) VALUES (?,?,?,0)"
                )->execute([$newId, uid(), 'Página 1']);
                redirect('notebook', ['nb' => $newId]);
            } else {
                redirect('notebook');
            }
            exit;
        }

        if ($act === 'create_page') {
            $stmt = db()->prepare("SELECT COALESCE(MAX(page_order),0) FROM notebook_pages WHERE notebook_id=?");
            $stmt->execute([$nbId]);
            $max   = (int)$stmt->fetchColumn();
            $title = trim($_POST['page_title'] ?? '') ?: 'Página ' . ($max + 2);
            db()->prepare(
                "INSERT INTO notebook_pages (notebook_id,user_id,title,page_order) VALUES (?,?,?,?)"
            )->execute([$nbId, uid(), $title, $max + 1]);
            redirect('notebook', ['nb' => $nbId]);
            exit;
        }

        if ($act === 'save_page') {
            db()->prepare(
                "UPDATE notebook_pages SET title=?,content=? WHERE id=? AND user_id=?"
            )->execute([trim($_POST['page_title'] ?? ''), $_POST['content'] ?? '', $pageId, uid()]);
            // AJAX → JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'saved' => date('H:i')]);
                exit;
            }
            redirect('notebook', ['nb' => $nbId, 'p' => $pageId]);
            exit;
        }

        if ($act === 'delete_page') {
            db()->prepare("DELETE FROM notebook_pages WHERE id=? AND user_id=?")->execute([$pageId, uid()]);
            redirect('notebook', ['nb' => $nbId]);
            exit;
        }

        if ($act === 'delete_notebook') {
            db()->prepare("DELETE FROM notebooks WHERE id=? AND user_id=?")->execute([$nbId, uid()]);
            redirect('notebook');
            exit;
        }

        if ($act === 'nb_sticky_add') {
            db()->prepare(
                "INSERT INTO stickies (user_id,content,color,context,context_id) VALUES (?,?,?,'notebook',?)"
            )->execute([uid(), trim($_POST['content']), $_POST['color'] ?? '#fbbf24', $nbId]);
            redirect('notebook', ['nb' => $nbId, 'p' => $pageId]);
            exit;
        }

        if ($act === 'nb_sticky_del') {
            db()->prepare("DELETE FROM stickies WHERE id=? AND user_id=?")->execute([(int)$_POST['sid'], uid()]);
            redirect('notebook', ['nb' => $nbId, 'p' => $pageId]);
            exit;
        }

        redirect('notebook');
        exit;
    }

    // ── AGENDA ───────────────────────────────────────────────
    if ($page === 'agenda') {
        $yr  = (int)($_POST['year']  ?? date('Y'));
        $mo  = (int)($_POST['month'] ?? date('n'));
        $dt  = $_POST['date'] ?? date('Y-m-d');

        if ($act === 'save_notes') {
            $stmt = db()->prepare("SELECT id FROM agenda_items WHERE user_id=? AND study_date=? AND title='__notes__'");
            $stmt->execute([uid(), $dt]);
            $row = $stmt->fetch();
            if ($row) {
                db()->prepare("UPDATE agenda_items SET description=? WHERE id=?")->execute([$_POST['notes'] ?? '', $row['id']]);
            } else {
                db()->prepare("INSERT INTO agenda_items (user_id,title,description,study_date,color) VALUES (?,'__notes__',?,?,'#6366f1')")->execute([uid(), $_POST['notes'] ?? '', $dt]);
            }
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'saved' => date('H:i')]);
                exit;
            }
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        if ($act === 'add_task') {
            db()->prepare("INSERT INTO agenda_items (user_id,title,study_date,priority,color,is_done) VALUES (?,?,?,?,?,0)")
                ->execute([uid(), trim($_POST['title']), $dt, (int)($_POST['priority'] ?? 2), $_POST['color'] ?? '#6366f1']);
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        if ($act === 'toggle_task') {
            $stmt = db()->prepare("SELECT is_done FROM agenda_items WHERE id=? AND user_id=?");
            $stmt->execute([(int)$_POST['id'], uid()]);
            $row = $stmt->fetch();
            if ($row) {
                db()->prepare("UPDATE agenda_items SET is_done=? WHERE id=? AND user_id=?")
                    ->execute([$row['is_done'] ? 0 : 1, (int)$_POST['id'], uid()]);
            }
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        if ($act === 'delete_task') {
            db()->prepare("DELETE FROM agenda_items WHERE id=? AND user_id=? AND title!='__notes__'")->execute([(int)$_POST['id'], uid()]);
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        if ($act === 'add_sticky') {
            db()->prepare("INSERT INTO stickies (user_id,content,color,context,context_id) VALUES (?,?,?,'agenda',?)")
                ->execute([uid(), trim($_POST['content']), $_POST['color'] ?? '#fbbf24', strtotime($dt)]);
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        if ($act === 'del_sticky') {
            db()->prepare("DELETE FROM stickies WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], uid()]);
            redirect('agenda', ['year' => $yr, 'month' => $mo, 'day' => $dt]);
            exit;
        }

        redirect('agenda');
        exit;
    }

    // ── FLASHCARDS ───────────────────────────────────────────
    if ($page === 'flashcards') {
        $deckId = (int)($_POST['deck_id'] ?? 0);

        if ($act === 'create_deck') {
            db()->prepare("INSERT INTO flashcard_decks (user_id,name,description,subject_id,color) VALUES (?,?,?,?,?)")
                ->execute([uid(), trim($_POST['name']), $_POST['description'] ?? '', $_POST['subject_id'] ?: null, $_POST['color'] ?? '#6366f1']);
            redirect('flashcards');
            exit;
        }

        if ($act === 'create_card') {
            db()->prepare("INSERT INTO flashcards (deck_id,user_id,question,answer,hint,difficulty) VALUES (?,?,?,?,?,?)")
                ->execute([$deckId, uid(), trim($_POST['question']), trim($_POST['answer']), $_POST['hint'] ?? '', (int)($_POST['difficulty'] ?? 2)]);
            redirect('flashcards', ['deck' => $deckId]);
            exit;
        }

        if ($act === 'delete_card') {
            db()->prepare("DELETE FROM flashcards WHERE id=? AND user_id=?")->execute([(int)$_POST['card_id'], uid()]);
            redirect('flashcards', ['deck' => $deckId]);
            exit;
        }

        if ($act === 'delete_deck') {
            db()->prepare("DELETE FROM flashcard_decks WHERE id=? AND user_id=?")->execute([$deckId, uid()]);
            redirect('flashcards');
            exit;
        }

        if ($act === 'record_answer') {
            db()->prepare("UPDATE flashcards SET times_seen=times_seen+1,times_right=times_right+?,last_seen=NOW() WHERE id=? AND user_id=?")
                ->execute([(int)($_POST['correct'] ?? 0), (int)$_POST['card_id'], uid()]);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        redirect('flashcards');
        exit;
    }

    // ── SIMULADOS ────────────────────────────────────────────
    if ($page === 'review') {
        if ($act === 'create_quiz') {
            redirect('review', ['quiz' => 1, 'subject' => (int)($_POST['subject_id'] ?? 0), 'qty' => (int)($_POST['quantity'] ?? 10), 'mode' => $_POST['mode'] ?? 'random']);
            exit;
        }
        if ($act === 'record') {
            db()->prepare("UPDATE flashcards SET times_seen=times_seen+1,times_right=times_right+?,last_seen=NOW() WHERE id=? AND user_id=?")
                ->execute([(int)($_POST['correct'] ?? 0), (int)$_POST['card_id'], uid()]);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('review');
        exit;
    }

    // ── PERFIL (sem foto) ─────────────────────────────────────
    if ($page === 'profile') {
        $um = new UserModel();

        if ($act === 'update_profile') {
            $r = $um->updateProfile(uid(), [
                'name'         => trim($_POST['name'] ?? ''),
                'email'        => trim($_POST['email'] ?? ''),
                'avatar_color' => $_POST['avatar_color'] ?? '#6366f1',
                'theme'        => $_POST['theme'] ?? 'dark',
            ]);
            $_SESSION['flash'] = $r['success'] ? ['ok', 'Perfil atualizado!'] : ['err', $r['error'] ?? 'Erro'];
        }

        if ($act === 'change_password') {
            $r = $um->changePassword(uid(), $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
            $_SESSION['flash'] = $r['success'] ? ['ok', 'Senha alterada!'] : ['err', $r['error'] ?? 'Erro'];
        }

        redirect('profile');
        exit;
    }

    // ── MATÉRIAS ──────────────────────────────────────────────
    if ($page === 'subjects') {
        require_once APP_ROOT . '/views/subjects/index.php';
        exit;
    }

    // ── CONFIGURAÇÕES ─────────────────────────────────────────
    if ($page === 'settings') {
        require_once APP_ROOT . '/views/settings/index.php';
        exit;
    }

    // ── METAS ─────────────────────────────────────────────────
    if ($page === 'goals') {
        require_once APP_ROOT . '/views/goals/index.php';
        exit;
    }

    // ── ADMIN ─────────────────────────────────────────────────
    if ($page === 'admin') {
        requireAdmin();
        require_once APP_ROOT . '/views/admin/index.php';
        exit;
    }

    // Fallback seguro
    redirect($page);
    exit;
}

// ── 5. RENDERIZAÇÃO GET ──────────────────────────────────────
if ($page === 'admin') requireAdmin();

$routes = [
    'dashboard'    => 'dashboard/index.php',
    'subjects'     => 'subjects/index.php',
    'timer'        => 'timer/index.php',
    'review'       => 'review/index.php',
    'reports'      => 'reports/index.php',
    'agenda'       => 'agenda/index.php',
    'study_hours'  => 'study_hours/index.php',
    'flashcards'   => 'flashcards/index.php',
    'notebook'     => 'notebook/index.php',
    'focus'        => 'focus/index.php',
    'settings'     => 'settings/index.php',
    'gamification' => 'gamification/index.php',
    'goals'        => 'goals/index.php',
    'profile'      => 'profile/index.php',
    'admin'        => 'admin/index.php',
];

$viewFile = isset($routes[$page])
    ? APP_ROOT . '/views/' . $routes[$page]
    : APP_ROOT . '/views/404.php';

if (!file_exists($viewFile)) $viewFile = APP_ROOT . '/views/404.php';

require_once APP_ROOT . '/views/layouts/header.php';
require_once $viewFile;
require_once APP_ROOT . '/views/layouts/footer.php';
