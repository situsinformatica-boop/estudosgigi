<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action'] ?? '';
    if ($act === 'create_deck') {
        db()->prepare("INSERT INTO flashcard_decks (user_id,name,description,subject_id,color) VALUES (?,?,?,?,?)")
            ->execute([uid(), trim($_POST['name']), $_POST['description']??'', $_POST['subject_id']?:null, $_POST['color']??'#6366f1']);
        header('Location: ' . BASE_URL . '/index.php?page=flashcards'); exit;
    }
    if ($act === 'create_card') {
        db()->prepare("INSERT INTO flashcards (deck_id,user_id,question,answer,hint,difficulty) VALUES (?,?,?,?,?,?)")
            ->execute([(int)$_POST['deck_id'], uid(), trim($_POST['question']), trim($_POST['answer']), $_POST['hint']??'', (int)($_POST['difficulty']??2)]);
        header('Location: ' . BASE_URL . '/index.php?page=flashcards&deck=' . (int)$_POST['deck_id']); exit;
    }
    if ($act === 'delete_card') {
        db()->prepare("DELETE FROM flashcards WHERE id=? AND user_id=?")->execute([(int)$_POST['card_id'],uid()]);
        header('Location: ' . BASE_URL . '/index.php?page=flashcards&deck=' . (int)$_POST['deck_id']); exit;
    }
    if ($act === 'delete_deck') {
        db()->prepare("DELETE FROM flashcard_decks WHERE id=? AND user_id=?")->execute([(int)$_POST['deck_id'],uid()]);
        header('Location: ' . BASE_URL . '/index.php?page=flashcards'); exit;
    }
    if ($act === 'record_answer') {
        $right = (int)($_POST['correct'] ?? 0);
        db()->prepare("UPDATE flashcards SET times_seen=times_seen+1, times_right=times_right+?, last_seen=NOW() WHERE id=? AND user_id=?")
            ->execute([$right, (int)$_POST['card_id'], uid()]);
        echo json_encode(['ok'=>true]); exit;
    }
}

$subjects = (new SubjectModel())->getAll();
$deckId   = isset($_GET['deck']) ? (int)$_GET['deck'] : null;
$mode     = $_GET['mode'] ?? 'list'; // list | study

