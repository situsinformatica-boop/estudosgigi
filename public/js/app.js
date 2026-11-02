// ============================================================
//  Estudos GiGi — app.js  v9
// ============================================================

// Aplica tema antes do render
(function(){
    const t=document.documentElement.getAttribute('data-theme')||localStorage.getItem('gigi_theme')||'dark';
    document.documentElement.setAttribute('data-theme',t);
})();

// ── Toast ────────────────────────────────────────────────────
function toast(msg,type='success',ms=3500){
    const el=document.getElementById('toast'); if(!el)return;
    el.textContent=msg; el.className='toast '+type; el.style.display='block';
    clearTimeout(el._t); el._t=setTimeout(()=>el.style.display='none',ms);
}

// ── AJAX ─────────────────────────────────────────────────────
async function api(action,data={},method='GET'){
    const url='index.php?page=api&action='+action;
    const opts={method};
    if(method==='POST'){
        opts.body=new URLSearchParams(data);
        opts.headers={'Content-Type':'application/x-www-form-urlencoded'};
    }
    const res=await fetch(url,opts);
    return res.json();
}

function showXP(amount,leveledUp){
    if(amount>0) toast('+'+amount+' XP'+(leveledUp?' — NÍVEL UP! 🎉':''),'xp');
}

// ── Stars ────────────────────────────────────────────────────
function initStars(containerId,inputId){
    const c=document.getElementById(containerId); if(!c)return;
    const inp=document.getElementById(inputId);
    const stars=c.querySelectorAll('.star');
    stars.forEach((s,i)=>{
        s.addEventListener('mouseenter',()=>stars.forEach((x,j)=>x.classList.toggle('active',j<=i)));
        s.addEventListener('mouseleave',()=>{const v=parseInt(inp.value||0);stars.forEach((x,j)=>x.classList.toggle('active',j<v));});
        s.addEventListener('click',()=>{inp.value=i+1;stars.forEach((x,j)=>x.classList.toggle('active',j<=i));});
    });
    inp.value=3; stars.forEach((x,j)=>x.classList.toggle('active',j<3));
}

// ── Tópicos ──────────────────────────────────────────────────
async function loadTopics(subjectId){
    const sel=document.getElementById('select-topic'); if(!sel)return;
    sel.innerHTML='<option value="">— Nenhum —</option>';
    if(!subjectId)return;
    try{
        const res=await api('get_topics&subject_id='+subjectId);
        if(Array.isArray(res)) res.forEach(t=>{
            const opt=document.createElement('option');
            opt.value=t.id; opt.textContent=t.name+(t.difficulty>1?' '+['','','⭐⭐','⭐⭐⭐'][t.difficulty]:'');
            sel.appendChild(opt);
        });
    }catch(e){}
}

// ── Tema ─────────────────────────────────────────────────────
function applyTheme(theme){
    document.documentElement.setAttribute('data-theme',theme);
    localStorage.setItem('gigi_theme',theme);
    document.querySelectorAll('.theme-btn').forEach(b=>b.classList.toggle('selected',b.dataset.theme===theme));
}

// ============================================================
//  SOM
// ============================================================
const Sound={
    _ctx:null,
    _getCtx(){
        if(!this._ctx) this._ctx=new(window.AudioContext||window.webkitAudioContext)();
        if(this._ctx.state==='suspended') this._ctx.resume();
        return this._ctx;
    },
    _beep(freq,dur,gain=0.4,type='sine'){
        try{
            const ctx=this._getCtx(),osc=ctx.createOscillator(),vol=ctx.createGain();
            osc.connect(vol); vol.connect(ctx.destination);
            osc.type=type; osc.frequency.value=freq;
            vol.gain.setValueAtTime(0,ctx.currentTime);
            vol.gain.linearRampToValueAtTime(gain,ctx.currentTime+0.01);
            vol.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+dur);
            osc.start(ctx.currentTime); osc.stop(ctx.currentTime+dur+0.05);
        }catch(e){}
    },
    workEnd(){ this._beep(523,.18,.5); setTimeout(()=>this._beep(659,.18,.5),220); setTimeout(()=>this._beep(784,.35,.6),440); },
    breakEnd(){ this._beep(784,.18,.4); setTimeout(()=>this._beep(523,.35,.5),220); },
    confirm(){ this._beep(880,.12,.25); },
};

