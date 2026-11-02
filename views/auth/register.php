<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar conta — Estudos GiGi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── Reset ── */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

/* ── Variáveis (idênticas ao login) ── */
:root {
    --bg:      #0b0d16;
    --card:    #13162a;
    --border:  rgba(255,255,255,.07);
    --primary: #f472b6;
    --primary2:#a78bfa;
    --accent:  #38bdf8;
    --text:    #f0f0ff;
    --muted:   #8b8fad;
    --green:   #34d399;
    --red:     #f87171;
    --radius:  12px;
    --font:    'Space Grotesk', sans-serif;
}

/* ── Fundo animado ── */
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    overflow-x: hidden;
    position: relative;
}
body::before {
    content:'';
    position:fixed; top:-20%; left:-10%;
    width:600px; height:600px;
    background:radial-gradient(circle,rgba(244,114,182,.18) 0%,transparent 65%);
    pointer-events:none; z-index:0;
    animation:float1 8s ease-in-out infinite;
}
body::after {
    content:'';
    position:fixed; bottom:-20%; right:-10%;
    width:500px; height:500px;
    background:radial-gradient(circle,rgba(167,139,250,.15) 0%,transparent 65%);
    pointer-events:none; z-index:0;
    animation:float2 10s ease-in-out infinite;
}
@keyframes float1 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(30px,20px)} }
@keyframes float2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-20px,-30px)} }

/* ── Container ── */
.wrap {
    width:100%; max-width:440px;
    position:relative; z-index:1;
    animation:slideUp .4s ease;
}
@keyframes slideUp {
    from{opacity:0;transform:translateY(24px)}
    to  {opacity:1;transform:translateY(0)}
}

/* ── Marca ── */
.brand { text-align:center; margin-bottom:28px; }
.brand-icon {
    width:68px; height:68px;
    background:linear-gradient(135deg,var(--primary),var(--primary2));
    border-radius:18px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:32px; margin-bottom:12px;
    box-shadow:0 8px 32px rgba(244,114,182,.35);
}
.brand-name {
    font-size:26px; font-weight:800;
    background:linear-gradient(90deg,var(--primary),var(--primary2));
    -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    letter-spacing:-.5px;
}
.brand-sub { font-size:13px; color:var(--muted); margin-top:3px; }

/* ── Card ── */
.card {
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    padding:34px 30px;
    box-shadow:0 0 0 1px rgba(244,114,182,.08), 0 24px 48px rgba(0,0,0,.4);
    position:relative; overflow:hidden;
}
.card::before {
    content:'';
    position:absolute; top:0; left:0; right:0; height:2px;
    background:linear-gradient(90deg,var(--primary),var(--primary2),var(--accent));
    border-radius:20px 20px 0 0;
}

.card-title { font-size:20px; font-weight:700; margin-bottom:4px; }
.card-sub   { font-size:13px; color:var(--muted); margin-bottom:22px; }

/* ── Erro ── */
.error {
    display:flex; align-items:center; gap:8px;
    background:rgba(248,113,113,.1);
    border:1px solid rgba(248,113,113,.3);
    border-radius:var(--radius);
    padding:11px 14px; font-size:14px; color:#fca5a5;
    margin-bottom:18px;
}

/* ── Campos ── */
.group { margin-bottom:16px; }
.group label {
    display:block; font-size:11px; font-weight:600;
    color:var(--muted); text-transform:uppercase;
    letter-spacing:.06em; margin-bottom:6px;
}
.input-wrap { position:relative; }
.input-icon {
    position:absolute; left:13px; top:50%; transform:translateY(-50%);
    font-size:15px; pointer-events:none; opacity:.5;
}
.input-wrap.has-icon input { padding-left:40px; }

input[type=email],
input[type=password],
input[type=text] {
    width:100%;
    background:rgba(255,255,255,.04);
    border:1.5px solid var(--border);
    border-radius:var(--radius);
    color:var(--text); font-family:var(--font);
    font-size:15px; padding:11px 15px;
    transition:border-color .2s,background .2s,box-shadow .2s;
    outline:none; -webkit-appearance:none;
}
input:focus {
    border-color:var(--primary);
    background:rgba(244,114,182,.05);
    box-shadow:0 0 0 3px rgba(244,114,182,.12);
}

/* ── Barra de força da senha ── */
.bar-wrap {
    height:4px; border-radius:2px;
    background:rgba(255,255,255,.07);
    overflow:hidden; margin-top:7px;
}
.bar-fill {
    height:100%; border-radius:2px;
    transition:width .3s, background .3s;
    width:0;
}
.hint { font-size:11px; margin-top:4px; display:block; }

