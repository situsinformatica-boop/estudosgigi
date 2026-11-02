<?php
$subjects = (new SubjectModel())->getAll();
$stmt = db()->prepare("SELECT * FROM pomodoro_config WHERE user_id=?");
$stmt->execute([uid()]);
$cfg = $stmt->fetch();
if (!$cfg) {
    db()->prepare("INSERT IGNORE INTO pomodoro_config (user_id) VALUES (?)")->execute([uid()]);
    $stmt->execute([uid()]);
    $cfg = $stmt->fetch();
}
if (!$cfg) {
    $cfg = ['work_minutes'=>25,'short_break_min'=>5,'long_break_min'=>15,
            'cycles_before_long'=>4,'auto_start_break'=>0,'auto_start_work'=>0,'sound_enabled'=>1];
}
?>

<div class="page-header">
    <div><div class="page-title">Estudar</div>
    <div class="page-subtitle">Escolha sua matéria, ajuste o tempo e comece.</div></div>
</div>

<?php if(empty($subjects)): ?><div class="card empty-day"><strong>Antes de começar, adicione uma matéria.</strong><a class="btn btn-primary" href="<?= BASE_URL ?>/index.php?page=subjects">Adicionar matéria</a></div><?php endif; ?>
<!-- Abas de modo -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--bg3);padding:4px;border-radius:12px;width:fit-content">
    <button id="tab-pomodoro" onclick="setMode('pomodoro')"
        style="padding:8px 20px;border-radius:9px;border:none;cursor:pointer;font-family:var(--font);font-size:14px;font-weight:600;background:var(--primary);color:var(--on-primary);transition:all .2s">
        🍅 Pomodoro
    </button>
    <button id="tab-smart" onclick="setMode('smart')"
        style="padding:8px 20px;border-radius:9px;border:none;cursor:pointer;font-family:var(--font);font-size:14px;font-weight:600;background:transparent;color:var(--muted);transition:all .2s">
        🧠 Tempo Total
    </button>
    <button id="tab-simple" onclick="setMode('simple')"
        style="padding:8px 20px;border-radius:9px;border:none;cursor:pointer;font-family:var(--font);font-size:14px;font-weight:600;background:transparent;color:var(--muted);transition:all .2s">
        ⏱ Simples
    </button>
</div>

<div class="study-layout">