// ============================================================
//  TIMER STATE — persiste entre páginas via localStorage
// ============================================================
const TS_KEY = 'gigi_timer_state';

function saveTimerState(data){
    if(data===null){ localStorage.removeItem(TS_KEY); return; }
    localStorage.setItem(TS_KEY, JSON.stringify({...data, _saved: Date.now()}));
}

function loadTimerState(){
    try{
        const raw=localStorage.getItem(TS_KEY);
        if(!raw)return null;
        const s=JSON.parse(raw);
        // Ajusta o remaining pelo tempo que passou desde o último save
        const elapsed=Math.floor((Date.now()-s._saved)/1000);
        if(s.state==='running') s.remaining=Math.max(0,s.remaining-elapsed);
        if(s.state==='break')   s.remaining=Math.max(0,s.remaining-elapsed);
        return s;
    }catch(e){return null;}
}

// ============================================================
//  TIMER FLUTUANTE (persiste entre páginas)
// ============================================================
function createFloatingTimer(){
    if(document.getElementById('floating-timer'))return;
    const el=document.createElement('div');
    el.id='floating-timer';
    el.innerHTML=`
    <div id="ft-inner" style="
        position:fixed;top:16px;right:16px;z-index:10000;
        background:var(--card);border:2px solid var(--primary);
        border-radius:14px;padding:10px 14px;min-width:180px;
        box-shadow:0 4px 20px rgba(0,0,0,.5);
        display:none;cursor:pointer;user-select:none;
        transition:border-color .3s,background .3s;
    " ondblclick="ftGoToTimer()">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div>
                <div id="ft-phase" style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:2px">Timer</div>
                <div id="ft-time"  style="font-size:22px;font-weight:700;font-family:var(--mono);color:var(--text);line-height:1">00:00</div>
                <div id="ft-subj"  style="font-size:11px;color:var(--muted);margin-top:2px;max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:5px">
                <button id="ft-btn-pp"   onclick="ftPlayPause(event)" style="width:32px;height:32px;border-radius:8px;background:var(--primary);border:none;color:#fff;cursor:pointer;font-size:14px">⏸</button>
                <button id="ft-btn-stop" onclick="ftStop(event)"      style="width:32px;height:32px;border-radius:8px;background:var(--bg3);border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:12px">⏹</button>
            </div>
        </div>
        <!-- Barra de progresso compacta -->
        <div style="margin-top:8px;height:3px;background:var(--bg3);border-radius:2px;overflow:hidden">
            <div id="ft-progress" style="height:100%;background:var(--primary);border-radius:2px;width:0%;transition:width .5s"></div>
        </div>
    </div>`;
    document.body.appendChild(el);
}

function ftGoToTimer(){
    // Duplo clique → vai para a página do timer
    const base = document.querySelector('base')?.href || window.location.href.split('/index.php')[0];
    window.location.href = 'index.php?page=timer';
}

function ftPlayPause(e){
    e && e.stopPropagation();
    if(TIMER){
        if(TIMER.state==='running') TIMER.pause();
        else if(TIMER.state==='paused'||TIMER.state==='break'||TIMER.state==='idle') TIMER.start();
    }
}

function ftStop(e){
    e && e.stopPropagation();
    if(!TIMER||!TIMER.sessionId)return;
    if(confirm('Encerrar e finalizar a sessão atual?')){
        const fp=document.getElementById('finish-panel');
        if(fp){ fp.style.display='block'; }
        else{
            // Não está na página do timer — finaliza direto com qualidade 3
            TIMER.finish(3,'').then(res=>{
                if(res) toast('Sessão finalizada! '+res.minutes+'min 🎯','success');
            });
        }
    }
}