// Busca decks do usuário
$stmtD = db()->prepare("SELECT d.*, s.name AS subject_name, COUNT(f.id) AS card_count
    FROM flashcard_decks d LEFT JOIN subjects s ON s.id=d.subject_id
    LEFT JOIN flashcards f ON f.deck_id=d.id
    WHERE d.user_id=? GROUP BY d.id ORDER BY d.created_at DESC");
$stmtD->execute([uid()]); $decks = $stmtD->fetchAll();

// Deck selecionado
$deck  = null;
$cards = [];
if ($deckId) {
    $stmtDk = db()->prepare("SELECT d.*, s.name AS subject_name FROM flashcard_decks d LEFT JOIN subjects s ON s.id=d.subject_id WHERE d.id=? AND d.user_id=?");
    $stmtDk->execute([$deckId, uid()]); $deck = $stmtDk->fetch();
    if ($deck) {
        $stmtC = db()->prepare("SELECT * FROM flashcards WHERE deck_id=? ORDER BY RAND()");
        $stmtC->execute([$deckId]); $cards = $stmtC->fetchAll();
    }
}
?>

<div class="page-header">
    <div>
        <div class="page-title">🃏 Flashcards</div>
        <div class="page-subtitle"><?= $deck ? e($deck['name']) : count($decks).' baralhos' ?></div>
    </div>
    <div style="display:flex;gap:8px">
        <?php if($deck): ?>
        <a href="?page=flashcards" class="btn btn-ghost">← Baralhos</a>
        <?php if(count($cards) > 0): ?>
        <a href="?page=flashcards&deck=<?= $deckId ?>&mode=study" class="btn btn-primary">▶ Estudar</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if(!$deck): ?>
        <button class="btn btn-primary" onclick="toggleEl('form-deck')">+ Novo baralho</button>
        <?php endif; ?>
    </div>
</div>

<?php if(!$deck): ?>
<!-- ── LISTA DE BARALHOS ────────────────────────────────────── -->
<div id="form-deck" style="display:none;margin-bottom:20px">
<div class="card" style="max-width:540px">
    <div class="card-title">Novo baralho</div>
    <form method="POST">
        <input type="hidden" name="_action" value="create_deck">
        <div class="form-group"><label class="form-label">Nome *</label>
            <input type="text" name="name" class="form-input" required placeholder="Ex: Direito Penal — Teoria do Crime"></div>
        <div class="form-group"><label class="form-label">Descrição</label>
            <input type="text" name="description" class="form-input" placeholder="Opcional..."></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Matéria</label>
                <select name="subject_id" class="form-select"><option value="">— Nenhuma —</option>
                    <?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Cor</label>
                <input type="color" name="color" class="form-input" value="#6366f1" style="height:44px;padding:4px"></div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn-primary">Criar baralho</button>
            <button type="button" class="btn btn-ghost" onclick="toggleEl('form-deck')">Cancelar</button>
        </div>
    </form>
</div>
</div>

<?php if(empty($decks)): ?>
<div class="card" style="text-align:center;padding:48px;color:var(--muted)">
    <div style="font-size:48px;margin-bottom:14px">🃏</div>
    <div style="font-size:17px;font-weight:700;margin-bottom:8px;color:var(--text)">Nenhum baralho ainda</div>
    <p>Crie um baralho e adicione perguntas e respostas para estudar.</p>
    <button class="btn btn-primary" style="margin-top:16px" onclick="toggleEl('form-deck')">+ Criar baralho</button>
</div>
<?php endif; ?>

<div class="grid-3">
<?php foreach($decks as $d): ?>
<div class="card" style="border-top:4px solid <?= e($d['color']) ?>">
    <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:10px">
        <div style="font-weight:700;font-size:16px"><?= e($d['name']) ?></div>
        <form method="POST" style="flex-shrink:0">
            <input type="hidden" name="_action" value="delete_deck"><input type="hidden" name="deck_id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);padding:3px 7px" onclick="return confirm('Excluir baralho e todos os cards?')">✕</button>
        </form>
    </div>
    <?php if($d['subject_name']): ?><div style="font-size:12px;color:var(--muted);margin-bottom:8px">📖 <?= e($d['subject_name']) ?></div><?php endif; ?>
    <div style="font-size:13px;color:var(--muted);margin-bottom:14px"><?= $d['card_count'] ?> card(s)</div>
    <div style="display:flex;gap:8px">
        <a href="?page=flashcards&deck=<?= $d['id'] ?>" class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">Gerenciar</a>
        <?php if($d['card_count'] > 0): ?>
        <a href="?page=flashcards&deck=<?= $d['id'] ?>&mode=study" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">▶ Estudar</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php elseif($mode === 'study' && count($cards) > 0): ?>
<!-- ── MODO ESTUDO ──────────────────────────────────────────── -->
<div style="max-width:600px;margin:0 auto">
    <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:13px;color:var(--muted)" id="card-counter">Card 1 de <?= count($cards) ?></div>
        <div class="progress-wrap" style="max-width:400px;margin:8px auto 0">
            <div class="progress-bar" id="study-progress" style="width:<?= count($cards)>0?round(1/count($cards)*100):0 ?>%;background:var(--primary)"></div>
        </div>
    </div>

    <!-- Card flip -->
    <div id="flashcard" style="perspective:1000px;cursor:pointer;margin-bottom:24px" onclick="flipCard()">
        <div id="card-inner" style="position:relative;width:100%;min-height:220px;transform-style:preserve-3d;transition:transform .5s;transform:rotateY(0deg)">
            <!-- Frente: Pergunta -->
            <div style="position:absolute;width:100%;backface-visibility:hidden;background:var(--card);border:2px solid var(--primary);border-radius:16px;padding:32px;text-align:center;min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:11px;font-weight:600;color:var(--primary);letter-spacing:.1em;text-transform:uppercase;margin-bottom:14px">PERGUNTA</div>
                <div id="card-question" style="font-size:20px;font-weight:600;line-height:1.5"></div>
                <div id="card-hint" style="margin-top:12px;font-size:13px;color:var(--muted);display:none"></div>
                <div style="margin-top:20px;font-size:13px;color:var(--muted)">Toque para ver a resposta 👆</div>
            </div>
            <!-- Verso: Resposta -->
            <div style="position:absolute;width:100%;backface-visibility:hidden;transform:rotateY(180deg);background:var(--card);border:2px solid var(--green);border-radius:16px;padding:32px;text-align:center;min-height:220px;display:flex;flex-direction:column;align-items:center;justify-content:center">
                <div style="font-size:11px;font-weight:600;color:var(--green);letter-spacing:.1em;text-transform:uppercase;margin-bottom:14px">RESPOSTA</div>
                <div id="card-answer" style="font-size:18px;line-height:1.5"></div>
            </div>
        </div>
    </div>

    <!-- Botões de resultado (aparecem após virar) -->
    <div id="result-btns" style="display:none;text-align:center">
        <p style="font-size:14px;color:var(--muted);margin-bottom:14px">Você acertou?</p>
        <div style="display:flex;gap:12px;justify-content:center">
            <button class="btn btn-danger" onclick="nextCard(0)" style="min-width:130px">✗ Errei</button>
            <button class="btn btn-success" onclick="nextCard(1)" style="min-width:130px">✓ Acertei</button>
        </div>
    </div>

    <div id="study-done" style="display:none;text-align:center;padding:32px">
        <div style="font-size:48px;margin-bottom:14px">🎉</div>
        <div style="font-size:20px;font-weight:700;margin-bottom:8px">Baralho concluído!</div>
        <p style="color:var(--muted);margin-bottom:6px">Acertos: <strong id="score-right" style="color:var(--green)">0</strong> / <strong id="score-total">0</strong></p>
        <div style="display:flex;gap:10px;justify-content:center;margin-top:20px">
            <button class="btn btn-primary" onclick="restartStudy()">🔄 Estudar novamente</button>
            <a href="?page=flashcards&deck=<?= $deckId ?>" class="btn btn-ghost">Ver baralho</a>
        </div>
    </div>
</div>

<script>
const cards = <?= json_encode(array_values($cards)) ?>;
let idx = 0, flipped = false, right = 0;

function showCard(i) {
    if (i >= cards.length) { endStudy(); return; }
    const c = cards[i];
    document.getElementById('card-question').textContent = c.question;
    document.getElementById('card-answer').textContent   = c.answer;
    const hint = document.getElementById('card-hint');
    if (c.hint) { hint.textContent = '💡 ' + c.hint; hint.style.display = 'block'; }
    else hint.style.display = 'none';
    document.getElementById('card-inner').style.transform = 'rotateY(0deg)';
    document.getElementById('result-btns').style.display = 'none';
    document.getElementById('card-counter').textContent = 'Card ' + (i+1) + ' de ' + cards.length;
    document.getElementById('study-progress').style.width = ((i+1)/cards.length*100) + '%';
    flipped = false;
}

function flipCard() {
    if (flipped) return;
    document.getElementById('card-inner').style.transform = 'rotateY(180deg)';
    document.getElementById('result-btns').style.display = 'block';
    flipped = true;
}

async function nextCard(correct) {
    if (correct) right++;
    // Registra no servidor
    await fetch('index.php?page=flashcards', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({_action:'record_answer', card_id:cards[idx].id, correct})
    });
    idx++;
    showCard(idx);
}

