<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar — Estudos GiGi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Reset ── */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

/* ── Variáveis de cor ── */
:root {
    --bg:       #0b0d16;
    --card:     #13162a;
    --border:   rgba(255,255,255,.07);
    --primary:  #f472b6;   /* rosa principal */
    --primary2: #a78bfa;   /* roxo secundário */
    --accent:   #38bdf8;   /* azul destaque */
    --text:     #f0f0ff;
    --muted:    #8b8fad;
    --green:    #34d399;
    --red:      #f87171;
    --radius:   12px;
    --font:     'Space Grotesk', sans-serif;
}

/* ── Fundo com gradiente e bolas decorativas ── */
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow: hidden;
    position: relative;
}

/* Bolas de luz no fundo */
body::before {
    content: '';
    position: fixed;
    top: -20%;
    left: -10%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(244,114,182,.18) 0%, transparent 65%);
    pointer-events: none;
    z-index: 0;
    animation: float1 8s ease-in-out infinite;
}
body::after {
    content: '';
    position: fixed;
    bottom: -20%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(167,139,250,.15) 0%, transparent 65%);
    pointer-events: none;
    z-index: 0;
    animation: float2 10s ease-in-out infinite;
}
@keyframes float1 {
    0%,100% { transform: translate(0,0); }
    50%      { transform: translate(30px, 20px); }
}
@keyframes float2 {
    0%,100% { transform: translate(0,0); }
    50%      { transform: translate(-20px, -30px); }
}

/* ── Container central ── */
.wrap {
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    animation: slideUp .4s ease;
}
@keyframes slideUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}

/* ── Logo / marca ── */
.brand {
    text-align: center;
    margin-bottom: 32px;
}
.brand-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, var(--primary), var(--primary2));
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    margin-bottom: 14px;
    box-shadow: 0 8px 32px rgba(244,114,182,.35);
}
.brand-name {
    font-size: 28px;
    font-weight: 800;
    /* gradiente no texto */
    background: linear-gradient(90deg, var(--primary), var(--primary2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -.5px;
}
.brand-sub {
    font-size: 14px;
    color: var(--muted);
    margin-top: 4px;
}

/* ── Card do formulário ── */
.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 36px 32px;
    /* brilho sutil na borda superior */
    box-shadow:
        0 0 0 1px rgba(244,114,182,.08),
        0 24px 48px rgba(0,0,0,.4);
    position: relative;
    overflow: hidden;
}
/* Linha decorativa no topo do card */
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--primary), var(--primary2), var(--accent));
    border-radius: 20px 20px 0 0;
}

.card-title  { font-size:21px; font-weight:700; margin-bottom:4px; }
.card-sub    { font-size:14px; color:var(--muted); margin-bottom:26px; }

/* ── Mensagem de erro ── */
.error {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(248,113,113,.1);
    border: 1px solid rgba(248,113,113,.3);
    border-radius: var(--radius);
    padding: 11px 14px;
    font-size: 14px;
    color: #fca5a5;
    margin-bottom: 20px;
}

/* ── Campos do formulário ── */
.group { margin-bottom: 18px; }

.group label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 7px;
}

.input-wrap { position: relative; }

.input-wrap input,
input[type=email],
input[type=password],
input[type=text] {
    width: 100%;
    background: rgba(255,255,255,.04);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    color: var(--text);
    font-family: var(--font);
    font-size: 15px;
    padding: 12px 16px;
    transition: border-color .2s, background .2s, box-shadow .2s;
    outline: none;
    -webkit-appearance: none;
}
.input-wrap input:focus,
input[type=email]:focus,
input[type=password]:focus,
input[type=text]:focus {
    border-color: var(--primary);
    background: rgba(244,114,182,.05);
    box-shadow: 0 0 0 3px rgba(244,114,182,.12);
}

/* ícone dentro do input */
.input-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    pointer-events: none;
    opacity: .5;
}
.input-wrap.has-icon input { padding-left: 42px; }

/* botão olho */
.eye-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 17px;
    padding: 4px;
    transition: color .15s;
}
.eye-btn:hover { color: var(--text); }