<!-- ── Timer principal ─────────────────────────────────────── -->
<div class="card" style="text-align:center;padding:32px 28px">

    <!-- Seleção de matéria / tópico -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;text-align:left">
        <div class="form-group" style="margin:0">
            <label class="form-label" for="select-subject">Matéria *</label>
            <select class="form-select" id="select-subject" onchange="loadTopics(this.value)">
                <option value="">— Selecione —</option>
                <?php foreach ($subjects as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (int)($_GET['subject_id'] ?? 0)===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label class="form-label" for="select-topic">Tópico (opcional)</label>
            <select class="form-select" id="select-topic">
                <option value="">— Nenhum —</option>
            </select>
        </div>
    </div>

    <div class="duration-presets" aria-label="Duração do estudo">
        <span>Tempo para estudar</span>
        <?php foreach([25,45,60] as $minutes): ?><button type="button" class="btn btn-ghost" onclick="chooseDuration(<?= $minutes ?>)"><?= $minutes ?> min</button><?php endforeach; ?>
    </div>
    <details class="timer-customize" id="timer-customize"><summary>Personalizar sessão e pausas</summary>
    <!-- Modo Pomodoro: config visível -->
    <div id="mode-pomodoro-config" style="margin-bottom:20px">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:left">
            <div>
                <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">TRABALHO (min)</label>
                <input type="number" id="pm-work" class="form-input" value="<?= $cfg['work_minutes'] ?>" min="1" max="90" style="text-align:center;font-size:16px;font-weight:700" oninput="recalcPomodoro()">
            </div>
            <div>
                <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">PAUSA CURTA</label>
                <input type="number" id="pm-short" class="form-input" value="<?= $cfg['short_break_min'] ?>" min="1" max="30" style="text-align:center;font-size:16px;font-weight:700">
            </div>
            <div>
                <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">PAUSA LONGA</label>
                <input type="number" id="pm-long" class="form-input" value="<?= $cfg['long_break_min'] ?>" min="5" max="60" style="text-align:center;font-size:16px;font-weight:700">
            </div>
            <div>
                <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">CICLOS</label>
                <input type="number" id="pm-cycles" class="form-input" value="<?= $cfg['cycles_before_long'] ?>" min="1" max="10" style="text-align:center;font-size:16px;font-weight:700">
            </div>
        </div>
    </div>

    <!-- Modo Tempo Total: calcula automaticamente -->
    <div id="mode-smart-config" style="display:none;margin-bottom:20px">
        <div style="background:var(--bg3);border-radius:12px;padding:16px;text-align:left">
            <div style="font-size:13px;color:var(--muted);margin-bottom:12px;font-weight:600">🧠 Defina o tempo total e quantas pausas quer ter:</div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
                <div>
                    <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">TEMPO TOTAL (min)</label>
                    <input type="number" id="sm-total" class="form-input" value="60" min="10" max="480" style="text-align:center;font-size:18px;font-weight:700;color:var(--primary-lt)" oninput="recalcSmart()">
                </div>
                <div>
                    <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">Nº DE PAUSAS</label>
                    <input type="number" id="sm-breaks" class="form-input" value="4" min="1" max="20" style="text-align:center;font-size:18px;font-weight:700;color:var(--accent)" oninput="recalcSmart()">
                </div>
                <div>
                    <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:4px">PAUSA (min)</label>
                    <input type="number" id="sm-break-dur" class="form-input" value="5" min="1" max="30" style="text-align:center;font-size:18px;font-weight:700;color:var(--green)" oninput="recalcSmart()">
                </div>
            </div>
            <div id="smart-result" style="margin-top:12px;padding:10px;background:var(--card);border-radius:8px;font-size:13px;color:var(--text);line-height:1.7"></div>
        </div>
    </div>

    <!-- Modo Simples -->
    <div id="mode-simple-config" style="display:none;margin-bottom:20px">
        <div style="background:var(--bg3);border-radius:12px;padding:16px;text-align:left">
            <label style="font-size:11px;color:var(--muted);font-weight:600;display:block;margin-bottom:8px">TEMPO DE ESTUDO (min)</label>
            <input type="number" id="si-minutes" class="form-input" value="60" min="1" max="480"
                style="text-align:center;font-size:32px;font-weight:700;color:var(--primary-lt);height:64px">
            <div style="font-size:13px;color:var(--muted);margin-top:8px">Sem pausas automáticas — timer contínuo</div>
        </div>
    </div>

    </details>
    <!-- Display do timer -->
    <div class="timer-display" id="timer-display">25:00</div>
    <div class="timer-label" id="timer-label">Pronto</div>

    <!-- Barra de progresso da sessão -->
    <div style="margin:12px auto;max-width:360px">
        <div style="background:var(--bg3);border-radius:4px;height:4px;overflow:hidden">
            <div id="session-progress" style="height:100%;background:var(--primary);border-radius:4px;width:0%;transition:width .5s"></div>
        </div>
    </div>

    <!-- Dots dos ciclos -->
    <div class="timer-dots" id="timer-dots">
        <?php for ($i = 0; $i < $cfg['cycles_before_long']; $i++): ?>
            <div class="timer-dot"></div>
        <?php endfor; ?>
    </div>

    <!-- Botões de controle -->
    <div style="display:flex;justify-content:center;gap:12px;margin-top:24px;flex-wrap:wrap">
        <button class="btn btn-primary" id="timer-start" style="min-width:130px" onclick="timerStart()">
            ▶ Iniciar
        </button>
        <button class="btn btn-ghost" id="timer-pause" style="display:none;min-width:130px" onclick="TIMER && TIMER.pause()">
            ⏸ Pausar
        </button>
        <button class="btn btn-ghost" id="timer-reset" onclick="TIMER && TIMER.reset()">
            ↺ Reiniciar
        </button>
    </div>

    <!-- Painel de finalizar sessão -->
    <div id="finish-panel" style="display:none;margin-top:24px;border-top:1px solid var(--border);padding-top:20px">
        <p style="font-size:14px;color:var(--muted);margin-bottom:12px">Como foi a sessão?</p>
        <div class="stars" id="quality-stars" style="justify-content:center;margin-bottom:12px">
            <span class="star">★</span><span class="star">★</span><span class="star">★</span>
            <span class="star">★</span><span class="star">★</span>
        </div>
        <input type="hidden" id="quality-input" value="3">
        <textarea class="form-textarea" id="session-notes" placeholder="Anotações rápidas..."
            style="max-width:400px;margin:0 auto 12px;display:block"></textarea>
        <button class="btn btn-success" onclick="finishSession()">✓ Finalizar Sessão</button>
        <button class="btn btn-ghost" style="margin-left:8px"
            onclick="document.getElementById('finish-panel').style.display='none'">Cancelar</button>
    </div>
</div>

<!-- ── Painel lateral ──────────────────────────────────────── -->
<div style="display:flex;flex-direction:column;gap:14px">

    <!-- Sessão ativa -->
    <div class="card" id="active-info" style="display:none;border-color:var(--green)">
        <div class="card-title">🟢 Em andamento</div>
        <div id="active-subject" style="font-weight:700;font-size:15px;margin-bottom:2px"></div>
        <div id="active-topic" style="font-size:13px;color:var(--muted);margin-bottom:10px"></div>
        <div style="font-size:12px;color:var(--muted)">Iniciado às <span id="active-start-time"></span></div>
        <button class="btn btn-ghost btn-sm" style="margin-top:10px;width:100%;justify-content:center"
            onclick="document.getElementById('finish-panel').style.display='block'">
            Encerrar sessão
        </button>
    </div>

    <!-- Opções -->
    <details class="card timer-options">
        <summary>Preferências de estudo</summary>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:14px">
            <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer">
                <span style="color:var(--muted)">🔊 Som de alerta</span>
                <input type="checkbox" id="opt-sound" <?= $cfg['sound_enabled']?'checked':'' ?> style="accent-color:var(--primary);width:18px;height:18px">
            </label>
            <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer">
                <span style="color:var(--muted)">▶ Auto-iniciar pausa</span>
                <input type="checkbox" id="opt-autobreak" <?= $cfg['auto_start_break']?'checked':'' ?> style="accent-color:var(--primary);width:18px;height:18px">
            </label>
            <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer">
                <span style="color:var(--muted)">▶ Auto-iniciar estudo</span>
                <input type="checkbox" id="opt-autowork" <?= $cfg['auto_start_work']?'checked':'' ?> style="accent-color:var(--primary);width:18px;height:18px">
            </label>
        </div>
        <button class="btn btn-ghost btn-sm" style="margin-top:14px;width:100%;justify-content:center" onclick="saveConfig()">
            Salvar preferências
        </button>
    </details>

    <a class="btn btn-ghost" href="<?= BASE_URL ?>/index.php?page=focus">Abrir modo foco</a>
    <!-- Como funciona (por modo) -->
    <div class="card" style="font-size:13px;color:var(--muted);line-height:1.8" id="how-it-works">
        <div class="card-title">Como funciona</div>
        <div id="hint-pomodoro">
            ① Selecione matéria e tópico<br>
            ② Escolha a duração do estudo<br>
            ③ Clique <strong style="color:var(--text)">Iniciar</strong><br>
            ④ Estude no tempo configurado<br>
            ⑤ Descanse na pausa automática<br>
            ⑥ Finalize para ganhar XP 🏆
        </div>
        <div id="hint-smart" style="display:none">
            ① Defina o tempo total (ex: 60 min)<br>
            ② Escolha quantas pausas quer<br>
            ③ O sistema divide automaticamente<br>
            Ex: 60 min + 4 pausas de 5 min =<br>
            <strong style="color:var(--text)">4 blocos de 11min + 1 bloco de 11min</strong>
        </div>
        <div id="hint-simple" style="display:none">
            ① Defina o tempo desejado<br>
            ② Timer contínuo sem pausas<br>
            ③ Finalize quando quiser
        </div>
    </div>
</div>

</div><!-- end grid -->

<script>
let currentMode = 'pomodoro';

document.addEventListener('DOMContentLoaded', () => {
    initStars('quality-stars', 'quality-input');
    loadTopics(document.getElementById('select-subject').value);
    recalcSmart();
    buildTimer();
});

// ── Troca de modo ────────────────────────────────────────────
function setMode(mode) {
    if (TIMER?.sessionId) { toast('Encerre a sessão atual antes de mudar o modo.', 'error'); return; }
    document.getElementById('timer-customize').open = mode !== 'pomodoro';
    currentMode = mode;
    ['pomodoro','smart','simple'].forEach(m => {
        document.getElementById('mode-'+m+'-config').style.display = m===mode?'block':'none';
        document.getElementById('hint-'+m).style.display = m===mode?'block':'none';
        const tab = document.getElementById('tab-'+m);
        tab.style.background = m===mode?'var(--primary)':'transparent';
        tab.style.color      = m===mode?'var(--on-primary)':'var(--muted)';
    });

    // Dots: só aparecem no pomodoro
    const dots = document.getElementById('timer-dots');
    dots.style.display = mode==='pomodoro'?'flex':'none';

    if (TIMER) TIMER.reset();
    buildTimer();
}

function chooseDuration(minutes) {
    if (TIMER?.sessionId) { toast('Encerre a sessão atual antes de alterar a duração.', 'error'); return; }
    const id = currentMode === 'pomodoro' ? 'pm-work' : currentMode === 'smart' ? 'sm-total' : 'si-minutes';
    document.getElementById(id).value = minutes;
    recalcSmart();
    buildTimer();
}
function recalcPomodoro() {
    if (!TIMER?.sessionId) buildTimer();
}

// ── Recalcula modo inteligente ───────────────────────────────
function recalcSmart() {
    const total    = parseInt(document.getElementById('sm-total').value) || 60;
    const breaks   = parseInt(document.getElementById('sm-breaks').value) || 4;
    const breakDur = parseInt(document.getElementById('sm-break-dur').value) || 5;

    const totalBreakTime = breaks * breakDur;
    const studyTime      = total - totalBreakTime;

    if (studyTime <= 0) {
        document.getElementById('smart-result').innerHTML =
            '<span style="color:var(--red)">⚠ Tempo de pausa maior que o total. Ajuste os valores.</span>';
        return;
    }

    const blocks   = breaks + 1;  // blocos de estudo = pausas + 1
    const perBlock = Math.floor(studyTime / blocks);
    const remainder = studyTime - (perBlock * blocks);

    document.getElementById('smart-result').innerHTML =
        `📊 <strong>${blocks} blocos de estudo</strong> de <strong style="color:var(--primary-lt)">${perBlock} min</strong>` +
        (remainder > 0 ? ` (último com +${remainder} min)` : '') +
        `<br>⏸ <strong>${breaks} pausas</strong> de <strong style="color:var(--green)">${breakDur} min</strong>` +
        `<br>⏱ Tempo de estudo: <strong>${studyTime} min</strong> | Total real: <strong>${total} min</strong>`;
}

// ── Constrói o timer conforme o modo ────────────────────────
function buildTimer() {
    if (TIMER) TIMER.reset();

    let cfg = {};

    if (currentMode === 'pomodoro') {
        cfg = {
            work_minutes:       parseInt(document.getElementById('pm-work').value)   || 25,
            short_break_min:    parseInt(document.getElementById('pm-short').value)  || 5,
            long_break_min:     parseInt(document.getElementById('pm-long').value)   || 15,
            cycles_before_long: parseInt(document.getElementById('pm-cycles').value) || 4,
            auto_start_break:   document.getElementById('opt-autobreak').checked ? 1 : 0,
            auto_start_work:    document.getElementById('opt-autowork').checked  ? 1 : 0,
            sound_enabled:      document.getElementById('opt-sound').checked     ? 1 : 0,
        };

        // Atualiza dots
        const dotsEl = document.getElementById('timer-dots');
        dotsEl.innerHTML = '';
        for (let i = 0; i < cfg.cycles_before_long; i++) {
            const d = document.createElement('div'); d.className = 'timer-dot'; dotsEl.appendChild(d);
        }

    } else if (currentMode === 'smart') {
        const total    = parseInt(document.getElementById('sm-total').value)     || 60;
        const breaks   = parseInt(document.getElementById('sm-breaks').value)    || 4;
        const breakDur = parseInt(document.getElementById('sm-break-dur').value) || 5;
        const studyTime = total - (breaks * breakDur);
        const blocks    = breaks + 1;
        const perBlock  = Math.max(1, Math.floor(studyTime / blocks));

        cfg = {
            work_minutes:       perBlock,
            short_break_min:    breakDur,
            long_break_min:     breakDur,
            cycles_before_long: breaks,   // depois de N ciclos vai para pausa longa (= pausa igual)
            auto_start_break:   document.getElementById('opt-autobreak').checked ? 1 : 0,
            auto_start_work:    document.getElementById('opt-autowork').checked  ? 1 : 0,
            sound_enabled:      document.getElementById('opt-sound').checked     ? 1 : 0,
            total_blocks:       blocks,
            is_smart:           true,
        };

    } else { // simple
        const min = parseInt(document.getElementById('si-minutes').value) || 60;
        cfg = {
            work_minutes:       min,
            short_break_min:    0,
            long_break_min:     0,
            cycles_before_long: 99,
            auto_start_break:   0,
            auto_start_work:    0,
            sound_enabled:      document.getElementById('opt-sound').checked ? 1 : 0,
            is_simple:          true,
        };
    }

    TIMER = new PomodoroTimer(cfg);
}

// ── Inicia timer (botão) ─────────────────────────────────────
async function timerStart() {
    const button = document.getElementById('timer-start');
    if (button.disabled) return;
    button.disabled = true;
    try {
        if (!TIMER || TIMER.state === 'idle') buildTimer();
        await TIMER.start();
    } finally {
        button.disabled = false;
    }
    // Atualiza hora de início
    document.getElementById('active-start-time').textContent = new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
}

// ── Salva config ─────────────────────────────────────────────
async function saveConfig() {
    const data = {
        work_minutes:       document.getElementById('pm-work').value,
        short_break_min:    document.getElementById('pm-short').value,
        long_break_min:     document.getElementById('pm-long').value,
        cycles_before_long: document.getElementById('pm-cycles').value,
        sound_enabled:      document.getElementById('opt-sound').checked     ? 1 : 0,
        auto_start_break:   document.getElementById('opt-autobreak').checked ? 1 : 0,
        auto_start_work:    document.getElementById('opt-autowork').checked  ? 1 : 0,
    };
    try {
        const result = await api('pomodoro_config', data, 'POST');
        if (result.error) throw new Error(result.error);
        toast('Preferências salvas!', 'success');
    } catch (error) {
        toast('Não foi possível salvar. Verifique sua conexão e tente novamente.', 'error');
    }
}

// ── Finaliza sessão ──────────────────────────────────────────
async function finishSession() {
    const quality = parseInt(document.getElementById('quality-input').value) || 3;
    const notes   = document.getElementById('session-notes').value || '';
    const res     = await TIMER.finish(quality, notes);
    if (res) {
        document.getElementById('finish-panel').style.display = 'none';
        document.getElementById('active-info').style.display  = 'none';
        document.getElementById('session-notes').value = '';
        toast('Sessão finalizada! ' + (res.minutes||0) + 'min de foco 🎯', 'success');
    }
}

// Recalcula ao mudar inputs do smart mode
['sm-total','sm-breaks','sm-break-dur'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', recalcSmart);
});
</script>
