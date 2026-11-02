<?php
$report  = new ReportModel();
$summary = $report->getSummary(30);
$top     = $report->topSubjectsByHour();
$weekGoals = $report->weeklyGoalsProgress();
?>
<div class="page-header">
    <div><div class="page-title">📊 Relatórios</div><div class="page-subtitle">Análise dos seus estudos</div></div>
    <div style="display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="loadData(7)"  id="btn-7">7 dias</button>
        <button class="btn btn-primary btn-sm" onclick="loadData(30)" id="btn-30">30 dias</button>
        <button class="btn btn-ghost btn-sm" onclick="loadData(90)" id="btn-90">90 dias</button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Total de horas</div><div class="stat-value stat-blue"><?= round($summary['total_minutes']/60,1) ?>h</div><div class="stat-sub"><?= $summary['total_sessions'] ?> sessões</div></div>
    <div class="stat-card"><div class="stat-label">Média por sessão</div><div class="stat-value stat-green"><?= minutesToReadable((int)$summary['avg_duration']) ?></div><div class="stat-sub">por sessão</div></div>
    <div class="stat-card"><div class="stat-label">Qualidade média</div><div class="stat-value stat-amber"><?= number_format($summary['avg_quality'],1) ?> ★</div><div class="stat-sub">de 5.0</div></div>
    <div class="stat-card"><div class="stat-label">Dias estudados</div><div class="stat-value stat-blue"><?= $summary['days_studied'] ?></div><div class="stat-sub"><?= $summary['total_pomodoros'] ?> pomodoros</div></div>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <div class="card"><div class="card-title">Horas por dia</div><canvas id="chart-hours" height="180"></canvas></div>
    <div class="card"><div class="card-title">Distribuição por matéria</div><canvas id="chart-pie" height="180"></canvas></div>
</div>

<div class="grid-2" style="margin-bottom:20px">
    <div class="card"><div class="card-title">Evolução da qualidade</div><canvas id="chart-quality" height="180"></canvas></div>
    <div class="card">
        <div class="card-title">Progresso metas semanais</div>
        <?php foreach($weekGoals as $g): ?>
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <span style="font-weight:600"><?= e($g['name']) ?></span>
                <span style="color:var(--muted);font-family:var(--mono)"><?= $g['percent'] ?>%</span>
            </div>
            <div class="progress-wrap"><div class="progress-bar" style="width:<?= $g['percent'] ?>%;background:<?= e($g['color']) ?>"></div></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if(!empty($top)): ?>
<div class="card" style="margin-bottom:20px">
    <div class="card-title">Top matérias por horas</div>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <thead><tr style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em">
            <th style="text-align:left;padding:8px 0;font-weight:600">Matéria</th>
            <th style="text-align:right;padding:8px;font-weight:600">Horas</th>
            <th style="text-align:right;padding:8px;font-weight:600">Qualidade</th>
        </tr></thead>
        <tbody>
        <?php foreach($top as $t): ?>
        <tr style="border-top:1px solid var(--border)">
            <td style="padding:10px 0;font-weight:600"><span class="subject-dot" style="background:<?= e($t['color']) ?>"></span><?= e($t['name']) ?></td>
            <td style="text-align:right;padding:10px 8px;font-family:var(--mono);color:var(--primary-lt)"><?= $t['hours'] ?>h</td>
            <td style="text-align:right;padding:10px 8px;color:var(--accent)"><?= $t['avg_quality'] ?> ★</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Heatmap — horários de estudo (90 dias)</div>
    <div id="heatmap"></div>
</div>

<script>
let cH,cP,cQ;
const dayNames=['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

async function loadData(days=30){
    ['7','30','90'].forEach(d=>document.getElementById('btn-'+d).className='btn btn-sm '+(d==days?'btn-primary':'btn-ghost'));
    const [h,p,q]=await Promise.all([
        fetch(`index.php?page=api&action=report_data&type=hours_per_day&days=${days}`).then(r=>r.json()),
        fetch(`index.php?page=api&action=report_data&type=distribution&days=${days}`).then(r=>r.json()),
        fetch(`index.php?page=api&action=report_data&type=quality&days=${days}`).then(r=>r.json()),
    ]);
    if(cH)cH.destroy();
    cH=makeBarChart('chart-hours',h.map(d=>{const[,m,dia]=d.date.split('-');return`${dia}/${m}`;}),h.map(d=>d.hours));
    if(cP)cP.destroy();
    if(p.length)cP=makePieChart('chart-pie',p.map(d=>d.name),p.map(d=>Math.round(d.minutes/60*10)/10),p.map(d=>d.color));
    if(cQ)cQ.destroy();
    const qCtx=document.getElementById('chart-quality')?.getContext('2d');
    if(qCtx&&q.length){cQ=new Chart(qCtx,{type:'line',data:{labels:q.map(d=>{const[,m,dia]=d.date.split('-');return`${dia}/${m}`;}),datasets:[{label:'Qualidade',data:q.map(d=>d.avg_quality),borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.1)',tension:.4,fill:true,pointRadius:3}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#94a3b8',maxTicksLimit:10}},y:{min:0,max:5,grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#94a3b8'}}}}});}
}

async function loadHeatmap(){
    const data=await fetch('index.php?page=api&action=report_data&type=heatmap&days=90').then(r=>r.json());
    const grid={};let maxV=1;
    data.forEach(d=>{grid[`${d.day_of_week}-${d.hour_of_day}`]=d.total_minutes;if(d.total_minutes>maxV)maxV=d.total_minutes;});
    let html='<div style="display:flex;gap:3px;font-size:11px;color:var(--muted);margin-bottom:4px"><div style="width:28px"></div>';
    for(let h=6;h<=23;h++)html+=`<div style="width:18px;text-align:center">${h}</div>`;
    html+='</div>';
    for(let d=0;d<7;d++){
        html+=`<div style="display:flex;gap:3px;align-items:center;margin-bottom:3px"><div style="width:28px;font-size:11px;color:var(--muted)">${dayNames[d]}</div>`;
        for(let h=6;h<=23;h++){const v=grid[`${d}-${h}`]||0;const a=v?(0.15+(v/maxV)*0.85).toFixed(2):0.05;html+=`<div title="${dayNames[d]} ${h}h: ${v}min" style="width:18px;height:18px;border-radius:3px;background:rgba(99,102,241,${a})"></div>`;}
        html+='</div>';
    }
    document.getElementById('heatmap').innerHTML=html;
}

loadData(30);loadHeatmap();
</script>
