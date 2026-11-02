<?php
$um = new UserModel(); $error=''; $success='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['_action']??''; $target=(int)($_POST['user_id']??0);
    if ($target===uid()) { $error='Não pode modificar sua própria conta aqui.'; }
    elseif ($act==='toggle_active') { $u=$um->findById($target); db()->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$u['is_active']?0:1,$target]); $success=$u['is_active']?'Usuário desativado.':'Usuário reativado.'; }
    elseif ($act==='set_admin')     { db()->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$target]); $success='Promovido a admin.'; }
    elseif ($act==='set_user')      { db()->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$target]); $success='Rebaixado para usuário.'; }
    elseif ($act==='reset_password') { $p=$_POST['new_password']??''; if(strlen($p)<6){$error='Senha mínimo 6 caracteres.';}else{db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($p,PASSWORD_BCRYPT,['cost'=>12]),$target]);$success='Senha redefinida.';} }
    elseif ($act==='delete_user')   { db()->prepare("DELETE FROM users WHERE id=? AND id!=?")->execute([$target,uid()]); $success='Usuário excluído.'; }
}
$users = $um->getAll();
$totalHours = round(array_sum(array_column($users,'total_minutes'))/60,1);
?>
<div class="page-header"><div><div class="page-title">👥 Gerenciar Usuários</div><div class="page-subtitle">Painel de administração</div></div></div>
<?php if($error): ?><div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;color:#fca5a5;font-size:14px">⚠ <?= e($error) ?></div><?php endif; ?>
<?php if($success): ?><div style="background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.4);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;color:#86efac;font-size:14px">✓ <?= e($success) ?></div><?php endif; ?>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card"><div class="stat-label">Total de usuários</div><div class="stat-value stat-blue"><?= count($users) ?></div><div class="stat-sub"><?= count(array_filter($users,fn($u)=>$u['is_active'])) ?> ativos</div></div>
    <div class="stat-card"><div class="stat-label">Sessões totais</div><div class="stat-value stat-green"><?= array_sum(array_column($users,'total_sessions')) ?></div><div class="stat-sub">todos os usuários</div></div>
    <div class="stat-card"><div class="stat-label">Horas acumuladas</div><div class="stat-value stat-amber"><?= $totalHours ?>h</div><div class="stat-sub">plataforma inteira</div></div>
    <div class="stat-card"><div class="stat-label">Admins</div><div class="stat-value"><?= count(array_filter($users,fn($u)=>$u['role']==='admin')) ?></div><div class="stat-sub">com acesso total</div></div>
</div>

