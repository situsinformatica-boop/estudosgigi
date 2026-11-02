<?php $subjects = (new SubjectModel())->getAll(); ?>
<div class="page-header">
    <div><div class="page-title">🔆 Modo Foco</div><div class="page-subtitle">Elimine as distrações. Foque no que importa.</div></div>
</div>
<div style="max-width:500px;margin:0 auto;text-align:center">
    <div class="card" style="padding:32px">
        <div class="form-group"><label class="form-label">O que vai estudar?</label>
            <select class="form-select" id="focus-subject" style="font-size:15px">
                <option value="">— Selecione uma matéria —</option>
                <?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label">Objetivo desta sessão</label>
            <input type="text" class="form-input" id="focus-goal" placeholder="Ex: Terminar o capítulo 5" style="text-align:center"></div>
        <div class="form-group"><label class="form-label">Duração</label>
            <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap">
                <?php foreach([25,45,60,90,120] as $m): ?>
                <button class="btn btn-ghost btn-sm focus-dur" data-min="<?= $m ?>" onclick="selDur(<?= $m ?>,this)"><?= $m ?>min</button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="focus-min" value="25"></div>
        <button class="btn btn-primary" style="width:100%;padding:14px;font-size:16px" onclick="enterFocus()">🔆 Entrar no Modo Foco</button>
    </div>
</div>

<!-- Overlay foco -->
<div id="focus-overlay" style="display:none;position:fixed;inset:0;background:#0f1117;z-index:9000;flex-direction:column;align-items:center;justify-content:center;text-align:center">
    <button onclick="exitFocus()" style="position:absolute;top:20px;right:20px;background:none;border:1px solid rgba(255,255,255,.15);color:var(--muted);border-radius:8px;padding:6px 14px;cursor:pointer;font-family:var(--font)">✕ Sair</button>
    <div style="font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px" id="fo-subject"></div>
    <div style="font-size:18px;font-weight:700;margin-bottom:48px;max-width:400px" id="fo-goal"></div>
    <div style="font-family:var(--mono);font-size:96px;font-weight:700;line-height:1;letter-spacing:-4px" id="fo-timer">00:00</div>
    <div style="font-size:13px;color:var(--muted);margin-top:12px;letter-spacing:.08em;text-transform:uppercase" id="fo-status">Pronto</div>
    <div style="display:flex;gap:14px;margin-top:48px">
        <button id="fo-start" onclick="focusStart()" style="min-width:130px;padding:12px 24px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer">▶ Iniciar</button>
        <button id="fo-pause" onclick="focusPause()" style="display:none;min-width:130px;padding:12px 24px;background:transparent;color:var(--muted);border:1px solid rgba(255,255,255,.15);border-radius:10px;font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer">⏸ Pausar</button>
        <button onclick="focusQuit()" style="padding:12px 24px;background:transparent;color:var(--muted);border:1px solid rgba(255,255,255,.1);border-radius:10px;font-family:var(--font);font-size:15px;cursor:pointer">Encerrar</button>
    </div>
    <div id="fo-done" style="display:none;margin-top:36px">
        <div style="font-size:48px;margin-bottom:12px">🎉</div>
        <div style="font-size:20px;font-weight:700;margin-bottom:8px">Sessão concluída!</div>
        <p style="color:var(--muted);font-size:14px;margin-bottom:20px">Excelente foco. Continue assim!</p>
        <button onclick="exitFocus()" style="padding:12px 28px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer">Voltar ao dashboard</button>
    </div>
</div>

<script>
let foIv=null,foRem=0,foSessId=null;
function selDur(m,btn){document.getElementById('focus-min').value=m;document.querySelectorAll('.focus-dur').forEach(b=>b.className='btn btn-ghost btn-sm focus-dur');btn.className='btn btn-primary btn-sm focus-dur';}
document.querySelector('[data-min="25"]')?.click();

async function enterFocus(){
    const subId=document.getElementById('focus-subject').value;
    if(!subId){toast('Selecione uma matéria!','error');return;}
    foRem=parseInt(document.getElementById('focus-min').value)*60;
    document.getElementById('fo-subject').textContent=document.getElementById('focus-subject').options[document.getElementById('focus-subject').selectedIndex]?.text||'';
    document.getElementById('fo-goal').textContent=document.getElementById('focus-goal').value||'Foco total';
    document.getElementById('fo-timer').textContent=fmt(foRem);
    document.getElementById('fo-status').textContent='Pronto';
    document.getElementById('fo-done').style.display='none';
    document.getElementById('fo-start').style.display='';
    document.getElementById('fo-pause').style.display='none';
    const ov=document.getElementById('focus-overlay');ov.style.display='flex';
    const res=await api('session_start',{subject_id:subId,session_type:'study'},'POST');
    foSessId=res.session_id;
}
function focusStart(){
    document.getElementById('fo-start').style.display='none';document.getElementById('fo-pause').style.display='';
    document.getElementById('fo-status').textContent='Em foco...';
    foIv=setInterval(()=>{foRem--;document.getElementById('fo-timer').textContent=fmt(foRem);document.title=fmt(foRem)+' — Foco';if(foRem<=0){clearInterval(foIv);focusDone();}},1000);
}
function focusPause(){clearInterval(foIv);document.getElementById('fo-start').style.display='';document.getElementById('fo-pause').style.display='none';document.getElementById('fo-status').textContent='Pausado';}
async function focusDone(){
    document.getElementById('fo-status').textContent='Concluído!';document.getElementById('fo-done').style.display='block';
    document.getElementById('fo-start').style.display='none';document.getElementById('fo-pause').style.display='none';
    if(foSessId){const res=await api('session_finish',{session_id:foSessId,quality:4},'POST');showXP(res.xp_earned,res.xp_result?.leveled_up);}
    foSessId=null;
}
async function focusQuit(){
    if(!confirm('Encerrar antes do tempo?'))return;clearInterval(foIv);
    if(foSessId){const res=await api('session_finish',{session_id:foSessId,quality:3},'POST');showXP(res.xp_earned);}
    exitFocus();
}
function exitFocus(){clearInterval(foIv);document.getElementById('focus-overlay').style.display='none';document.title='StudyTracker';foSessId=null;}
function fmt(s){return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');}
</script>