function updateFloatingTimer(state, remaining, totalWork, subjectName, phase){
    const inner=document.getElementById('ft-inner');
    if(!inner)return;

    const active=state==='running'||state==='paused'||state==='break';
    inner.style.display = active ? 'block' : 'none';

    if(!active)return;

    const m=String(Math.floor(remaining/60)).padStart(2,'0');
    const s=String(remaining%60).padStart(2,'0');
    document.getElementById('ft-time').textContent=m+':'+s;
    document.getElementById('ft-subj').textContent=subjectName||'';

    const phaseLabels={running:'Estudando',paused:'⏸ Pausado',break:'☕ Descanse'};
    document.getElementById('ft-phase').textContent=phaseLabels[state]||'Timer';

    // Cor borda conforme fase
    const inner2=document.getElementById('ft-inner');
    inner2.style.borderColor=state==='break'?'var(--green)':state==='paused'?'var(--accent)':'var(--primary)';

    // Barra de progresso
    const pct=totalWork>0?Math.round(((totalWork-remaining)/totalWork)*100):0;
    const bar=document.getElementById('ft-progress');
    if(bar){
        bar.style.width=Math.min(100,pct)+'%';
        bar.style.background=state==='break'?'var(--green)':'var(--primary)';
    }

    // Botão play/pause
    const btn=document.getElementById('ft-btn-pp');
    if(btn) btn.textContent=state==='running'?'⏸':'▶';

    // Título do browser
    document.title=active?(m+':'+s+' — Estudos GiGi'):'Estudos GiGi';
}

// ============================================================
//  POMODORO TIMER  v9
// ============================================================
class PomodoroTimer {
    constructor(cfg){
        this.workSec    = Math.max(1,(cfg.work_minutes||25))*60;
        this.shortBreak = Math.max(0,(cfg.short_break_min||5))*60;
        this.longBreak  = Math.max(0,(cfg.long_break_min||15))*60;
        this.maxCycles  = (cfg.cycles_before_long||4);
        this.autoBreak  = !!cfg.auto_start_break;
        this.autoWork   = !!cfg.auto_start_work;
        this.soundOn    = cfg.sound_enabled!==0&&cfg.sound_enabled!==false;
        this.isSimple   = !!cfg.is_simple;

        this.state      = 'idle';
        this.remaining  = this.workSec;
        this._totalWork = this.workSec;
        this.cycle      = 0;
        this.totalPomos = 0;
        this.sessionId  = null;
        this._iv        = null;
        this._startedAt = null;
        this._subjectName='';
        this._heartbeatIv=null;

        // Restaura estado salvo (se o usuário voltou de outra página)
        this._restoreState();
        this._render();
        this._updateButtons();
        createFloatingTimer();
    }

    _restoreState(){
        const s=loadTimerState();
        if(!s||!s.sessionId)return;
        this.state     =s.state||'idle';
        this.remaining =s.remaining||this.workSec;
        this._totalWork=s.totalWork||this.workSec;
        this.cycle     =s.cycle||0;
        this.totalPomos=s.totalPomos||0;
        this.sessionId =s.sessionId||null;
        this._startedAt=s.startedAt||null;
        this._subjectName=s.subjectName||'';
        this.soundOn   =s.soundOn!==undefined?s.soundOn:this.soundOn;

        if(this.state==='running'){
            this._iv=setInterval(()=>this._tick(),1000);
            this._startHeartbeat();
        } else if(this.state==='break'&&this.autoBreak){
            this._iv=setInterval(()=>this._tick(),1000);
        }

        // Preenche o painel lateral se existir
        const infoEl=document.getElementById('active-info');
        const subjEl=document.getElementById('active-subject');
        if(infoEl&&this.sessionId) infoEl.style.display='block';
        if(subjEl&&this._subjectName) subjEl.textContent=this._subjectName;
    }