function endStudy() {
    document.getElementById('flashcard').style.display = 'none';
    document.getElementById('result-btns').style.display = 'none';
    document.getElementById('study-done').style.display = 'block';
    document.getElementById('score-right').textContent = right;
    document.getElementById('score-total').textContent = cards.length;
}

function restartStudy() {
    idx = 0; right = 0; flipped = false;
    document.getElementById('flashcard').style.display = 'block';
    document.getElementById('study-done').style.display = 'none';
    showCard(0);
}

showCard(0);
</script>

<?php else: ?>
<!-- ── GERENCIAR DECK ───────────────────────────────────────── -->
<div id="form-card" style="display:none;margin-bottom:20px">
<div class="card" style="max-width:600px">
    <div class="card-title">Novo flashcard</div>
    <form method="POST">
        <input type="hidden" name="_action" value="create_card">
        <input type="hidden" name="deck_id" value="<?= $deckId ?>">
        <div class="form-group"><label class="form-label">Pergunta *</label>
            <textarea name="question" class="form-textarea" required placeholder="Ex: O que é dolo direto?"></textarea></div>
        <div class="form-group"><label class="form-label">Resposta *</label>
            <textarea name="answer" class="form-textarea" required placeholder="Ex: É a intenção direta de produzir o resultado..."></textarea></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Dica (opcional)</label>
                <input type="text" name="hint" class="form-input" placeholder="Uma pista para lembrar..."></div>
            <div class="form-group"><label class="form-label">Dificuldade</label>
                <select name="difficulty" class="form-select"><option value="1">Fácil</option><option value="2" selected>Médio</option><option value="3">Difícil</option></select></div>
        </div>
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn-primary">Adicionar card</button>
            <button type="button" class="btn btn-ghost" onclick="toggleEl('form-card')">Cancelar</button>
        </div>
    </form>
