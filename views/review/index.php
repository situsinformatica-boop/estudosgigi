<?php
// ── Simulados / Quiz por matéria ─────────────────────────────
// Substitui a revisão espaçada que não estava funcionando.
// Usa os flashcards existentes para montar simulados.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';

    // Cria quiz personalizado
    if ($act === 'create_quiz') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $qty       = min(50, max(1, (int)($_POST['quantity'] ?? 10)));
        $mode      = $_POST['mode'] ?? 'random'; // random | hardest | newest
        header('Location: '.BASE_URL.'/index.php?page=review&quiz=1&subject='.$subjectId.'&qty='.$qty.'&mode='.$mode);
        exit;
    }
    // Registra resultado de card do quiz
    if ($act === 'record') {
        $cardId = (int)($_POST['card_id'] ?? 0);
        $right  = (int)($_POST['correct'] ?? 0);
        if ($cardId) {
            db()->prepare("UPDATE flashcards SET times_seen=times_seen+1, times_right=times_right+?, last_seen=NOW() WHERE id=? AND user_id=?")
                ->execute([$right, $cardId, uid()]);
        }
        echo json_encode(['ok'=>true]); exit;
    }
}

// ── Modo Quiz ────────────────────────────────────────────────
$isQuiz     = isset($_GET['quiz']);
$subjectId  = (int)($_GET['subject'] ?? 0);
$qty        = min(50, max(1, (int)($_GET['qty'] ?? 10)));
$mode       = $_GET['mode'] ?? 'random';
$quizCards  = [];