/* ── Dois campos lado a lado em desktop ── */
.grid-2 {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

/* ── Botões ── */
.btn-primary {
    display:block; width:100%; padding:13px;
    background:linear-gradient(135deg,var(--primary),var(--primary2));
    color:#fff; border:none; border-radius:var(--radius);
    font-family:var(--font); font-size:15px; font-weight:700;
    cursor:pointer; text-align:center; text-decoration:none;
    transition:opacity .2s,transform .1s,box-shadow .2s;
    box-shadow:0 4px 20px rgba(244,114,182,.35);
    letter-spacing:.02em;
}
.btn-primary:hover  { opacity:.9; box-shadow:0 6px 28px rgba(244,114,182,.45); }
.btn-primary:active { transform:scale(.98); }

.divider {
    text-align:center; font-size:12px; color:var(--muted);
    margin:20px 0; position:relative;
}
.divider::before,.divider::after {
    content:''; position:absolute; top:50%;
    width:calc(50% - 28px); height:1px; background:var(--border);
}
.divider::before{left:0} .divider::after{right:0}

.btn-ghost {
    display:block; width:100%; padding:13px;
    background:transparent; color:var(--muted);
    border:1.5px solid var(--border); border-radius:var(--radius);
    font-family:var(--font); font-size:15px; font-weight:600;
    cursor:pointer; text-align:center; text-decoration:none;
    transition:all .2s;
}
.btn-ghost:hover { border-color:var(--primary); color:var(--primary); background:rgba(244,114,182,.06); }

.foot { text-align:center; margin-top:22px; font-size:12px; color:var(--muted); opacity:.7; }

/* ── Check de concordância ── */
.match-ok  { color:var(--green); }
.match-err { color:var(--red); }

@media(max-width:480px){
    .card { padding:26px 18px; }
    .grid-2 { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<?php
/* ── Lógica de cadastro ── */
$error = ''; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!$name)
        $error = 'Digite seu nome.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = 'E-mail inválido.';
    elseif (strlen($password) < 6)
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    elseif ($password !== $confirm)
        $error = 'As senhas não coincidem.';
    else {
        $result = (new UserModel())->register($name, $email, $password);
        if ($result['success']) {
            (new UserModel())->login($email, $password);
            header('Location: ' . BASE_URL . '/index.php?page=dashboard&welcome=1');
            exit;
        }
        $error = $result['error'];
    }
}
?>

<div class="wrap">

    <!-- ── MARCA ── -->
    <div class="brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-name">Estudos GiGi</div>
        <div class="brand-sub">Crie sua conta e comece a estudar</div>
    </div>

    <!-- ── CARD DE CADASTRO ── -->
    <div class="card">
        <div class="card-title">Criar sua conta ✨</div>
        <div class="card-sub">Preencha os dados abaixo para começar</div>

        <!-- Erro -->
        <?php if ($error): ?>
        <div class="error">
            <span>⚠</span>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="">

            <!-- Nome -->
            <div class="group">
                <label for="name">Seu nome</label>
                <div class="input-wrap has-icon">
                    <span class="input-icon">😊</span>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        placeholder="Como quer ser chamado?"
                        required
                        autofocus
                        autocomplete="name">
                </div>
            </div>

            <!-- E-mail -->
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
                        autocomplete="email">
                </div>
            </div>

            <!-- Senha com barra de força -->
            <div class="group">
                <label for="pwd1">Senha</label>
                <div class="input-wrap has-icon">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="pwd1"
                        name="password"
                        placeholder="Mínimo 6 caracteres"
                        required
                        oninput="checkStrength(this.value)"
                        autocomplete="new-password">
                </div>
                <!-- Barra visual da força da senha -->
                <div class="bar-wrap">
                    <div class="bar-fill" id="sbar"></div>
                </div>
                <span class="hint" id="slbl"></span>
            </div>

            <!-- Confirmar senha -->
            <div class="group">
                <label for="pwd2">Confirmar senha</label>
                <div class="input-wrap has-icon">
                    <span class="input-icon">🔐</span>
                    <input
                        type="password"
                        id="pwd2"
                        name="password_confirm"
                        placeholder="Repita a senha"
                        required
                        oninput="checkMatch()"
                        autocomplete="new-password">
                </div>
                <span class="hint" id="mlbl"></span>
            </div>

            <!-- Botão criar -->
            <button type="submit" class="btn-primary" style="margin-top:6px">
                Criar minha conta
            </button>
        </form>

        <div class="divider">já tem conta?</div>
        <a href="?page=login" class="btn-ghost">Entrar</a>
    </div>

    <p class="foot">Estudos GiGi © <?= date('Y') ?> — Desenvolvido com amor Uilian Cesar 💗</p>
</div>

<script>
/* ── Verificador de força da senha ── */
function checkStrength(v) {
    const bar   = document.getElementById('sbar');
    const label = document.getElementById('slbl');
    let score = 0;

    if (v.length >= 6)              score++;  // comprimento mínimo
    if (v.length >= 10)             score++;  // comprimento bom
    if (/[A-Z]/.test(v))           score++;  // maiúscula
    if (/[0-9]/.test(v))           score++;  // número
    if (/[^A-Za-z0-9]/.test(v))   score++;  // caractere especial

    const levels = [
        ['0%',   '#ef4444', ''],
        ['20%',  '#ef4444', 'Muito fraca'],
        ['40%',  '#f59e0b', 'Fraca'],
        ['65%',  '#f59e0b', 'Razoável'],
        ['85%',  '#34d399', 'Boa'],
        ['100%', '#34d399', 'Forte ✓'],
    ];
    bar.style.width      = levels[score][0];
    bar.style.background = levels[score][1];
    label.textContent    = levels[score][2];
    label.style.color    = levels[score][1];
}

/* ── Verificador de coincidência ── */
function checkMatch() {
    const p1    = document.getElementById('pwd1').value;
    const p2    = document.getElementById('pwd2').value;
    const label = document.getElementById('mlbl');
    if (!p2) { label.textContent = ''; return; }
    if (p1 === p2) {
        label.textContent = '✓ Senhas coincidem';
        label.className   = 'hint match-ok';
    } else {
        label.textContent = '✗ Senhas diferentes';
        label.className   = 'hint match-err';
    }
}
</script>
</body>
</html>