</div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
    <div>
        <div style="font-size:17px;font-weight:700"><?= e($deck['name']) ?></div>
        <?php if($deck['subject_name']): ?><div style="font-size:13px;color:var(--muted)">📖 <?= e($deck['subject_name']) ?></div><?php endif; ?>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="toggleEl('form-card')">+ Adicionar card</button>
</div>

<?php if(empty($cards)): ?>
<div class="card" style="text-align:center;padding:40px;color:var(--muted)">
    Nenhum card ainda. Adicione perguntas e respostas!
    <div style="margin-top:14px"><button class="btn btn-primary btn-sm" onclick="toggleEl('form-card')">+ Adicionar card</button></div>
</div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach($cards as $c): ?>
<div class="card">
    <div style="display:flex;align-items:start;gap:12px">
        <div style="flex:1;min-width:0">
            <div style="font-size:11px;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">PERGUNTA</div>
            <div style="font-size:15px;font-weight:600;margin-bottom:10px"><?= nl2br(e($c['question'])) ?></div>
            <div style="font-size:11px;font-weight:600;color:var(--green);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">RESPOSTA</div>
            <div style="font-size:14px;color:var(--muted)"><?= nl2br(e($c['answer'])) ?></div>
            <?php if($c['hint']): ?><div style="font-size:12px;color:var(--accent);margin-top:6px">💡 <?= e($c['hint']) ?></div><?php endif; ?>
            <?php if($c['times_seen'] > 0): ?>
            <div style="font-size:11px;color:var(--muted);margin-top:8px">
                Visto <?= $c['times_seen'] ?>x · Acertos: <?= $c['times_right'] ?>/<?= $c['times_seen'] ?>
                (<?= round($c['times_right']/$c['times_seen']*100) ?>%)
            </div>
            <?php endif; ?>
        </div>
        <form method="POST" style="flex-shrink:0">
            <input type="hidden" name="_action" value="delete_card">
            <input type="hidden" name="card_id" value="<?= $c['id'] ?>">
            <input type="hidden" name="deck_id" value="<?= $deckId ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red);padding:3px 8px" onclick="return confirm('Excluir card?')">✕</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<script>function toggleEl(id){const el=document.getElementById(id);el.style.display=el.style.display==='none'?'block':'none';}</script>