    _saveState(){
        if(!this.sessionId){ saveTimerState(null); return; }
        saveTimerState({
            state:      this.state,
            remaining:  this.remaining,
            totalWork:  this._totalWork,
            cycle:      this.cycle,
            totalPomos: this.totalPomos,
            sessionId:  this.sessionId,
            startedAt:  this._startedAt,
            subjectName:this._subjectName,
            soundOn:    this.soundOn,
        });
        updateFloatingTimer(this.state, this.remaining, this._totalWork, this._subjectName);
    }

    _startHeartbeat(){
        clearInterval(this._heartbeatIv);
        // Heartbeat a cada 30s: atualiza ended_at no servidor
        this._heartbeatIv=setInterval(()=>{
            if(!this.sessionId||(this.state!=='running'&&this.state!=='break'))return;
            const elapsed=this._startedAt?Math.floor((Date.now()-this._startedAt)/1000):0;
            api('session_heartbeat',{session_id:this.sessionId,elapsed_seconds:elapsed},'POST')
                .catch(()=>{});
        },30000);
    }

    async start(){
        if(this.state==='break'){
            clearInterval(this._iv);
            this.remaining=this.workSec;
            this._totalWork=this.workSec;
            this.state='running';
            this._iv=setInterval(()=>this._tick(),1000);
            this._render(); this._updateButtons(); this._saveState();
            return;
        }

        if(!this.sessionId){
            const subEl=document.getElementById('select-subject');
            const topEl=document.getElementById('select-topic');
            const subId=subEl?subEl.value:'';
            const topId=topEl?topEl.value:'';
            if(!subId){toast('Selecione uma matéria primeiro!','error');return;}
            try{
                const res=await api('session_start',{subject_id:subId,topic_id:topId,session_type:'study'},'POST');
                if(!res.session_id){toast('Erro ao iniciar: '+(res.error||'tente novamente'),'error');return;}
                this.sessionId=res.session_id;
                this._startedAt=Date.now();
                this._subjectName=subEl?.options[subEl.selectedIndex]?.text||'';
                Sound._getCtx();
                const infoEl=document.getElementById('active-info');
                const subjEl=document.getElementById('active-subject');
                const topEl2=document.getElementById('active-topic');
                if(subjEl) subjEl.textContent=this._subjectName;
                if(topEl2&&topEl&&topEl.value) topEl2.textContent=topEl.options[topEl.selectedIndex]?.text||'';
                if(infoEl) infoEl.style.display='block';
            }catch(err){toast('Erro de conexão.','error');return;}
        }

        this.state='running';
        clearInterval(this._iv);
        this._iv=setInterval(()=>this._tick(),1000);
        this._startHeartbeat();
        this._render(); this._updateButtons(); this._saveState();
    }

    pause(){
        clearInterval(this._iv);
        this.state='paused';
        this._render(); this._updateButtons(); this._saveState();
    }

    reset(){
        clearInterval(this._iv);
        clearInterval(this._heartbeatIv);
        this.state='idle'; this.remaining=this.workSec; this._totalWork=this.workSec;
        this.cycle=0; this.totalPomos=0; this.sessionId=null; this._startedAt=null;
        this._subjectName='';
        saveTimerState(null);
        this._render(); this._updateButtons(); this._renderDots();
        document.title='Estudos GiGi';
        updateFloatingTimer('idle',0,0,'');
        ['active-info','finish-panel'].forEach(id=>{const el=document.getElementById(id);if(el)el.style.display='none';});
        const bar=document.getElementById('session-progress');
        if(bar){bar.style.width='0%';}
    }

    _tick(){
        this.remaining--;
        this._render();
        this._saveState(); // salva estado a cada segundo (para o floating timer)
        if(this.remaining<=0) this._onPhaseEnd();
    }

