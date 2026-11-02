# 📚 StudyTracker v3 — Guia de Instalação no WAMP

## Instalação (passo a passo)

### 1. Copie a pasta para o WAMP
Extraia o ZIP e coloque a pasta dentro de:
```
C:\wamp64\www\studytracker\
```
A estrutura deve ficar assim:
```
C:\wamp64\www\studytracker\
    config\
    database\
    public\         ← raiz do site
    src\
    views\
```

### 2. Crie o banco de dados
Abra o **phpMyAdmin** e execute o arquivo `database/schema.sql` via **Importar**.
Ele cria o banco `studytracker` com todas as tabelas e conquistas.

### 3. Configure a conexão
Edite `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // senha do MySQL (geralmente vazia no WAMP)
define('DB_NAME', 'studytracker');
```

### 4. Acesse no navegador
Com o WAMP rodando (ícone verde):
```
http://localhost/studytracker/public/index.php
```
Você será redirecionado para a tela de login automaticamente.

### 5. Crie sua conta
Clique em **"Criar nova conta"** e cadastre-se.

### 6. Torne-se admin (opcional)
Após criar a primeira conta, execute no phpMyAdmin:
```sql
UPDATE users SET role = 'admin' WHERE email = 'seu@email.com';
```
Isso libera o painel **Usuários** no menu lateral.

---

## Páginas do sistema

| URL                                          | Página         |
|----------------------------------------------|----------------|
| `index.php?page=login`                       | Login          |
| `index.php?page=register`                    | Cadastro       |
| `index.php?page=dashboard`                   | Dashboard      |
| `index.php?page=timer`                       | Timer/Pomodoro |
| `index.php?page=subjects`                    | Matérias       |
| `index.php?page=review`                      | Revisão        |
| `index.php?page=reports`                     | Relatórios     |
| `index.php?page=goals`                       | Metas          |
| `index.php?page=gamification`                | Conquistas     |
| `index.php?page=focus`                       | Modo Foco      |
| `index.php?page=profile`                     | Perfil         |
| `index.php?page=settings`                    | Configurações  |
| `index.php?page=admin` *(só admin)*          | Usuários       |

---

## Solução de problemas comuns

**Ícone do WAMP laranja (porta em conflito):**
O Skype ou IIS pode estar usando a porta 80. Troque para 8080 em:
Apache → `httpd.conf` → `Listen 80` → `Listen 8080`
Então acesse: `http://localhost:8080/studytracker/public/index.php`

**Erro "GENERATED ALWAYS AS" no MySQL 5.6:**
O MySQL 5.6 não suporta colunas geradas. Use MySQL 5.7+ ou MariaDB 10.2+.
O WAMP 3.x já vem com MySQL 5.7 ou 8.x por padrão.

**Tela em branco ou erro 500:**
Ative os erros PHP no `php.ini` do WAMP:
```ini
display_errors = On
error_reporting = E_ALL
```

**Não redireciona para login:**
Certifique-se de acessar `index.php` diretamente — não abra arquivos de views diretamente.
