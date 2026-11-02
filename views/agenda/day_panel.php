<?php
// views/agenda/day_panel.php — painel expandido inline no calendário
// Variáveis disponíveis: $selectedDay, $year, $month, $dayNotes, $dayTasks, $dayStickies, $subjects
?>
<div class="day-expanded" style="grid-column:1/-1">
    <?php
    $ts  = strtotime($selectedDay);
    $dowN= ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    $monN= ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $label = $dowN[date('w',$ts)].', '.date('d',$ts).' de '.$monN[(int)date('n',$ts)].' de '.date('Y',$ts);
    $isToday2 = ($selectedDay === date('Y-m-d'));
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
        <div style="font-size:17px;font-weight:700;color:var(--primary-lt)">
            <?= $label ?>
            <?= $isToday2?'<span style="font-size:12px;background:var(--primary);color:#fff;padding:2px 9px;border-radius:8px;margin-left:6px;vertical-align:middle">Hoje</span>':'' ?>
        </div>
        <a href="?page=agenda&year=<?= $year ?>&month=<?= $month ?>" class="btn btn-ghost btn-sm">✕ Fechar</a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 280px;gap:16px;align-items:start">

    <!-- Anotações -->
    <div>
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">📝 Anotações do dia</div>
        <textarea id="day-notes-<?= e($selectedDay) ?>" rows="8"
            style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--font);font-size:14px;padding:12px;outline:none;resize:vertical;line-height:1.7;transition:border-color .15s"
            placeholder="O que pretendo estudar hoje...&#10;Metas do dia...&#10;Lembretes importantes..."
            onfocus="this.style.borderColor='var(--primary)'"
            onblur="this.style.borderColor='var(--border)'"
            oninput="markNotesDirty('<?= e($selectedDay) ?>')"
        ><?= e($dayNotes) ?></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
            <span id="notes-status-<?= e($selectedDay) ?>" style="font-size:11px;color:var(--muted)">Ctrl+S para salvar</span>
            <button class="btn btn-primary btn-sm" onclick="saveNotes('<?= e($selectedDay) ?>')">💾 Salvar</button>
        </div>
    </div>

    <!-- Tarefas -->
    <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em">✅ Tarefas</div>
            <button class="btn btn-ghost btn-sm" onclick="toggleEl('form-task-<?= e($selectedDay) ?>')">+ Add</button>
        </div>

        <!-- Form nova tarefa -->
        <div id="form-task-<?= e($selectedDay) ?>" style="display:none;margin-bottom:10px;padding:10px;background:var(--bg3);border-radius:10px">
            <form method="POST" action="index.php?page=agenda">
                <input type="hidden" name="_action" value="add_task">
                <input type="hidden" name="date"  value="<?= e($selectedDay) ?>">
                <input type="hidden" name="year"  value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="text" name="title" class="form-input" required placeholder="Ex: Estudar capítulo 5..." style="margin-bottom:8px;font-size:14px">
                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                    <select name="priority" class="form-select" style="width:auto;font-size:13px">
                        <option value="1">🟢 Baixa</option><option value="2" selected>🟡 Média</option><option value="3">🔴 Alta</option>
                    </select>
                    <?php foreach(['#6366f1','#f472b6','#f59e0b','#22c55e','#ef4444'] as $c): ?>
                    <label style="cursor:pointer;display:flex">
                        <input type="radio" name="color" value="<?= $c ?>" <?= $c==='#6366f1'?'checked':'' ?> style="display:none" class="tcr2">
                        <span class="tcs2" style="width:18px;height:18px;border-radius:50%;background:<?= $c ?>;border:3px solid <?= $c==='#6366f1'?'#fff':'transparent' ?>;display:block"></span>
                    </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </div>
            </form>
        </div>

        <!-- Lista de tarefas -->
        <?php if(empty($dayTasks)): ?>
        <div style="font-size:13px;color:var(--muted);padding:8px 0">Nenhuma tarefa.</div>
        <?php endif; ?>
        <?php foreach($dayTasks as $t): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid var(--border)">
            <form method="POST" action="index.php?page=agenda" style="flex-shrink:0">
                <input type="hidden" name="_action" value="toggle_task">
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <input type="hidden" name="date" value="<?= e($selectedDay) ?>">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <button type="submit" style="width:20px;height:20px;border-radius:50%;border:2px solid <?= e($t['color']) ?>;background:<?= $t['is_done']?e($t['color']):'transparent' ?>;cursor:pointer;display:flex;align-items:center;justify-content:center">
                    <?= $t['is_done']?'<span style="color:#fff;font-size:10px">✓</span>':'' ?>
                </button>
            </form>
            <span style="flex:1;font-size:13px;<?= $t['is_done']?'text-decoration:line-through;color:var(--muted)':'' ?>"><?= e($t['title']) ?></span>
            <span style="font-size:10px;padding:1px 6px;border-radius:5px;font-weight:600;white-space:nowrap;background:<?= ['','rgba(34,197,94,.15)','rgba(245,158,11,.15)','rgba(239,68,68,.15)'][$t['priority']] ?>;color:<?= ['','var(--green)','var(--accent)','var(--red)'][$t['priority']] ?>"><?= ['','Baixa','Média','Alta'][$t['priority']] ?></span>
            <form method="POST" action="index.php?page=agenda">
                <input type="hidden" name="_action" value="delete_task"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="date" value="<?= e($selectedDay) ?>"><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
                <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:13px" onclick="return confirm('Excluir?')">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Post-its -->
    <div>
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">📌 Post-its</div>
        <form method="POST" action="index.php?page=agenda" style="margin-bottom:10px">
            <input type="hidden" name="_action" value="add_sticky">
            <input type="hidden" name="date" value="<?= e($selectedDay) ?>">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <textarea name="content" style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:var(--font);font-size:13px;padding:8px;outline:none;resize:none;height:52px;margin-bottom:6px" placeholder="Lembrete..."></textarea>
            <div style="display:flex;gap:5px;align-items:center" id="sc-<?= e($selectedDay) ?>">
                <?php foreach(['#fbbf24','#f472b6','#34d399','#60a5fa','#a78bfa'] as $c): ?>
                <label style="cursor:pointer"><input type="radio" name="color" value="<?= $c ?>" <?= $c==='#fbbf24'?'checked':'' ?> class="scr3" style="display:none">
                <span class="scs3" style="width:18px;height:18px;border-radius:50%;background:<?= $c ?>;border:3px solid <?= $c==='#fbbf24'?'#fff':'transparent' ?>;display:block"></span></label>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto;font-size:12px;padding:4px 10px">+ Add</button>
            </div>
        </form>
        <div style="display:flex;flex-direction:column;gap:6px">
            <?php foreach($dayStickies as $s): ?>
            <div style="background:<?= e($s['color']) ?>;border-radius:8px;padding:10px 10px 22px;position:relative;box-shadow:1px 2px 6px rgba(0,0,0,.2)">
                <p style="font-size:12px;color:#1a1a1a;line-height:1.5;word-break:break-word"><?= nl2br(e($s['content'])) ?></p>
                <form method="POST" action="index.php?page=agenda" style="position:absolute;bottom:4px;right:5px">
                    <input type="hidden" name="_action" value="delete_sticky"><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="date" value="<?= e($selectedDay) ?>"><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $month ?>">
                    <button type="submit" style="background:rgba(0,0,0,.15);border:none;border-radius:4px;padding:1px 6px;cursor:pointer;font-size:11px;color:#333" onclick="return confirm('Remover?')">✕</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    </div><!-- end 3-col grid -->