    _onPhaseEnd(){
        clearInterval(this._iv);

        if(this.state==='running'){
            this.totalPomos++;
            this.cycle++;
            this._renderDots();
            if(this.soundOn) Sound.workEnd();

            if(this.isSimple||this.shortBreak===0){
                toast('⏰ Tempo concluído! Sessão pronta para finalizar.','success',6000);
                this.state='idle';
                this._render(); this._updateButtons(); this._saveState();
                const fp=document.getElementById('finish-panel');
                if(fp) fp.style.display='block';
                return;
            }

            const isLong=this.cycle>=this.maxCycles;
            this.remaining=isLong?this.longBreak:this.shortBreak;
            this._totalWork=this.remaining;
            this.state='break';
            if(isLong){this.cycle=0; toast('🎉 Pausa longa! '+this.totalPomos+' pomodoros.','success',5000);}
            else       {toast('✅ Pomodoro '+this.totalPomos+' feito! Descanse.','success',4000);}
            this._render(); this._saveState();

            if(this.autoBreak){ this._iv=setInterval(()=>this._tick(),1000); }
            else               { this._updateButtons(); }

        } else if(this.state==='break'){
            this.remaining=this.workSec;
            this._totalWork=this.workSec;
            this.state='idle';
            if(this.soundOn) Sound.breakEnd();
            toast('⏰ Pausa encerrada! Hora de estudar.','success',4000);
            this._render(); this._saveState();
            if(this.autoWork){ this.start(); }
            else             { this._updateButtons(); }
        }
    }

    _render(){
        const m=String(Math.floor(this.remaining/60)).padStart(2,'0');
        const s=String(this.remaining%60).padStart(2,'0');
        const d=document.getElementById('timer-display');
        const l=document.getElementById('timer-label');
        if(d){
            d.textContent=m+':'+s;
            d.style.color=this.state==='break'?'var(--green)':this.state==='idle'?'var(--muted)':'var(--text)';
        }
        if(l) l.textContent=({running:'Estudando',paused:'Pausado',break:'☕ Descanse agora',idle:'Pronto'})[this.state]||'Pronto';
        document.title=(this.state==='running'||this.state==='break')?(m+':'+s+' — Estudos GiGi'):'Estudos GiGi';

        const total=this._totalWork||this.workSec;
        const pct=total>0?Math.round(((total-this.remaining)/total)*100):0;
        const bar=document.getElementById('session-progress');
        if(bar){
            bar.style.width=Math.min(100,pct)+'%';
            bar.style.background=this.state==='break'?'var(--green)':'var(--primary)';
        }

        // Atualiza o timer flutuante em todas as páginas
        updateFloatingTimer(this.state, this.remaining, this._totalWork, this._subjectName);
    }

    _updateButtons(){
        const btnStart=document.getElementById('timer-start');
        const btnPause=document.getElementById('timer-pause');
        if(!btnStart||!btnPause)return;
        const showPause=this.state==='running';
        btnPause.style.display=showPause?'inline-flex':'none';
        btnStart.style.display=showPause?'none':'inline-flex';
        if(this.state==='paused')     btnStart.textContent='▶ Retomar';
        else if(this.state==='break') btnStart.textContent='⏭ Iniciar pausa / Pular';
        else                          btnStart.textContent='▶ Iniciar';
    }

    _renderDots(){
        const dots=document.getElementById('timer-dots'); if(!dots)return;
        dots.querySelectorAll('.timer-dot').forEach((d,i)=>{
            d.classList.toggle('done',i<(this.totalPomos%Math.max(1,this.maxCycles)));
        });
    }

    async finish(quality,notes=''){
        if(!this.sessionId){toast('Nenhuma sessão ativa.','error');return null;}
        clearInterval(this._heartbeatIv);
        const elapsedSec=this._startedAt?Math.floor((Date.now()-this._startedAt)/1000):0;
        try{
            const res=await api('session_finish',{
                session_id:this.sessionId,quality,notes,
                pomodoros:this.totalPomos,
                elapsed_seconds:elapsedSec,
            },'POST');
            showXP(res.xp_earned,res.xp_result?.leveled_up);
            if(this.soundOn) Sound.confirm();
            (res.new_achievements||[]).forEach(a=>setTimeout(()=>toast('🏆 '+a.title+'!','xp',5000),600));
            this.reset();
            return res;
        }catch(e){toast('Erro ao finalizar.','error');return null;}
    }
}

