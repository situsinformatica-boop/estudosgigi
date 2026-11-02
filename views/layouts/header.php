<?php
$user   = currentUser();
$theme  = $user['theme'] ?? 'dark';
$gamif  = new GamificationModel();
$uStats = $gamif->getStats();
$xpInfo = $gamif->xpToNextLevel();
$initial     = mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= e($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Estudos GiGi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/css/app.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Aplica tema antes do render (evita flash)
(function(){
  var t='<?= e($theme) ?>';
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('gigi_theme',t);
})();
</script>
</head>
<body>
<a class="skip-link" href="#main-content">Pular para o conteúdo</a>

<!-- ── Botão hamburguer (mobile) ──────────────────────── -->
<button class="hamburger" id="hamburger" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
</button>

<!-- ── Overlay escuro ao abrir sidebar (mobile) ───────── -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ── Sidebar ────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <span>🎓</span><span>Estudos GiGi</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar" style="background:<?= e($user['avatar_color'] ?? '#6366f1') ?>">
            <?= $initial ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?= e($user['name'] ?? '') ?></div>
            <div class="user-email"><?= e($user['email'] ?? '') ?></div>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="Navegação principal">
        <?php
        $groups = [
            'Seu dia' => [['dashboard','Hoje'],['timer','Estudar'],['agenda','Agenda']],
            'Materiais' => [['subjects','Matérias'],['notebook','Caderno digital'],['flashcards','Flashcards']],
            'Praticar' => [['review','Simulados e quiz'],['focus','Modo foco']],
            'Evolução' => [['reports','Relatórios'],['study_hours','Horas de estudo'],['goals','Metas'],['gamification','Conquistas']],
        ];
        foreach ($groups as $group => $links): ?>
        <div class="nav-group-label"><?= $group ?></div>
        <?php foreach ($links as [$p, $label]): ?>
        <a href="<?= BASE_URL ?>/index.php?page=<?= $p ?>" class="nav-item <?= $page===$p?'active':'' ?>" <?= $page===$p?'aria-current="page"':'' ?> onclick="closeSidebar()">
            <span class="nav-marker" aria-hidden="true"></span><?= $label ?>
        </a>
        <?php endforeach; endforeach; ?>

        <div class="nav-sep"></div>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <a href="<?= BASE_URL ?>/index.php?page=admin"
           class="nav-item <?= $page==='admin'?'active':'' ?>"
           onclick="closeSidebar()">
            <span class="nav-icon">👥</span> Usuários
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?page=profile"
           class="nav-item <?= $page==='profile'?'active':'' ?>"
           onclick="closeSidebar()">
            <span class="nav-icon">👤</span> Meu Perfil
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=settings"
           class="nav-item <?= $page==='settings'?'active':'' ?>"
           onclick="closeSidebar()">
            <span class="nav-icon">⚙</span> Configurações
        </a>
        <a href="<?= BASE_URL ?>/index.php?page=logout"
           class="nav-item"
           onclick="return confirm('Sair do Estudos GiGi?')">
            <span class="nav-icon">↩</span> Sair
        </a>
    </nav>

    <div class="sidebar-xp">
        <div class="xp-level">Nível <?= $uStats['level'] ?></div>
        <div class="xp-bar-wrap">
            <div class="xp-bar" style="width:<?= $xpInfo['percent'] ?>%"></div>
        </div>
        <div class="xp-label">
            <?= number_format($xpInfo['current']) ?> / <?= number_format($xpInfo['needed']) ?> XP
        </div>
        <div class="xp-streak">🔥 <?= $uStats['current_streak'] ?> dias seguidos</div>
    </div>
</aside>

<!-- ── Bottom nav (mobile) ────────────────────────────── -->
<nav class="bottom-nav" aria-label="Navegação rápida">
    <?php foreach ([['dashboard','Hoje'],['timer','Estudar'],['agenda','Agenda']] as [$p,$label]): ?>
    <a href="<?= BASE_URL ?>/index.php?page=<?= $p ?>" class="<?= $page===$p?'active':'' ?>" <?= $page===$p?'aria-current="page"':'' ?>><?= $label ?></a>
    <?php endforeach; ?>
    <button type="button" aria-controls="sidebar" aria-expanded="false" onclick="toggleSidebar()">Mais</button>
</nav>

<!-- ── Main content ───────────────────────────────────── -->
<main class="main-content" id="main-content" tabindex="-1">
<?php if (isset($_GET['welcome'])): ?>
<div style="background:rgba(99,102,241,.15);border:1px solid var(--primary);border-radius:10px;padding:13px 16px;margin-bottom:18px;font-size:14px;color:var(--primary-lt)">
    🎉 Bem-vindo ao <strong>Estudos GiGi</strong>, <strong><?= e($user['name']) ?></strong>!
    <a href="<?= BASE_URL ?>/index.php?page=subjects" style="color:var(--primary-lt);text-decoration:underline">Adicione suas matérias →</a>
</div>
<?php endif; ?>

<script>
function toggleSidebar(){
    const s=document.getElementById('sidebar');
    const o=document.getElementById('sidebar-overlay');
    const h=document.getElementById('hamburger');
    const open=s.classList.toggle('open');
    h.classList.toggle('open',open);
    document.querySelectorAll('[aria-controls=sidebar]').forEach(b=>b.setAttribute('aria-expanded',String(open)));
    if(open) s.querySelector('a')?.focus();
    o.classList.toggle('visible',open);
    document.body.style.overflow=open?'hidden':'';
}
function closeSidebar(){
    const s=document.getElementById('sidebar');
    if(!s.classList.contains('open'))return;
    s.classList.remove('open');
    document.querySelectorAll('[aria-controls=sidebar]').forEach(b=>b.setAttribute('aria-expanded','false'));
    if(s.contains(document.activeElement)) document.getElementById('hamburger').focus();
    document.getElementById('hamburger').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('visible');
    document.body.style.overflow='';
}
// Fecha com Escape
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});
// Fecha ao redimensionar para desktop
window.addEventListener('resize',()=>{if(window.innerWidth>768)closeSidebar()});
</script>
