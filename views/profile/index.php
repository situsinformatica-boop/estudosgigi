<?php
/**
 * views/profile/index.php — SOMENTE RENDERIZAÇÃO
 * POSTs são tratados em public/index.php
 */
$user = currentUser();

// Flash message da sessão (vem após redirect do POST)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$gamif   = new GamificationModel();
$uStats  = $gamif->getStats();
$xpInfo  = $gamif->xpToNextLevel();
$summary = (new ReportModel())->getSummary(9999);

$curTheme = $user['theme'] ?? 'dark';
$colors   = ['#6366f1','#22c55e','#f59e0b','#ef4444','#06b6d4','#ec4899','#8b5cf6','#14b8a6','#f97316','#64748b'];

$themes = [
    'dark'     => ['name' => 'Dark',       'bg' => '#0f1117', 'dot' => '#6366f1'],
    'light'    => ['name' => 'Light',      'bg' => '#f4f5fb', 'dot' => '#6366f1'],
    'midnight' => ['name' => 'Midnight',   'bg' => '#0d0d1a', 'dot' => '#a78bfa'],
    'forest'   => ['name' => 'Forest',     'bg' => '#0a1a0f', 'dot' => '#4ade80'],
    'ocean'    => ['name' => 'Ocean',      'bg' => '#051525', 'dot' => '#38bdf8'],
    'sakura'   => ['name' => 'Sakura 🌸',  'bg' => '#1a0d14', 'dot' => '#f472b6'],
    'candy'    => ['name' => 'Candy 🍬',   'bg' => '#fff0f6', 'dot' => '#ec4899'],
    'blush'    => ['name' => 'Blush 💗',   'bg' => '#180f14', 'dot' => '#fbcfe8'],
    'rosegold' => ['name' => 'Rose Gold',  'bg' => '#1c1210', 'dot' => '#e6af91'],
    'hotpink'  => ['name' => 'Hot Pink',   'bg' => '#120510', 'dot' => '#ff1493'],
];

$FORM = BASE_URL . '/index.php?page=profile';
?>

<div class="page-header">
    <div>
        <div class="page-title">👤 Meu Perfil</div>
        <div class="page-subtitle">Gerencie sua conta e preferências visuais</div>
    </div>
</div>

<?php if ($flash): ?>
<div style="
    background:<?= $flash[0]==='ok' ? 'rgba(34,197,94,.12)' : 'rgba(239,68,68,.12)' ?>;
    border:1px solid <?= $flash[0]==='ok' ? 'rgba(34,197,94,.4)' : 'rgba(239,68,68,.4)' ?>;
    border-radius:var(--radius);padding:12px 16px;margin-bottom:18px;font-size:14px;
    color:<?= $flash[0]==='ok' ? '#86efac' : '#fca5a5' ?>">
    <?= $flash[0]==='ok' ? '✓' : '⚠' ?> <?= e($flash[1]) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 290px;gap:20px;align-items:start">

<!-- ── Coluna principal ───────────────────────────────────── -->
<div style="display:flex;flex-direction:column;gap:18px">

    <!-- Dados pessoais + tema + cor -->
    <div class="card">
        <div class="card-title">✏️ Informações pessoais</div>
        <form method="POST" action="<?= $FORM ?>">
            <input type="hidden" name="_action" value="update_profile">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-input"
                           value="<?= e($user['name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-input"
                           value="<?= e($user['email'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Cor do avatar -->
            <div class="form-group">
                <label class="form-label">Cor do avatar</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap" id="color-picker">
                    <?php foreach ($colors as $c): ?>
                    <button type="button"
                        onclick="pickColor('<?= $c ?>')"
                        data-c="<?= $c ?>"
                        style="width:28px;height:28px;border-radius:50%;background:<?= $c ?>;
                               border:3px solid <?= $c===($user['avatar_color']??'#6366f1')?'#fff':'transparent' ?>;
                               cursor:pointer;transition:border-color .15s;flex-shrink:0">
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="avatar_color" id="avatar-color-val"
                       value="<?= e($user['avatar_color'] ?? '#6366f1') ?>">
            </div>

            <!-- Seletor de tema -->
            <div class="form-group">
                <label class="form-label">Tema da interface</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
                    <?php foreach ($themes as $key => $t): ?>
                    <button type="button"
                        data-theme="<?= $key ?>"
                        onclick="pickTheme('<?= $key ?>')"
                        title="<?= $t['name'] ?>"
                        style="width:36px;height:36px;border-radius:50%;cursor:pointer;transition:all .2s;
                               background:<?= $t['bg'] ?>;
                               border:3px solid <?= $curTheme===$key?'#fff':'transparent' ?>;
                               box-shadow:inset 0 0 0 8px <?= $t['dot'] ?>44">
                    </button>
                    <?php endforeach; ?>
                </div>
                <div style="font-size:12px;color:var(--muted)" id="theme-label">
                    Tema atual: <strong><?= $themes[$curTheme]['name'] ?? $curTheme ?></strong>
                </div>
                <input type="hidden" name="theme" id="theme-val" value="<?= e($curTheme) ?>">
            </div>

            <button type="submit" class="btn btn-primary">Salvar alterações</button>
        </form>
    </div>

    <!-- Alterar senha -->
    <div class="card">
        <div class="card-title">🔒 Alterar senha</div>
        <form method="POST" action="<?= $FORM ?>">
            <input type="hidden" name="_action" value="change_password">
            <div class="form-group">
                <label class="form-label">Senha atual</label>
                <input type="password" name="current_password" class="form-input"
                       placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nova senha</label>
                <input type="password" name="new_password" class="form-input"
                       placeholder="Mínimo 6 caracteres" required>
            </div>
            <button type="submit" class="btn btn-ghost">Alterar senha</button>
        </form>
    </div>

