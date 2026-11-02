<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach(['daily_goal_minutes','timezone','xp_per_minute','xp_per_session','xp_per_pomodoro','xp_per_review','level_xp_base'] as $k) {
        if (isset($_POST[$k])) db()->prepare("INSERT INTO settings(key_name,value)VALUES(?,?)ON DUPLICATE KEY UPDATE value=?")->execute([$k,$_POST[$k],$_POST[$k]]);
    }
    clearSettingsCache(); header('Location: '.BASE_URL.'/index.php?page=settings&saved=1'); exit;
}
?>
<div class="page-header"><div><div class="page-title">⚙ Configurações</div><div class="page-subtitle">Personalize o sistema</div></div></div>
<?php if(isset($_GET['saved'])): ?><div style="background:rgba(34,197,94,.15);border:1px solid var(--green);border-radius:var(--radius);padding:12px 16px;margin-bottom:20px;color:var(--green);font-size:14px">✓ Configurações salvas!</div><?php endif; ?>
<form method="POST" action="" style="max-width:580px">
    <div class="card" style="margin-bottom:20px">
        <div class="card-title">Metas de estudo</div>
        <div class="form-group"><label class="form-label">Meta diária (minutos)</label><input type="number" name="daily_goal_minutes" class="form-input" value="<?= getSetting('daily_goal_minutes','120') ?>" min="15" step="15"></div>
        <div class="form-group"><label class="form-label">Fuso horário</label>
            <select name="timezone" class="form-select"><?php foreach(['America/Sao_Paulo','America/Manaus','America/Belem','America/Fortaleza','America/Recife'] as $t): ?><option value="<?=$t?>" <?= getSetting('timezone','America/Sao_Paulo')===$t?'selected':'' ?>><?=$t?></option><?php endforeach; ?></select></div>
    </div>
    <div class="card" style="margin-bottom:20px">
        <div class="card-title">Sistema de XP</div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">XP por minuto</label><input type="number" name="xp_per_minute" class="form-input" value="<?= getSetting('xp_per_minute','2') ?>" min="0"></div>
            <div class="form-group"><label class="form-label">XP por sessão</label><input type="number" name="xp_per_session" class="form-input" value="<?= getSetting('xp_per_session','30') ?>" min="0"></div>
            <div class="form-group"><label class="form-label">XP por pomodoro</label><input type="number" name="xp_per_pomodoro" class="form-input" value="<?= getSetting('xp_per_pomodoro','15') ?>" min="0"></div>
            <div class="form-group"><label class="form-label">XP por revisão</label><input type="number" name="xp_per_review" class="form-input" value="<?= getSetting('xp_per_review','20') ?>" min="0"></div>
        </div>
        <div class="form-group"><label class="form-label">XP base por nível</label><input type="number" name="level_xp_base" class="form-input" value="<?= getSetting('level_xp_base','500') ?>" min="100" step="100"></div>
    </div>
    <div class="card" style="margin-bottom:20px">
        <div class="card-title">Conexão com banco</div>
        <div style="background:var(--bg3);border-radius:var(--radius);padding:14px;font-family:var(--mono);font-size:13px;color:var(--muted)">
            Host: <?= DB_HOST ?>:<?= DB_PORT ?><br>Banco: <?= DB_NAME ?><br>Usuário: <?= DB_USER ?>
        </div>
        <p style="font-size:13px;color:var(--muted);margin-top:10px">Edite <code style="background:var(--bg3);padding:2px 6px;border-radius:4px">config/database.php</code> para alterar.</p>
    </div>
    <button type="submit" class="btn btn-primary">Salvar configurações</button>
</form>
