<?php
/**
 * views/notebook/index.php — Editor rico estilo Word
 * POSTs centralizados em public/index.php
 */
$subjects  = (new SubjectModel())->getAll();
$nbId      = isset($_GET['nb']) ? (int)$_GET['nb'] : null;
$pageId    = isset($_GET['p'])  ? (int)$_GET['p']  : null;

$stmtNbs = db()->prepare("
    SELECT n.*, s.name AS subject_name, s.color AS subject_color,
           COUNT(np.id) AS page_count
    FROM notebooks n
    LEFT JOIN subjects s ON s.id=n.subject_id
    LEFT JOIN notebook_pages np ON np.notebook_id=n.id
    WHERE n.user_id=?
    GROUP BY n.id
    ORDER BY n.updated_at DESC
");
$stmtNbs->execute([uid()]);
$notebooks = $stmtNbs->fetchAll();

$notebook   = null;
$pages      = [];
$activePage = null;
$stickies   = [];

if ($nbId) {
    $q = db()->prepare("SELECT n.*, s.name AS subject_name FROM notebooks n LEFT JOIN subjects s ON s.id=n.subject_id WHERE n.id=? AND n.user_id=?");
    $q->execute([$nbId, uid()]);
    $notebook = $q->fetch();

    if ($notebook) {
        $qp = db()->prepare("SELECT * FROM notebook_pages WHERE notebook_id=? ORDER BY page_order ASC");
        $qp->execute([$nbId]);
        $pages = $qp->fetchAll();

        if ($pageId) {
            foreach ($pages as $pg) {
                if ((int)$pg['id'] === $pageId) { $activePage = $pg; break; }
            }
        }
        if (!$activePage && !empty($pages)) {
            $activePage = $pages[0];
            $pageId     = (int)$activePage['id'];
        }

        $qs = db()->prepare("SELECT * FROM stickies WHERE user_id=? AND context='notebook' AND context_id=? ORDER BY created_at DESC");
        $qs->execute([uid(), $nbId]);
        $stickies = $qs->fetchAll();

        db()->prepare("UPDATE notebooks SET updated_at=NOW() WHERE id=?")->execute([$nbId]);
    }
}

$FORM = BASE_URL . '/index.php?page=notebook';
?>

<?php if (!$notebook): ?>
<!-- ══════════════════════════════════════
     TELA 1 — LISTA DE CADERNOS
══════════════════════════════════════ -->
<div class="page-header">
    <div>
        <div class="page-title">📓 Caderno Digital</div>
        <div class="page-subtitle"><?= count($notebooks) ?> caderno(s)</div>
    </div>
    <button class="btn btn-primary"
        onclick="document.getElementById('form-novo').style.display='block';this.style.display='none'">
        + Novo caderno
    </button>
</div>

<div id="form-novo" style="display:none;margin-bottom:24px">
    <div class="card" style="max-width:500px">
        <div class="card-title">Criar novo caderno</div>
        <form method="POST" action="<?= $FORM ?>">
            <input type="hidden" name="_action" value="create_notebook">
            <div class="form-group">
                <label class="form-label">Título *</label>
                <input type="text" name="title" class="form-input" required autofocus
                       placeholder="Ex: Direito Penal — Teoria do Crime">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Matéria</label>
                    <select name="subject_id" class="form-select">
                        <option value="">— Nenhuma —</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cor</label>
                    <input type="color" name="color" value="#6366f1"
                           class="form-input" style="height:44px;padding:4px 8px;cursor:pointer">
                </div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary">✓ Criar caderno</button>
                <button type="button" class="btn btn-ghost"
                    onclick="document.getElementById('form-novo').style.display='none';
                             document.querySelector('.btn-primary[onclick*=form-novo]').style.display=''">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($notebooks)): ?>
<div class="card" style="text-align:center;padding:56px 32px;color:var(--muted)">
    <div style="font-size:56px;margin-bottom:16px">📓</div>
    <div style="font-size:18px;font-weight:700;margin-bottom:10px;color:var(--text)">Nenhum caderno ainda</div>
    <p style="margin-bottom:20px">Crie um caderno para cada matéria e anote tudo das videoaulas.</p>
    <button class="btn btn-primary"
        onclick="document.getElementById('form-novo').style.display='block';this.style.display='none'">
        + Criar primeiro caderno
    </button>
</div>
<?php else: ?>
<div class="grid-3">
<?php foreach ($notebooks as $nb): ?>
<div class="card" style="border-top:4px solid <?= e($nb['color']) ?>;position:relative">
    <a href="<?= BASE_URL ?>/index.php?page=notebook&nb=<?= $nb['id'] ?>"
       style="display:block;text-decoration:none;color:inherit">
        <div style="font-weight:700;font-size:15px;margin-bottom:6px;padding-right:28px">
            <?= e($nb['title']) ?>
        </div>
        <?php if ($nb['subject_name']): ?>
        <div style="font-size:12px;color:var(--muted);margin-bottom:4px">📖 <?= e($nb['subject_name']) ?></div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--muted)">
            <?= $nb['page_count'] ?> página(s) · <?= formatDate($nb['updated_at'],'d/m/Y') ?>
        </div>
    </a>
    <form method="POST" action="<?= $FORM ?>" style="position:absolute;top:8px;right:8px">
        <input type="hidden" name="_action" value="delete_notebook">
        <input type="hidden" name="notebook_id" value="<?= $nb['id'] ?>">
        <button type="submit"
            style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px;padding:4px;line-height:1"
            onclick="return confirm('Excluir este caderno e TODAS as suas páginas?')">✕</button>
    </form>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══════════════════════════════════════
     TELA 2 — EDITOR DO CADERNO