let TIMER=null;

// ── Floating timer para páginas sem o timer principal ────────
// Lê o estado salvo e exibe o widget sem criar uma instância completa
(function initFloatingFromStorage(){
    createFloatingTimer();
    const s=loadTimerState();
    if(!s||!s.sessionId)return;

    const active=s.state==='running'||s.state==='paused'||s.state==='break';
    if(!active)return;

    // Calcula remaining real (quanto tempo passou desde o último save)
    let remaining=s.remaining||0;
    if(s.state==='running'){
        const elapsed=Math.floor((Date.now()-s._saved)/1000);
        remaining=Math.max(0,remaining-elapsed);
    }

    updateFloatingTimer(s.state,remaining,s.totalWork||remaining,s.subjectName||'');

    // Só se não estiver na página do timer, cria um mini-tick para o widget
    const isTimerPage = document.getElementById('timer-start') !== null;
    if(isTimerPage) return; // o TIMER real vai cuidar disso

    let rem=remaining;
    let tot=s.totalWork||rem;
    let state=s.state;
    const soundOn=s.soundOn!==false;

    // Mini tick apenas para o floating display
    const miniTick=setInterval(()=>{
        const cur=loadTimerState();
        if(!cur||!cur.sessionId){ clearInterval(miniTick); updateFloatingTimer('idle',0,0,''); return; }
        // Recalcula
        let r=cur.remaining||0;
        if(cur.state==='running'){
            const el=Math.floor((Date.now()-cur._saved)/1000);
            r=Math.max(0,r-el);
        }
        updateFloatingTimer(cur.state,r,cur.totalWork||r,cur.subjectName||'');
        if(r<=0 && cur.state==='running'){
            if(soundOn) Sound.workEnd();
        }
    },1000);
})();

// ── Abandona sessão ao sair da página se timer ativo ─────────
window.addEventListener('beforeunload',()=>{
    const s=loadTimerState();
    if(!s||!s.sessionId)return;
    const active=s.state==='running'||s.state==='paused'||s.state==='break';
    if(!active)return;
    const elapsed=s.startedAt?Math.floor((Date.now()-s.startedAt)/1000):60;
    // Usa sendBeacon para garantir envio mesmo durante navegação
    const fd=new FormData();
    fd.append('session_id',s.sessionId);
    fd.append('elapsed_seconds',elapsed);
    navigator.sendBeacon('index.php?page=api&action=session_heartbeat',
        new URLSearchParams({session_id:s.sessionId,elapsed_seconds:elapsed}));
});

// ── Charts ────────────────────────────────────────────────────
function makeBarChart(id,labels,data,label='Horas'){
    const ctx=document.getElementById(id)?.getContext('2d'); if(!ctx)return null;
    return new Chart(ctx,{type:'bar',
        data:{labels,datasets:[{label,data,backgroundColor:'rgba(99,102,241,.7)',borderRadius:5,borderSkipped:false}]},
        options:{responsive:true,plugins:{legend:{display:false}},
            scales:{x:{grid:{color:'rgba(128,128,128,.1)'},ticks:{color:'#94a3b8',maxTicksLimit:10}},
                    y:{grid:{color:'rgba(128,128,128,.1)'},ticks:{color:'#94a3b8'},beginAtZero:true}}}});
}

function makePieChart(id,labels,data,colors){
    const ctx=document.getElementById(id)?.getContext('2d'); if(!ctx)return null;
    return new Chart(ctx,{type:'doughnut',
        data:{labels,datasets:[{data,backgroundColor:colors,borderWidth:0,hoverOffset:6}]},
        options:{responsive:true,cutout:'65%',plugins:{legend:{position:'right',labels:{color:'#94a3b8',font:{size:12}}}}}});
}