<div class="card">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            <thead><tr style="color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)">
                <th style="text-align:left;padding:10px 12px;font-weight:600">Usuário</th>
                <th style="text-align:left;padding:10px 8px;font-weight:600">E-mail</th>
                <th style="text-align:center;padding:10px 8px;font-weight:600">Papel</th>
                <th style="text-align:center;padding:10px 8px;font-weight:600">Status</th>
                <th style="text-align:right;padding:10px 8px;font-weight:600">Sessões</th>
                <th style="text-align:right;padding:10px 8px;font-weight:600">Horas</th>
                <th style="text-align:right;padding:10px 8px;font-weight:600">Último acesso</th>
                <th style="text-align:center;padding:10px 8px;font-weight:600">Ações</th>
            </tr></thead>
            <tbody>
            <?php foreach($users as $u): $isMe=$u['id']===uid(); ?>
            <tr style="border-bottom:1px solid var(--border);<?= !$u['is_active']?'opacity:.5':'' ?>">
                <td style="padding:12px">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:32px;height:32px;border-radius:50%;background:<?= e($u['avatar_color']??'#6366f1') ?>;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0"><?= mb_strtoupper(mb_substr($u['name'],0,1)) ?></div>
                        <div><div style="font-weight:600"><?= e($u['name']) ?><?= $isMe?' <span style="font-size:11px;color:var(--primary-lt)">(você)</span>':'' ?></div><div style="font-size:11px;color:var(--muted)">desde <?= formatDate($u['created_at'],'d/m/Y') ?></div></div>
                    </div>
                </td>
                <td style="padding:12px 8px;color:var(--muted);font-size:13px"><?= e($u['email']) ?></td>
                <td style="padding:12px 8px;text-align:center"><span style="background:<?= $u['role']==='admin'?'rgba(99,102,241,.2)':'var(--bg3)' ?>;color:<?= $u['role']==='admin'?'var(--primary-lt)':'var(--muted)' ?>;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600"><?= $u['role']==='admin'?'Admin':'Usuário' ?></span></td>
                <td style="padding:12px 8px;text-align:center"><span style="background:<?= $u['is_active']?'rgba(34,197,94,.15)':'rgba(239,68,68,.15)' ?>;color:<?= $u['is_active']?'#4ade80':'#f87171' ?>;padding:2px 10px;border-radius:10px;font-size:12px"><?= $u['is_active']?'● Ativo':'● Inativo' ?></span></td>
                <td style="padding:12px 8px;text-align:right;font-family:var(--mono)"><?= $u['total_sessions'] ?></td>
                <td style="padding:12px 8px;text-align:right;font-family:var(--mono);color:var(--primary-lt)"><?= round($u['total_minutes']/60,1) ?>h</td>
                <td style="padding:12px 8px;text-align:right;font-size:12px;color:var(--muted)"><?= $u['last_login']?formatDate($u['last_login'],'d/m/Y H:i'):'—' ?></td>
                <td style="padding:12px 8px;text-align:center"><?php if(!$isMe): ?><button class="btn btn-ghost btn-sm" onclick="openModal(<?= $u['id'] ?>,'<?= e($u['name']) ?>',<?= $u['is_active'] ?>,'<?= $u['role'] ?>')">⋯</button><?php else: ?><span style="font-size:12px;color:var(--muted)">—</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:8000;align-items:center;justify-content:center" onclick="if(event.target===this)closeModal()">
    <div style="background:var(--card);border:1px solid var(--border);border-radius:14px;padding:28px;width:340px;max-width:90vw">
        <div style="font-size:17px;font-weight:700;margin-bottom:4px" id="modal-name"></div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:20px">Selecione uma ação</div>
        <form method="POST" action="" id="modal-form">
            <input type="hidden" name="user_id" id="modal-uid">
            <button type="submit" name="_action" id="btn-toggle" class="btn btn-ghost" style="width:100%;justify-content:start;margin-bottom:8px"></button>
            <button type="submit" name="_action" id="btn-role"   class="btn btn-ghost" style="width:100%;justify-content:start;margin-bottom:12px"></button>
            <div style="border-top:1px solid var(--border);padding-top:12px;margin-bottom:12px">
                <div style="font-size:13px;color:var(--muted);margin-bottom:8px">Redefinir senha</div>
                <div style="display:flex;gap:8px"><input type="password" name="new_password" class="form-input" placeholder="Nova senha (mín. 6)" style="flex:1"><button type="submit" name="_action" value="reset_password" class="btn btn-ghost btn-sm">OK</button></div>
            </div>
            <button type="submit" name="_action" value="delete_user" class="btn btn-danger" style="width:100%;justify-content:start" onclick="return confirm('Excluir permanentemente? Todos os dados serão apagados!')">🗑 Excluir permanentemente</button>
        </form>
        <button class="btn btn-ghost" style="width:100%;margin-top:10px" onclick="closeModal()">Cancelar</button>
    </div>
</div>
<script>
function openModal(id,name,active,role){
    document.getElementById('modal-name').textContent=name;
    document.getElementById('modal-uid').value=id;
    const bt=document.getElementById('btn-toggle');bt.value='toggle_active';bt.textContent=active?'🚫 Desativar conta':'✓ Reativar conta';
    const br=document.getElementById('btn-role');
    if(role==='admin'){br.value='set_user';br.textContent='↓ Rebaixar para usuário';}
    else{br.value='set_admin';br.textContent='↑ Promover a administrador';}
    document.getElementById('modal').style.display='flex';
}
function closeModal(){document.getElementById('modal').style.display='none';}
</script>