══════════════════════════════════════ -->

<style>
/* ── Toolbar Word-like ── */
.nb-toolbar {
    display:flex;flex-wrap:wrap;gap:2px;padding:6px 10px;
    background:var(--bg2);border-bottom:1px solid var(--border);
    align-items:center;position:sticky;top:0;z-index:50;
}
.nb-toolbar .tb-sep {
    width:1px;height:22px;background:var(--border);margin:0 4px;flex-shrink:0;
}
.tb-btn {
    min-width:30px;height:28px;padding:0 7px;
    background:transparent;border:1px solid transparent;
    border-radius:5px;cursor:pointer;font-size:13px;
    color:var(--text);transition:all .1s;display:inline-flex;
    align-items:center;justify-content:center;gap:4px;
    font-family:var(--font);white-space:nowrap;flex-shrink:0;
}
.tb-btn:hover  { background:var(--bg3);border-color:var(--border); }
.tb-btn.active { background:var(--primary);color:#fff;border-color:var(--primary); }
.tb-select {
    height:28px;background:var(--bg3);border:1px solid var(--border);
    border-radius:5px;color:var(--text);font-family:var(--font);
    font-size:12px;padding:0 6px;cursor:pointer;outline:none;
}
.tb-color-btn {
    width:30px;height:28px;border-radius:5px;border:1px solid var(--border);
    cursor:pointer;padding:3px;background:var(--bg3);display:flex;
    align-items:center;justify-content:center;flex-direction:column;gap:1px;
}
.tb-color-preview {
    width:20px;height:4px;border-radius:2px;background:#e74c3c;
}

/* ── Área da folha ── */
.nb-page-area {
    background:var(--bg);min-height:calc(100vh - 160px);
    display:flex;justify-content:center;padding:32px 20px;
}
.nb-paper {
    width:100%;max-width:820px;min-height:1000px;
    background:#fff;border-radius:4px;
    box-shadow:0 2px 20px rgba(0,0,0,.25);
    padding:60px 72px;
    font-family:'Georgia','Times New Roman',serif;
    font-size:15px;line-height:1.8;color:#1a1a1a;
    outline:none;
    word-break:break-word;
}
.nb-paper:focus { box-shadow:0 2px 24px rgba(99,102,241,.3); }
.nb-paper[data-placeholder]:empty::before {
    content:attr(data-placeholder);
    color:#aaa;pointer-events:none;
    font-style:italic;
}

/* Estilos internos do editor */
.nb-paper h1 { font-size:26px;font-weight:700;margin:20px 0 10px;border-bottom:2px solid #eee;padding-bottom:6px; }
.nb-paper h2 { font-size:20px;font-weight:700;margin:16px 0 8px; }
.nb-paper h3 { font-size:16px;font-weight:700;margin:12px 0 6px; }
.nb-paper p  { margin:0 0 8px; }
.nb-paper ul, .nb-paper ol { margin:8px 0 8px 28px; }
.nb-paper li { margin-bottom:4px; }
.nb-paper blockquote {
    border-left:4px solid #6366f1;margin:12px 0;
    padding:8px 16px;background:#f8f8ff;color:#444;
    font-style:italic;border-radius:0 6px 6px 0;
}
.nb-paper table {
    border-collapse:collapse;width:100%;margin:12px 0;
}
.nb-paper td, .nb-paper th {
    border:1px solid #ddd;padding:7px 12px;text-align:left;
}
.nb-paper th { background:#f4f4f4;font-weight:700; }
.nb-paper hr  { border:none;border-top:2px solid #eee;margin:20px 0; }
.nb-paper a   { color:#6366f1; }
.nb-paper code {
    background:#f0f0f0;border-radius:3px;
    padding:1px 6px;font-family:monospace;font-size:13px;color:#e74c3c;
}
.nb-paper pre {
    background:#1e2132;color:#e2e8f0;border-radius:8px;
    padding:16px 20px;overflow-x:auto;margin:12px 0;
    font-family:'JetBrains Mono',monospace;font-size:13px;line-height:1.6;
}

/* Tema light: papel mantém fundo branco */
[data-theme="light"] .nb-page-area { background:#e8e8e8; }
[data-theme="dark"]  .nb-page-area { background:#0f1117; }
[data-theme="midnight"] .nb-page-area { background:#0d0d1a; }
[data-theme="forest"]   .nb-page-area { background:#0a1a0f; }
[data-theme="ocean"]    .nb-page-area { background:#051525; }
[data-theme="sakura"]   .nb-page-area { background:#1a0d14; }
[data-theme="candy"]    .nb-page-area { background:#ffe4f0; }
[data-theme="blush"]    .nb-page-area { background:#180f14; }
[data-theme="rosegold"] .nb-page-area { background:#1c1210; }
[data-theme="hotpink"]  .nb-page-area { background:#120510; }

/* Color picker popup */
.color-popup {
    display:none;position:absolute;top:calc(100% + 4px);left:0;
    background:var(--card);border:1px solid var(--border);border-radius:10px;
    padding:10px;z-index:200;box-shadow:0 4px 16px rgba(0,0,0,.3);
}
.color-popup.open { display:block; }
.color-popup-grid { display:grid;grid-template-columns:repeat(8,22px);gap:4px; }
.cp-swatch {
    width:22px;height:22px;border-radius:4px;cursor:pointer;border:2px solid transparent;
    transition:border-color .1s;
}
.cp-swatch:hover { border-color:#fff; }
</style>

<!-- Cabeçalho compacto do editor -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0;padding:10px 16px;background:var(--card);border:1px solid var(--border);border-radius:12px 12px 0 0;flex-wrap:wrap;gap:8px">
    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
        <a href="<?= BASE_URL ?>/index.php?page=notebook"
           style="color:var(--muted);text-decoration:none;font-size:13px;white-space:nowrap">← Cadernos</a>
        <span style="color:var(--border)">|</span>
        <input type="text" id="page-title-input"
               value="<?= e($activePage ? $activePage['title'] : ($notebook['title']??'')) ?>"
               style="background:none;border:none;font-size:15px;font-weight:700;color:var(--text);
                      outline:none;font-family:var(--font);flex:1;min-width:0">
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <span id="save-status" style="font-size:12px;color:var(--muted);white-space:nowrap">Ctrl+S salva</span>
        <button class="btn btn-primary btn-sm" onclick="savePageContent()">💾 Salvar</button>
        <!-- Páginas -->
        <div style="position:relative">
            <button class="btn btn-ghost btn-sm" onclick="toggleEl('pages-dropdown')"
                style="display:flex;align-items:center;gap:6px">
                📄 Páginas
                <span style="font-size:10px">▾</span>
            </button>
            <div id="pages-dropdown"
                 style="display:none;position:absolute;right:0;top:calc(100%+4px);background:var(--card);
                        border:1px solid var(--border);border-radius:10px;padding:8px;
                        min-width:200px;z-index:100;box-shadow:0 4px 16px rgba(0,0,0,.3)">
                <?php foreach ($pages as $pg): ?>
                <a href="<?= BASE_URL ?>/index.php?page=notebook&nb=<?= $nbId ?>&p=<?= $pg['id'] ?>"
                   style="display:block;padding:7px 10px;border-radius:6px;font-size:13px;text-decoration:none;
                          background:<?= (int)$pg['id']===$pageId?'var(--primary)':'transparent' ?>;
                          color:<?= (int)$pg['id']===$pageId?'#fff':'var(--text)' ?>;
                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px"
                   onmouseover="if(<?= (int)$pg['id']!==$pageId?'true':'false' ?>)this.style.background='var(--bg3)'"
                   onmouseout="if(<?= (int)$pg['id']!==$pageId?'true':'false' ?>)this.style.background='transparent'">
                    <?= e($pg['title']) ?>
                </a>
                <?php endforeach; ?>
                <hr style="border:none;border-top:1px solid var(--border);margin:6px 0">
                <!-- Nova página inline -->
                <form method="POST" action="<?= $FORM ?>">
                    <input type="hidden" name="_action" value="create_page">
                    <input type="hidden" name="notebook_id" value="<?= $nbId ?>">
                    <input type="text" name="page_title" class="form-input"
                           placeholder="Nome da nova página..." style="font-size:12px;margin-bottom:6px">
                    <button type="submit" class="btn btn-primary btn-sm"
                            style="width:100%;justify-content:center">+ Criar página</button>
                </form>
            </div>
        </div>
        <!-- Post-its toggle -->
        <button class="btn btn-ghost btn-sm" onclick="toggleEl('sticky-panel')"
                style="display:flex;align-items:center;gap:4px">
            📌 <span id="sticky-count"><?= count($stickies) ?></span>
        </button>
    </div>
</div>

<!-- ── TOOLBAR WORD-LIKE ─────────────────────────────────────── -->
<div class="nb-toolbar" id="nb-toolbar">

    <!-- Histórico -->
    <button class="tb-btn" onclick="doc('undo')"         title="Desfazer (Ctrl+Z)">↩</button>
    <button class="tb-btn" onclick="doc('redo')"         title="Refazer (Ctrl+Y)">↪</button>
    <div class="tb-sep"></div>

    <!-- Fonte e tamanho -->
    <select class="tb-select" onchange="changeFont(this.value)" id="sel-font" title="Fonte" style="width:130px">
        <option value="Georgia, serif">Georgia</option>
        <option value="'Arial', sans-serif">Arial</option>
        <option value="'Times New Roman', serif">Times New Roman</option>
        <option value="'Calibri', sans-serif">Calibri</option>
        <option value="'Verdana', sans-serif">Verdana</option>
        <option value="'JetBrains Mono', monospace">Mono (código)</option>
    </select>

    <select class="tb-select" onchange="changeFontSize(this.value)" id="sel-size" title="Tamanho" style="width:60px">
        <?php foreach ([10,11,12,14,16,18,20,24,28,32,36,48,72] as $sz): ?>
        <option value="<?= $sz ?>" <?= $sz===15?'selected':'' ?>><?= $sz ?></option>
        <?php endforeach; ?>
    </select>
    <div class="tb-sep"></div>

    <!-- Estilo de parágrafo -->
    <select class="tb-select" onchange="applyBlock(this.value)" id="sel-block" title="Estilo" style="width:110px">
        <option value="p">Parágrafo</option>
        <option value="h1">Título 1</option>
        <option value="h2">Título 2</option>
        <option value="h3">Título 3</option>
        <option value="pre">Código</option>
        <option value="blockquote">Citação</option>
    </select>
    <div class="tb-sep"></div>

    <!-- Formatação inline -->
    <button class="tb-btn" id="btn-bold"      onclick="doc('bold')"          title="Negrito (Ctrl+B)"><b>N</b></button>
    <button class="tb-btn" id="btn-italic"    onclick="doc('italic')"        title="Itálico (Ctrl+I)"><i>I</i></button>
    <button class="tb-btn" id="btn-underline" onclick="doc('underline')"     title="Sublinhado (Ctrl+U)"><u>S</u></button>
    <button class="tb-btn" id="btn-strike"    onclick="doc('strikeThrough')" title="Riscado"><s>R</s></button>
    <div class="tb-sep"></div>

    <!-- Cor do texto -->
    <div style="position:relative">
        <button class="tb-color-btn" onclick="toggleEl('cp-text')" title="Cor do texto (A)">
            <span style="font-size:12px;font-weight:700;color:var(--text)">A</span>
            <span class="tb-color-preview" id="text-color-preview"></span>
        </button>
        <div class="color-popup" id="cp-text">
            <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Cor do texto</div>
            <div class="color-popup-grid" id="cp-text-grid"></div>
            <input type="color" id="text-color-custom" style="width:100%;margin-top:8px;height:28px;cursor:pointer"
                   onchange="applyColor('foreColor', this.value)">
        </div>
    </div>

    <!-- Cor de fundo/destaque -->
    <div style="position:relative">
        <button class="tb-color-btn" onclick="toggleEl('cp-bg')" title="Destaque">
            <span style="font-size:12px">🖍</span>
            <span class="tb-color-preview" id="bg-color-preview" style="background:#fff176"></span>
        </button>
        <div class="color-popup" id="cp-bg">
            <div style="font-size:11px;color:var(--muted);margin-bottom:6px">Cor de destaque</div>
            <div class="color-popup-grid" id="cp-bg-grid"></div>
        </div>
    </div>
    <div class="tb-sep"></div>

    <!-- Alinhamento -->
    <button class="tb-btn" id="btn-left"    onclick="doc('justifyLeft')"   title="Alinhar à esquerda">⬤ ≡</button>
    <button class="tb-btn" id="btn-center"  onclick="doc('justifyCenter')" title="Centralizar">≡</button>
    <button class="tb-btn" id="btn-right"   onclick="doc('justifyRight')"  title="Alinhar à direita">≡ ⬤</button>
    <button class="tb-btn" id="btn-justify" onclick="doc('justifyFull')"   title="Justificar">⬛</button>
    <div class="tb-sep"></div>

    <!-- Listas -->
    <button class="tb-btn" id="btn-ul" onclick="doc('insertUnorderedList')" title="Lista com marcadores">• —</button>
    <button class="tb-btn" id="btn-ol" onclick="doc('insertOrderedList')"   title="Lista numerada">1.</button>
    <button class="tb-btn"             onclick="doc('indent')"              title="Aumentar recuo">→</button>
    <button class="tb-btn"             onclick="doc('outdent')"             title="Diminuir recuo">←</button>
    <div class="tb-sep"></div>

    <!-- Inserir -->
    <button class="tb-btn" onclick="insertHR()"    title="Linha horizontal">─</button>
    <button class="tb-btn" onclick="insertTable()" title="Inserir tabela">⊞</button>
    <button class="tb-btn" onclick="insertLink()"  title="Inserir link">🔗</button>
    <div class="tb-sep"></div>

    <!-- Limpar / imprimir -->
    <button class="tb-btn" onclick="doc('removeFormat')" title="Remover formatação">✕A</button>
    <button class="tb-btn" onclick="printPage()"         title="Imprimir página">🖨</button>

</div>

<!-- ── ÁREA DA FOLHA ─────────────────────────────────────────── -->
<div class="nb-page-area" id="page-area">
    <div
        id="editor"
        class="nb-paper"
        contenteditable="true"
        spellcheck="true"
        data-placeholder="Clique aqui e comece a digitar suas anotações...&#10;&#10;Use a barra de ferramentas acima para formatar seu texto como no Word."
        <?php if ($activePage && trim($activePage['content'] ?? '') !== ''): ?>
        ><?= $activePage['content'] ?></div>
        <?php else: ?>
        ></div>
        <?php endif; ?>
</div>

<!-- ── PAINEL DE POST-ITS (drawer lateral) ───────────────────── -->
<div id="sticky-panel" style="
    display:none;position:fixed;right:0;top:0;height:100vh;width:280px;
    background:var(--card);border-left:1px solid var(--border);
    z-index:300;overflow-y:auto;padding:16px;
    box-shadow:-4px 0 20px rgba(0,0,0,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <strong style="font-size:14px">📌 Post-its</strong>
        <button onclick="toggleEl('sticky-panel')"
            style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:18px;padding:0">✕</button>
    </div>

    <!-- Adicionar post-it -->
    <form method="POST" action="<?= $FORM ?>" style="margin-bottom:14px">
        <input type="hidden" name="_action" value="nb_sticky_add">
        <input type="hidden" name="notebook_id" value="<?= $nbId ?>">
        <input type="hidden" name="page_id"     value="<?= $pageId ?>">
        <textarea name="content" class="form-textarea"
                  placeholder="Lembrete, dúvida, link..."
                  style="min-height:64px;margin-bottom:8px;font-size:13px"></textarea>
        <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap" id="nb-colors">
            <?php foreach (['#fbbf24','#f472b6','#34d399','#60a5fa','#a78bfa','#fb923c','#ffffff','#f0f0f0'] as $i=>$c): ?>
            <label style="cursor:pointer">
                <input type="radio" name="color" value="<?= $c ?>"
                       <?= $i===0?'checked':'' ?> class="nb-cr" style="display:none">
                <span class="nb-cs" style="display:inline-block;width:22px;height:22px;border-radius:4px;
                      background:<?= $c ?>;border:2px solid <?= $i===0?'var(--text)':'var(--border)' ?>;cursor:pointer"></span>
            </label>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto">+ Add</button>
        </div>
    </form>

    <!-- Post-its existentes -->
    <div id="stickies-list">
        <?php if (empty($stickies)): ?>
        <p style="font-size:12px;color:var(--muted);text-align:center">Nenhum post-it ainda</p>
        <?php endif; ?>
        <?php foreach ($stickies as $s): ?>
        <div style="background:<?= e($s['color']) ?>;border-radius:8px;padding:11px 11px 26px;
                    position:relative;margin-bottom:10px;box-shadow:1px 2px 8px rgba(0,0,0,.2)">
            <p style="font-size:12px;color:#1a1a1a;line-height:1.5;word-break:break-word">
                <?= nl2br(e($s['content'])) ?>
            </p>
            <form method="POST" action="<?= $FORM ?>" style="position:absolute;bottom:4px;right:5px">
                <input type="hidden" name="_action"     value="nb_sticky_del">
                <input type="hidden" name="sid"         value="<?= $s['id'] ?>">
                <input type="hidden" name="notebook_id" value="<?= $nbId ?>">
                <input type="hidden" name="page_id"     value="<?= $pageId ?>">
                <button type="submit"
                    style="background:rgba(0,0,0,.15);border:none;border-radius:4px;
                           padding:1px 7px;cursor:pointer;font-size:11px;color:#333"
                    onclick="return confirm('Remover?')">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Excluir página -->
    <?php if ($activePage): ?>
    <div style="margin-top:20px;padding-top:14px;border-top:1px solid var(--border)">
        <form method="POST" action="<?= $FORM ?>"
              onsubmit="return confirm('Excluir esta página permanentemente?')">
            <input type="hidden" name="_action"     value="delete_page">
            <input type="hidden" name="page_id"     value="<?= $activePage['id'] ?>">
            <input type="hidden" name="notebook_id" value="<?= $nbId ?>">
            <button type="submit" class="btn btn-ghost btn-sm"
                    style="color:var(--red);width:100%;justify-content:center">
                🗑 Excluir esta página
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Formulário oculto para salvar via AJAX -->
<form id="save-form" method="POST" action="<?= $FORM ?>" style="display:none">
    <input type="hidden" name="_action"     value="save_page">
    <input type="hidden" name="notebook_id" value="<?= $nbId ?>">
    <input type="hidden" name="page_id"     value="<?= $pageId ?>">
    <input type="hidden" name="page_title"  id="save-title">
    <input type="hidden" name="content"     id="save-content">
</form>

<script>
// ── Cores para os pickers ────────────────────────────────────
const TEXT_COLORS = [
    '#000000','#1a1a1a','#333333','#555555','#777777','#999999','#bbbbbb','#ffffff',
    '#e74c3c','#e67e22','#f1c40f','#2ecc71','#1abc9c','#3498db','#9b59b6','#e91e63',
    '#c0392b','#d35400','#f39c12','#27ae60','#16a085','#2980b9','#8e44ad','#c2185b',
    '#ff6b6b','#ffa94d','#ffd43b','#69db7c','#38d9a9','#74c0fc','#cc5de8','#f783ac',
];
const BG_COLORS = [
    '#fff176','#ffe0b2','#ffccbc','#f8bbd0','#e1bee7','#bbdefb','#b2dfdb','#c8e6c9',
    '#fff9c4','#fff3e0','#fce4ec','#f3e5f5','#e3f2fd','#e0f7fa','#e8f5e9','#f1f8e9',
    'transparent',
];

// Preenche grids de cor
function buildColorGrid(gridId, colors, fn) {
    const g = document.getElementById(gridId); if(!g) return;
    colors.forEach(c => {
        const s = document.createElement('div');
        s.className = 'cp-swatch';
        s.style.background = c === 'transparent' ? 'linear-gradient(135deg,#fff 45%,#f00 45%,#f00 55%,#fff 55%)' : c;
        s.style.border = '2px solid ' + (c==='#ffffff'?'#ccc':'transparent');
        s.title = c;
        s.onclick = () => fn(c);
        g.appendChild(s);
    });
}
buildColorGrid('cp-text-grid', TEXT_COLORS, c => applyColor('foreColor', c));
buildColorGrid('cp-bg-grid',   BG_COLORS,   c => applyColor('hiliteColor', c));

function applyColor(cmd, color) {
    const editor = document.getElementById('editor');
    editor.focus();
    document.execCommand(cmd, false, color);
    if (cmd === 'foreColor') {
        document.getElementById('text-color-preview').style.background = color;
        document.getElementById('text-color-custom').value = color;
    } else {
        document.getElementById('bg-color-preview').style.background = color;
    }
    ['cp-text','cp-bg'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('open');
    });
    markDirty();
}

// ── execCommand wrapper ──────────────────────────────────────
function doc(cmd, val) {
    document.getElementById('editor').focus();
    document.execCommand(cmd, false, val || null);
    updateToolbarState();
    markDirty();
}

function changeFont(font) {
    document.getElementById('editor').focus();
    document.execCommand('fontName', false, font);
    markDirty();
}

function changeFontSize(size) {
    // execCommand fontSize usa 1–7, usamos estilo direto
    document.getElementById('editor').focus();
    const sel = window.getSelection();
    if (!sel.rangeCount) return;
    const range = sel.getRangeAt(0);
    if (range.collapsed) return;
    document.execCommand('fontSize', false, '7');
    document.querySelectorAll('#editor font[size="7"]').forEach(el => {
        el.removeAttribute('size');
        el.style.fontSize = size + 'px';
    });
    markDirty();
}

function applyBlock(tag) {
    document.getElementById('editor').focus();
    document.execCommand('formatBlock', false, '<' + tag + '>');
    updateToolbarState();
    markDirty();
}

function insertHR() {
    doc('insertHTML', '<hr><p><br></p>');
}

function insertTable() {
    const rows = parseInt(prompt('Número de linhas:', '3') || '3');
    const cols = parseInt(prompt('Número de colunas:', '3') || '3');
    if (!rows || !cols) return;
    let html = '<table><thead><tr>';
    for (let c=0;c<cols;c++) html += '<th>Coluna '+(c+1)+'</th>';
    html += '</tr></thead><tbody>';
    for (let r=0;r<rows-1;r++) {
        html += '<tr>';
        for (let c=0;c<cols;c++) html += '<td>&nbsp;</td>';
        html += '</tr>';
    }
    html += '</tbody></table><p><br></p>';
    doc('insertHTML', html);
}

function insertLink() {
    const url  = prompt('URL do link:', 'https://');
    if (!url) return;
    const text = prompt('Texto do link:', url);
    if (!text) return;
    doc('insertHTML', '<a href="'+url+'" target="_blank">'+text+'</a>');
}

function printPage() {
    const content = document.getElementById('editor').innerHTML;
    const title   = document.getElementById('page-title-input').value || 'Página';
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><title>${title}</title>
    <style>body{font-family:Georgia,serif;font-size:14px;line-height:1.8;max-width:800px;margin:40px auto;color:#000}
    h1,h2,h3{margin:16px 0 8px}table{border-collapse:collapse;width:100%}
    td,th{border:1px solid #ccc;padding:6px 10px}blockquote{border-left:4px solid #999;padding:8px 16px;color:#444;font-style:italic}
    pre{background:#f4f4f4;padding:12px;border-radius:4px}hr{border:none;border-top:1px solid #ccc;margin:16px 0}
    @page{margin:2cm}</style></head><body>
    <h1 style="border-bottom:2px solid #999;padding-bottom:8px">${title}</h1>
    ${content}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => { w.print(); w.close(); }, 500);
}

// ── Atualiza estado dos botões da toolbar ────────────────────
function updateToolbarState() {
    const cmds = {
        'btn-bold':      'bold',
        'btn-italic':    'italic',
        'btn-underline': 'underline',
        'btn-strike':    'strikeThrough',
        'btn-left':      'justifyLeft',
        'btn-center':    'justifyCenter',
        'btn-right':     'justifyRight',
        'btn-justify':   'justifyFull',
        'btn-ul':        'insertUnorderedList',
        'btn-ol':        'insertOrderedList',
    };
    Object.entries(cmds).forEach(([id, cmd]) => {
        const btn = document.getElementById(id); if (!btn) return;
        btn.classList.toggle('active', document.queryCommandState(cmd));
    });
}

// ── Auto-save ────────────────────────────────────────────────
let dirty = false, saveTimer = null;
const NB_ID   = <?= (int)$nbId ?>;
const PAGE_ID = <?= (int)$pageId ?>;
const API_URL = '<?= $FORM ?>';

function markDirty() {
    dirty = true;
    setStatus('Não salvo...', 'var(--accent)');
    clearTimeout(saveTimer);
    saveTimer = setTimeout(savePageContent, 4000);
}
function setStatus(txt, color) {
    const el = document.getElementById('save-status');
    if (el) { el.textContent = txt; el.style.color = color; }
}

async function savePageContent() {
    const title   = document.getElementById('page-title-input')?.value   ?? '';
    const content = document.getElementById('editor')?.innerHTML          ?? '';
    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                _action:     'save_page',
                notebook_id: NB_ID,
                page_id:     PAGE_ID,
                page_title:  title,
                content:     content,
            })
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        setStatus('Salvo às ' + data.saved, 'var(--green)');
        dirty = false;
    } catch(err) {
        setStatus('Erro ao salvar!', 'var(--red)');
        console.error(err);
    }
}

// ── Eventos ──────────────────────────────────────────────────
const editor = document.getElementById('editor');

editor.addEventListener('input',     markDirty);
editor.addEventListener('keyup',     updateToolbarState);
editor.addEventListener('mouseup',   updateToolbarState);
editor.addEventListener('selectionchange', updateToolbarState);

document.getElementById('page-title-input')?.addEventListener('input', markDirty);

document.addEventListener('keydown', e => {
    if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); savePageContent(); }
    if ((e.ctrlKey||e.metaKey) && e.key==='p') { e.preventDefault(); printPage(); }
});

// Fecha dropdowns e color pickers ao clicar fora
document.addEventListener('click', e => {
    if (!e.target.closest('#pages-dropdown') && !e.target.closest('[onclick*=pages-dropdown]'))
        document.getElementById('pages-dropdown')?.style.setProperty('display','none');
    ['cp-text','cp-bg'].forEach(id => {
        const el = document.getElementById(id);
        if (el && !e.target.closest('#'+id) && !e.target.closest('[onclick*='+id+']'))
            el.classList.remove('open');
    });
});

function toggleEl(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (id === 'cp-text' || id === 'cp-bg') {
        el.classList.toggle('open');
    } else {
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    }
}

// ── Post-it color pickers ────────────────────────────────────
document.querySelectorAll('.nb-cr').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.nb-cs').forEach(s => s.style.borderColor = 'var(--border)');
        r.nextElementSibling.style.borderColor = 'var(--text)';
    });
    if (r.checked) r.nextElementSibling.style.borderColor = 'var(--text)';
});

// ── Aviso ao sair sem salvar ─────────────────────────────────
window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>

<?php endif; ?>