</div><!-- end day-expanded -->

<script>
// Color pickers do painel
document.querySelectorAll('.tcr2').forEach(r=>{
    r.addEventListener('change',()=>{document.querySelectorAll('.tcs2').forEach(s=>s.style.borderColor='transparent');r.nextElementSibling.style.borderColor='#fff';});
    if(r.checked)r.nextElementSibling.style.borderColor='#fff';
});
document.querySelectorAll('.scr3').forEach(r=>{
    r.addEventListener('change',()=>{document.querySelectorAll('.scs3').forEach(s=>s.style.borderColor='transparent');r.nextElementSibling.style.borderColor='#fff';});
    if(r.checked)r.nextElementSibling.style.borderColor='#fff';
});

function toggleEl(id){const el=document.getElementById(id);if(el)el.style.display=el.style.display==='none'?'block':'none';}

let notesDirtyMap={}, notesTimers={};
function markNotesDirty(date){
    notesDirtyMap[date]=true;
    const st=document.getElementById('notes-status-'+date);
    if(st){st.textContent='Não salvo...';st.style.color='var(--accent)';}
    clearTimeout(notesTimers[date]);
    notesTimers[date]=setTimeout(()=>saveNotes(date),3000);
}

async function saveNotes(date){
    const ta=document.getElementById('day-notes-'+date); if(!ta)return;
    const st=document.getElementById('notes-status-'+date);
    try{
        const res=await fetch('index.php?page=agenda',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({_action:'save_notes',date,year:'<?= $year ?>',month:'<?= $month ?>',notes:ta.value})
        });
        const data=await res.json();
        if(st){st.textContent='Salvo às '+data.saved;st.style.color='var(--green)';}
        notesDirtyMap[date]=false;
    }catch(e){if(st){st.textContent='Erro ao salvar';st.style.color='var(--red)';}}
}

// Ctrl+S
document.addEventListener('keydown',e=>{
    if((e.ctrlKey||e.metaKey)&&e.key==='s'){
        e.preventDefault();
        const date='<?= e($selectedDay??'') ?>';
        if(date) saveNotes(date);
    }
});
</script>