/* ── Linha lembrar / esquecer ── */
.row-opts {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    font-size: 13px;
}
.check-label {
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    color: var(--muted);
}
.check-label input[type=checkbox] {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
    cursor: pointer;
}

/* ── Botão principal ── */
.btn-primary {
    display: block;
    width: 100%;
    padding: 13px;
    background: linear-gradient(135deg, var(--primary), var(--primary2));
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-family: var(--font);
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: opacity .2s, transform .1s, box-shadow .2s;
    box-shadow: 0 4px 20px rgba(244,114,182,.35);
    letter-spacing: .02em;
}
.btn-primary:hover  { opacity:.9; box-shadow:0 6px 28px rgba(244,114,182,.45); }
.btn-primary:active { transform:scale(.98); }

/* ── Divisor ── */
.divider {
    text-align: center;
    font-size: 12px;
    color: var(--muted);
    margin: 22px 0;
    position: relative;
}
.divider::before,
.divider::after {
    content:'';
    position: absolute;
    top: 50%;
    width: calc(50% - 28px);
    height: 1px;
    background: var(--border);
}
.divider::before { left:0; }
.divider::after  { right:0; }

/* ── Botão secundário ── */
.btn-ghost {
    display: block;
    width: 100%;
    padding: 13px;
    background: transparent;
    color: var(--muted);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    font-family: var(--font);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: all .2s;
}
.btn-ghost:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(244,114,182,.06);
}

/* ── Rodapé ── */
.foot {
    text-align: center;
    margin-top: 24px;
    font-size: 12px;
    color: var(--muted);
    opacity: .7;
}

/* ── Responsivo ── */
@media (max-width: 480px) {
    .card { padding: 28px 20px; }
    .brand-name { font-size: 24px; }
}
</style>
</head>
<body>

<?php
/* ── Lógica de autenticação ── */
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $result   = (new UserModel())->login($email, $password, $remember);
    if ($result['success']) {
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    }
    $error = $result['error'];
}
?>

<div class="wrap">

    <!-- ── LOGO / MARCA ── -->
    <div class="brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-name">Estudos GiGi</div>
        <div class="brand-sub">Sua plataforma de estudos personalizada</div>
    </div>

    <!-- ── CARD DE LOGIN ── -->
    <div class="card">
        <div class="card-title">Bem-vinda de volta 👋</div>
        <div class="card-sub">Entre para continuar seus estudos</div>

        <!-- Mensagem de erro (aparece só se houver erro) -->
        <?php if ($error): ?>
        <div class="error">
            <span>⚠</span>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Formulário de login -->
        <form method="POST" action="">

            <!-- Campo e-mail com ícone -->
            <div class="group">
                <label for="email">E-mail</label>
                <div class="input-wrap has-icon">
                    <span class="input-icon">✉</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        placeholder="seu@email.com"
                        required
                        autofocus
                        autocomplete="email">
                </div>
            </div>

            <!-- Campo senha com ícone e botão olho -->
            <div class="group">
                <label for="pwd">Senha</label>
                <div class="input-wrap has-icon">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="pwd"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        style="padding-right:44px">
                    <button type="button" class="eye-btn" onclick="togglePwd()" title="Mostrar/ocultar senha">
                        👁
                    </button>
                </div>
            </div>

            <!-- Opção "lembrar de mim" -->
            <div class="row-opts">
                <label class="check-label">
                    <input type="checkbox" name="remember">
                    Lembrar por 30 dias
                </label>
            </div>

            <!-- Botão entrar -->
            <button type="submit" class="btn-primary">
                Entrar
            </button>
        </form>

        <!-- Divisor -->
        <div class="divider">não tem conta?</div>

        <!-- Botão criar conta -->
        <a href="?page=register" class="btn-ghost">
            ✨ Criar nova conta
        </a>
    </div>

    <!-- Rodapé -->
    <p class="foot">Estudos GiGi © <?= date('Y') ?> — Desenvolvido com amor Uilian Cesar💗</p>
</div>

<script>
/* Alterna visibilidade da senha */
function togglePwd() {
    const input = document.getElementById('pwd');
    const btn   = input.nextElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
    } else {
        input.type = 'password';
        btn.textContent = '👁';
    }
}
</script>
</body>
</html>