</div><!-- fim coluna principal -->

<!-- ── Coluna lateral: estatísticas ──────────────────────── -->
<div style="display:flex;flex-direction:column;gap:16px">

    <!-- Avatar visual -->
    <div class="card" style="text-align:center;padding:24px">
        <div id="avatar-preview"
             style="width:72px;height:72px;border-radius:50%;margin:0 auto 10px;
                    display:flex;align-items:center;justify-content:center;
                    font-size:28px;font-weight:700;color:#fff;
                    background:<?= e($user['avatar_color'] ?? '#6366f1') ?>;
                    transition:background .2s">
            <?= mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div style="font-size:14px;font-weight:600"><?= e($user['name'] ?? '') ?></div>
        <div style="font-size:12px;color:var(--muted)"><?= e($user['email'] ?? '') ?></div>
    </div>

    <!-- XP e nível -->
    <div class="card">
        <div class="card-title">⚡ Progresso</div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);
                        display:flex;align-items:center;justify-content:center;
                        font-size:18px;font-weight:700;color:#fff;flex-shrink:0">
                <?= $uStats['level'] ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:12px;color:var(--muted);margin-bottom:3px">
                    Nível <?= $uStats['level'] ?> · <?= number_format($uStats['total_xp']) ?> XP
                </div>
                <div class="xp-bar-wrap" style="height:7px;margin-bottom:3px">
                    <div class="xp-bar" style="width:<?= $xpInfo['percent'] ?>%"></div>
                </div>
                <div style="font-size:11px;color:var(--muted);font-family:var(--mono)">
                    <?= number_format($xpInfo['current']) ?> / <?= number_format($xpInfo['needed']) ?> XP
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="card">
        <div class="card-title">📊 Estatísticas</div>
        <?php
        $stats = [
            ['📚', 'Sessões',         $uStats['total_sessions'] . ' sessões'],
            ['⏱',  'Horas estudadas', round($uStats['total_minutes'] / 60, 1) . 'h'],
            ['🔥', 'Streak atual',    $uStats['current_streak'] . ' dias'],
            ['🏆', 'Recorde',         $uStats['longest_streak'] . ' dias'],
            ['⭐', 'Qualidade média', number_format((float)($summary['avg_quality'] ?? 0), 1) . ' / 5.0'],
            ['🍅', 'Pomodoros',       ($summary['total_pomodoros'] ?? 0) . ' feitos'],
            ['📅', 'Membro desde',    formatDate($user['created_at'] ?? '', 'd/m/Y')],
        ];
        foreach ($stats as [$ic, $label, $val]):
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;
                    padding:7px 0;border-bottom:1px solid var(--border)">
            <span style="font-size:13px;color:var(--muted)"><?= $ic ?> <?= $label ?></span>
            <span style="font-size:13px;font-weight:600;font-family:var(--mono)"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
    </div>

</div><!-- fim coluna lateral -->

</div><!-- fim grid -->

<script>
const themeNames = <?= json_encode(array_map(fn($t) => $t['name'], $themes)) ?>;

function pickColor(c) {
    document.getElementById('avatar-color-val').value = c;
    document.getElementById('avatar-preview').style.background = c;
    document.querySelectorAll('#color-picker button').forEach(b => {
        b.style.borderColor = b.dataset.c === c ? '#fff' : 'transparent';
    });
}

function pickTheme(key) {
    // Aplica imediatamente no browser
    document.documentElement.setAttribute('data-theme', key);
    localStorage.setItem('gigi_theme', key);
    // Atualiza o input e label
    document.getElementById('theme-val').value = key;
    const name = themeNames[key] || key;
    document.getElementById('theme-label').innerHTML = 'Tema atual: <strong>' + name + '</strong>';
    // Marca o botão selecionado
    document.querySelectorAll('[data-theme]').forEach(b => {
        b.style.borderColor = b.dataset.theme === key ? '#fff' : 'transparent';
    });
}
</script>
