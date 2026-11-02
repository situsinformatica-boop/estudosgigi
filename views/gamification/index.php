<?php
$gamif   = new GamificationModel();
$uStats  = $gamif->getStats();
$xpInfo  = $gamif->xpToNextLevel();
$all     = $gamif->getAllWithStatus();
$unlocked = array_filter($all, fn($a) => $a['is_unlocked']);
$xpLog   = db()->prepare("SELECT * FROM xp_log WHERE user_id=? ORDER BY earned_at DESC LIMIT 20");
$xpLog->execute([uid()]); $xpLog = $xpLog->fetchAll();

$icons = ['play'=>'▶','flame'=>'🔥','fire'=>'🔥','bolt'=>'⚡','clock'=>'⏱','star'=>'⭐',
          'medal'=>'🏅','check'=>'✅','award'=>'🏆','tomato'=>'🍅','target'=>'🎯',
          'moon'=>'🌙','sun'=>'☀️','refresh'=>'🔄','trophy'=>'🏆'];
?>
<div class="page-header">
    <div><div class="page-title">🏆 Conquistas & XP</div><div class="page-subtitle"><?= count($unlocked) ?> de <?= count($all) ?> desbloqueadas</div></div>
</div>

<div class="card" style="margin-bottom:24px;max-width:580px">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;flex-shrink:0"><?= $uStats['level'] ?></div>
        <div style="flex:1">
            <div style="font-size:13px;color:var(--muted);margin-bottom:4px">Nível <?= $uStats['level'] ?> · <?= number_format($uStats['total_xp']) ?> XP total</div>
            <div class="xp-bar-wrap" style="height:10px;margin-bottom:4px"><div class="xp-bar" style="width:<?= $xpInfo['percent'] ?>%"></div></div>
            <div style="font-size:11px;color:var(--muted);font-family:var(--mono)"><?= number_format($xpInfo['current']) ?> / <?= number_format($xpInfo['needed']) ?> XP para nível <?= $uStats['level']+1 ?></div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;border-top:1px solid var(--border);padding-top:14px">
        <?php foreach([['🔥',$uStats['current_streak'],'Streak atual'],['🏆',$uStats['longest_streak'],'Recorde'],['✅',$uStats['total_sessions'],'Sessões'],[' ',round($uStats['total_minutes']/60,1).'h','Horas']] as [$ic,$v,$l]): ?>
        <div style="text-align:center"><div style="font-size:20px;font-weight:700;color:var(--accent)"><?= $v ?></div><div style="font-size:11px;color:var(--muted)"><?= $l ?></div></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card-title" style="margin-bottom:12px">Todas as conquistas</div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach($all as $a): ?>
            <div class="achievement-card <?= !$a['is_unlocked']?'locked':'' ?>">
                <div class="achievement-icon"><?= $icons[$a['icon']] ?? '🏆' ?></div>
                <div class="achievement-info">
                    <div class="name"><?= e($a['title']) ?></div>
                    <div class="desc"><?= e($a['description']) ?></div>
                    <?php if($a['is_unlocked']): ?><div style="font-size:11px;color:var(--green);margin-top:2px">✓ <?= formatDate($a['unlocked_at'],'d/m/Y') ?></div><?php endif; ?>
                </div>
                <div class="achievement-xp">+<?= $a['xp_reward'] ?> XP</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div>
        <div class="card-title" style="margin-bottom:12px">Últimos ganhos de XP</div>
        <div class="card">
            <?php if(empty($xpLog)): ?><p style="color:var(--muted);font-size:14px">Nenhum XP ainda. Complete uma sessão!</p><?php endif; ?>
            <?php foreach($xpLog as $log): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--border)">
                <div><div style="font-size:13px;font-weight:600"><?= e(str_replace('_',' ',$log['reason'])) ?></div><div style="font-size:11px;color:var(--muted)"><?= formatDate($log['earned_at'],'d/m H:i') ?></div></div>
                <div style="color:var(--accent);font-family:var(--mono);font-weight:700">+<?= $log['amount'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