if ($isQuiz) {
    $orderBy = match($mode) {
        'hardest' => 'f.difficulty DESC, (f.times_seen - f.times_right) DESC',
        'newest'  => 'f.created_at DESC',
        default   => 'RAND()',
    };
    $where = $subjectId ? 'AND d.subject_id=?' : '';
    $params = $subjectId ? [uid(), $subjectId, $qty] : [uid(), $qty];
    $stmt = db()->prepare("
        SELECT f.*, d.name AS deck_name, d.color AS deck_color, s.name AS subject_name
        FROM flashcards f
        JOIN flashcard_decks d ON d.id=f.deck_id
        LEFT JOIN subjects s ON s.id=d.subject_id
        WHERE f.user_id=? $where
        ORDER BY $orderBy
        LIMIT ?
    ");
    $stmt->execute($params);
    $quizCards = $stmt->fetchAll();
}

// ── Dashboard de desempenho ──────────────────────────────────
$subjects = (new SubjectModel())->getAll();

// Stats de flashcards por matéria
$stmtStats = db()->prepare("
    SELECT s.name, s.color,
           COUNT(f.id) AS total_cards,
           SUM(f.times_seen) AS total_seen,
           SUM(f.times_right) AS total_right,
           COUNT(DISTINCT d.id) AS total_decks
    FROM flashcard_decks d
    JOIN flashcards f ON f.deck_id=d.id
    LEFT JOIN subjects s ON s.id=d.subject_id
    WHERE d.user_id=?
    GROUP BY s.id, s.name, s.color
    ORDER BY total_seen DESC
");
$stmtStats->execute([uid()]); $stats = $stmtStats->fetchAll();

// Total de decks e cards
$stmtTotals = db()->prepare("SELECT COUNT(DISTINCT d.id) AS decks, COUNT(f.id) AS cards FROM flashcard_decks d LEFT JOIN flashcards f ON f.deck_id=d.id WHERE d.user_id=?");
$stmtTotals->execute([uid()]); $totals = $stmtTotals->fetch();

// Cards com menor taxa de acerto (para estudar)
$stmtWeak = db()->prepare("
    SELECT f.question, f.times_seen, f.times_right, d.name AS deck_name, d.color,
           ROUND(CASE WHEN f.times_seen>0 THEN (f.times_right/f.times_seen)*100 ELSE 0 END, 0) AS pct
    FROM flashcards f JOIN flashcard_decks d ON d.id=f.deck_id
    WHERE f.user_id=? AND f.times_seen>0
    ORDER BY pct ASC LIMIT 5
");
$stmtWeak->execute([uid()]); $weakCards = $stmtWeak->fetchAll();
?>

<?php if (!$isQuiz): ?>
<!-- ── DASHBOARD ─────────────────────────────────────────────── -->
<div class="page-header">
    <div><div class="page-title">🎯 Simulados & Quiz</div>
    <div class="page-subtitle">Teste seus conhecimentos com seus flashcards</div></div>
</div>

<!-- Stats rápidas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Baralhos</div>
        <div class="stat-value stat-blue"><?= $totals['decks'] ?? 0 ?></div>
        <div class="stat-sub"><a href="<?= BASE_URL ?>/index.php?page=flashcards" style="color:var(--primary-lt)">Gerenciar →</a></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Flashcards</div>
        <div class="stat-value stat-green"><?= $totals['cards'] ?? 0 ?></div>
        <div class="stat-sub">cards criados</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Questões respondidas</div>
        <div class="stat-value stat-amber"><?= array_sum(array_column($stats,'total_seen')) ?></div>
        <div class="stat-sub">no total</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Taxa de acerto geral</div>
        <?php
        $ts = array_sum(array_column($stats,'total_seen'));
        $tr = array_sum(array_column($stats,'total_right'));
        $pct = $ts>0 ? round($tr/$ts*100) : 0;
        ?>
        <div class="stat-value <?= $pct>=70?'stat-green':($pct>=50?'stat-amber':'stat-red') ?>"><?= $pct ?>%</div>
        <div class="stat-sub"><?= $tr ?> de <?= $ts ?> acertos</div>
    </div>
</div>

<!-- Iniciar novo simulado -->
<div class="card" style="margin-bottom:20px">
    <div class="card-title">🚀 Iniciar simulado</div>
    <form method="POST" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
        <input type="hidden" name="_action" value="create_quiz">
        <div class="form-group" style="margin:0;flex:1;min-width:180px">
            <label class="form-label">Matéria</label>
            <select name="subject_id" class="form-select">
                <option value="0">Todas as matérias</option>
                <?php foreach($subjects as $s): ?>
                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;width:120px">
            <label class="form-label">Qtd. questões</label>
            <input type="number" name="quantity" class="form-input" value="10" min="5" max="50">
        </div>
        <div class="form-group" style="margin:0;flex:1;min-width:160px">
            <label class="form-label">Ordem</label>
            <select name="mode" class="form-select">
                <option value="random">Aleatória</option>
                <option value="hardest">Mais difíceis primeiro</option>
                <option value="newest">Mais recentes</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height:44px;padding:0 24px">▶ Iniciar simulado</button>
    </form>
    <?php if(($totals['cards']??0) === 0): ?>
    <div style="margin-top:12px;padding:12px;background:var(--bg3);border-radius:var(--radius);font-size:14px;color:var(--muted)">
        ℹ Crie flashcards primeiro em <a href="<?= BASE_URL ?>/index.php?page=flashcards" style="color:var(--primary-lt)">Flashcards</a> para poder fazer simulados.
    </div>
    <?php endif; ?>
</div>

<div class="grid-2">
<!-- Performance por matéria -->
<div class="card">
    <div class="card-title">Performance por matéria</div>
    <?php if(empty($stats)): ?>
    <p style="color:var(--muted);font-size:14px">Nenhum quiz realizado ainda.</p>
    <?php endif; ?>
    <?php foreach($stats as $st): ?>
    <?php $p = $st['total_seen']>0?round($st['total_right']/$st['total_seen']*100):0; ?>
    <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
            <span style="font-weight:600"><span class="subject-dot" style="background:<?= e($st['color']??'#6366f1') ?>"></span><?= e($st['name']??'Sem matéria') ?></span>
            <span style="color:var(--muted);font-family:var(--mono)"><?= $st['total_right'] ?>/<?= $st['total_seen'] ?> (<?= $p ?>%)</span>
        </div>
        <div class="progress-wrap">
            <div class="progress-bar" style="width:<?= $p ?>%;background:<?= $p>=70?'var(--green)':($p>=50?'var(--accent)':'var(--red)') ?>"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px"><?= $st['total_decks'] ?> baralho(s) · <?= $st['total_cards'] ?> card(s)</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Cards mais errados -->
<div class="card">
    <div class="card-title">⚠ Cards com menor acerto</div>
    <?php if(empty($weakCards)): ?>
    <p style="color:var(--muted);font-size:14px">Ainda sem dados suficientes. Faça mais simulados!</p>
    <?php else: ?>
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">Foque nesses temas para melhorar seu desempenho:</p>
    <?php foreach($weakCards as $w): ?>
    <div style="padding:10px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:8px">
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;font-weight:600;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(mb_substr($w['question'],0,60)) ?></div>
                <div style="font-size:11px;color:var(--muted)"><span class="subject-dot" style="background:<?= e($w['color']) ?>"></span><?= e($w['deck_name']) ?> · <?= $w['times_seen'] ?> tentativas</div>
            </div>
            <span style="font-size:13px;font-weight:700;padding:2px 10px;border-radius:8px;white-space:nowrap;background:<?= $w['pct']>=50?'rgba(245,158,11,.15)':'rgba(239,68,68,.15)' ?>;color:<?= $w['pct']>=50?'var(--accent)':'var(--red)' ?>"><?= $w['pct'] ?>%</span>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>

<?php else: ?>
<!-- ── MODO QUIZ ──────────────────────────────────────────────── -->
<div class="page-header">
    <div><div class="page-title">🎯 Simulado em Andamento</div>
    <div class="page-subtitle"><?= count($quizCards) ?> questões<?= $subjectId?' · matéria filtrada':' · todas as matérias' ?></div></div>
    <a href="?page=review" class="btn btn-ghost">← Sair do simulado</a>
</div>

<?php if(empty($quizCards)): ?>
<div class="card" style="text-align:center;padding:48px;color:var(--muted)">
    <div style="font-size:48px;margin-bottom:14px">🃏</div>
    <div style="font-size:17px;font-weight:700;margin-bottom:8px;color:var(--text)">Nenhum card encontrado</div>
    <p>Crie flashcards nessa matéria antes de iniciar um simulado.</p>
    <a href="<?= BASE_URL ?>/index.php?page=flashcards" class="btn btn-primary" style="margin-top:16px">Ir para Flashcards</a>
</div>
<?php else: ?>

<div style="max-width:640px;margin:0 auto">
    <!-- Progresso -->
    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted);margin-bottom:8px">
        <span id="q-counter">Questão 1 de <?= count($quizCards) ?></span>
        <span id="q-score">✓ 0 | ✗ 0</span>
    </div>
    <div class="progress-wrap" style="margin-bottom:24px">
        <div class="progress-bar" id="q-progress" style="width:0%;background:var(--primary)"></div>
    </div>

    <!-- Card de questão -->
    <div id="quiz-card" style="perspective:1000px;margin-bottom:20px;cursor:pointer" onclick="flipQuizCard()">
        <div id="quiz-inner" style="position:relative;width:100%;min-height:200px;transform-style:preserve-3d;transition:transform .5s">
            <!-- Frente -->
            <div style="position:absolute;inset:0;backface-visibility:hidden;background:var(--card);border:2px solid var(--primary);border-radius:16px;padding:28px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:200px">
                <div style="font-size:11px;font-weight:600;color:var(--primary);letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px">PERGUNTA</div>
                <div id="q-question" style="font-size:19px;font-weight:600;line-height:1.5"></div>
                <div id="q-hint" style="margin-top:10px;font-size:13px;color:var(--muted);display:none"></div>
                <div style="margin-top:16px;font-size:13px;color:var(--muted)">Toque para ver a resposta 👆</div>
                <div id="q-deck" style="position:absolute;top:12px;right:12px;font-size:11px;color:var(--muted);background:var(--bg3);padding:2px 8px;border-radius:6px"></div>
            </div>
            <!-- Verso -->
            <div style="position:absolute;inset:0;backface-visibility:hidden;transform:rotateY(180deg);background:var(--card);border:2px solid var(--green);border-radius:16px;padding:28px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:200px">
                <div style="font-size:11px;font-weight:600;color:var(--green);letter-spacing:.1em;text-transform:uppercase;margin-bottom:12px">RESPOSTA</div>
                <div id="q-answer" style="font-size:17px;line-height:1.6"></div>
            </div>
        </div>
    </div>

    <!-- Botões de resposta -->
    <div id="q-btns" style="display:none;text-align:center">
        <p style="font-size:14px;color:var(--muted);margin-bottom:14px">Você acertou essa questão?</p>
        <div style="display:flex;gap:12px;justify-content:center">
            <button class="btn btn-danger" onclick="answerQuiz(0)" style="min-width:140px;font-size:15px">✗ Errei</button>
            <button class="btn btn-success" onclick="answerQuiz(1)" style="min-width:140px;font-size:15px">✓ Acertei!</button>
        </div>
    </div>

    <!-- Resultado final -->
    <div id="q-done" style="display:none;text-align:center;padding:32px 0">
        <div style="font-size:52px;margin-bottom:14px" id="q-emoji">🎉</div>
        <div style="font-size:22px;font-weight:700;margin-bottom:8px" id="q-result-title">Simulado concluído!</div>
        <div style="font-size:16px;color:var(--muted);margin-bottom:20px" id="q-result-sub"></div>
        <div class="card" style="max-width:300px;margin:0 auto 20px">
            <div style="font-size:48px;font-weight:700;font-family:var(--mono);color:var(--primary-lt)" id="q-pct">0%</div>
            <div style="font-size:14px;color:var(--muted)" id="q-final-score"></div>
            <div class="progress-wrap" style="margin-top:10px"><div class="progress-bar" id="q-final-bar" style="background:var(--green)"></div></div>
        </div>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <button class="btn btn-primary" onclick="restartQuiz()">🔄 Refazer simulado</button>
            <a href="?page=review" class="btn btn-ghost">← Voltar</a>
            <a href="<?= BASE_URL ?>/index.php?page=flashcards" class="btn btn-ghost">📚 Flashcards</a>
        </div>
    </div>
</div>

<script>
const quizCards = <?= json_encode(array_values($quizCards)) ?>;
let qi=0, right=0, wrong=0, flipped=false;

function showQuestion(i){
    if(i>=quizCards.length){endQuiz();return;}
    const c=quizCards[i];
    document.getElementById('q-question').textContent=c.question;
    document.getElementById('q-answer').textContent=c.answer;
    document.getElementById('q-deck').textContent=c.deck_name||(c.subject_name||'');
    const hint=document.getElementById('q-hint');
    if(c.hint){hint.textContent='💡 '+c.hint;hint.style.display='block';}
    else hint.style.display='none';
    document.getElementById('quiz-inner').style.transform='rotateY(0deg)';
    document.getElementById('q-btns').style.display='none';
    document.getElementById('q-counter').textContent='Questão '+(i+1)+' de '+quizCards.length;
    document.getElementById('q-score').textContent='✓ '+right+' | ✗ '+wrong;
    document.getElementById('q-progress').style.width=(i/quizCards.length*100)+'%';
    flipped=false;
    // Muda cor do card pela dificuldade
    const border=document.querySelector('#quiz-inner > div:first-child');
    if(border) border.style.borderColor=c.difficulty>=3?'var(--red)':c.difficulty>=2?'var(--accent)':'var(--primary)';
}

function flipQuizCard(){
    if(flipped)return;
    document.getElementById('quiz-inner').style.transform='rotateY(180deg)';
    document.getElementById('q-btns').style.display='block';
    flipped=true;
}

async function answerQuiz(correct){
    if(correct) right++; else wrong++;
    // Registra via POST
    fetch('index.php?page=review',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({_action:'record',card_id:quizCards[qi].id,correct})});
    qi++;
    showQuestion(qi);
}

function endQuiz(){
    document.getElementById('quiz-card').style.display='none';
    document.getElementById('q-btns').style.display='none';
    document.getElementById('q-done').style.display='block';
    const total=right+wrong, pct=total>0?Math.round(right/total*100):0;
    document.getElementById('q-pct').textContent=pct+'%';
    document.getElementById('q-final-score').textContent=right+' acertos de '+total+' questões';
    document.getElementById('q-final-bar').style.width=pct+'%';
    document.getElementById('q-final-bar').style.background=pct>=70?'var(--green)':pct>=50?'var(--accent)':'var(--red)';
    document.getElementById('q-progress').style.width='100%';
    const emojis=['😅','😐','🙂','😊','🎉','🏆'];
    document.getElementById('q-emoji').textContent=emojis[Math.min(5,Math.floor(pct/20))];
    document.getElementById('q-result-title').textContent=pct>=80?'Excelente desempenho!':pct>=60?'Bom trabalho!':'Continue praticando!';
    document.getElementById('q-result-sub').textContent=pct>=70?'Você está dominando bem o conteúdo.':'Revise os flashcards com erros para melhorar.';
}

function restartQuiz(){
    qi=0;right=0;wrong=0;flipped=false;
    document.getElementById('quiz-card').style.display='block';
    document.getElementById('q-done').style.display='none';
    showQuestion(0);
}

showQuestion(0);
</script>
<?php endif; ?>
<?php endif; ?>
