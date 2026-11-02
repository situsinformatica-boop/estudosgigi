# Publicação do estudosgigi por FTPS

O workflow `.github/workflows/deploy.yml` está em **simulação** (`DRY_RUN: 'true'`).
Nenhuma publicação foi executada durante a configuração local.

## O que vai para a hospedagem

O projeto PHP não possui build Node. `public/index.php` carrega arquivos de pastas
irmãs, portanto publicar somente `public/` quebraria o sistema. O pacote mantém:

```
<FTP_SERVER_DIR>/
  config/app.php
  public/index.php
  public/css/
  public/js/
  src/
  views/
```

O preparador usa somente arquivos versionados, com pastas e extensões permitidas.
Não envia `.git`, `.github`, `.env`, arquivos ocultos, SQL, documentação, dependências,
backups, temporários, caches, logs, uploads, `config/database.php` ou `.htaccess`.
Arquivos novos com extensões/pastas diferentes precisam de revisão na lista permitida.
Não adicione credenciais a arquivos de aplicação: nenhuma lista de exclusões consegue
identificar todos os segredos arbitrariamente inseridos em código.

`config/database.php` contém a configuração local e também a função `db()`: a versão
de produção precisa existir no servidor com os dados corretos. Este workflow nunca
a envia, gera ou altera. Não executa migrações de banco de dados.

## Destino e domínio: verificar antes da publicação real

Não foi possível acessar a conta FTP nesta sessão. Os Secrets do GitHub não podem
ser recuperados para uso local. Cadastre o quarto Secret `FTP_SERVER_DIR` depois de
confirmar o diretório no cPanel, com o suporte ou por um cliente FTPS.

Conecte usando FTPS **explícito**, porta **21**, validação de certificado ativa e os
mesmos dados dos Secrets. Verifique qual pasta corresponde a `public_html` para
essa conta específica. Se o login já estiver limitado a `public_html`, o caminho
pode ser `/`; se a pasta aparecer dentro da raiz FTP, pode ser `public_html/` ou
outro caminho. São exemplos, não valores verificados. Use o caminho efetivamente
confirmado, terminando em `/`. O workflow não cria o destino principal se ele não existir.

**A raiz web deste projeto é `public/`, não a raiz do repositório.** Para publicar
na raiz de `situsinformatica.com.br` preservando a estrutura, configure a raiz de
documentos do domínio para a subpasta `public` dentro do destino (por exemplo,
`public_html/public`, se esse for o destino físico confirmado). A raiz de documentos
é uma configuração HTTP/cPanel e não é o mesmo que o caminho visível no FTP.
Se o domínio principal não permitir a alteração, solicite orientação ao suporte
antes de ativar o deploy; será necessário definir uma alternativa de roteamento e
proteção das pastas privadas. Não aponte o Secret simplesmente para `public/`:
isso criaria outra subpasta `public` dentro dela.

Preserve/configure manualmente os `.htaccess` necessários, inclusive o de proteção
de `public/uploads/`, e garanta que `config`, `src` e `views` não sejam expostos
diretamente pela web. O workflow não sobrescreve esses arquivos da hospedagem.

## Como funciona

1. Push na `main` ou execução manual na `main` inicia o workflow. Outros branches
   não publicam. Execuções usam a mesma fila, sem interromper uma transferência ativa.
2. Checkout com permissão somente de leitura e sem persistir o token Git.
3. `prepare_deploy.py` prepara `.deploy/` e imprime `PACKAGE` e `IGNORE` para cada
   arquivo versionado. Nada fora desse pacote é enviado.
4. `guard_deploy.py` autentica com TLS, protege também o canal de dados e exige um
   destino existente. Lê o estado anterior somente em memória e protege todos os
   caminhos antigos ausentes do pacote. Estado desconhecido/inválido interrompe o job.
5. A Action SamKirkland v4.4.0, fixada pelo SHA do commit, calcula as diferenças.
   Em simulação, não transfere arquivos. Depois de ativada, envia novos/alterados e
   mantém o arquivo de controle `.estudosgigi-deploy-state.json` no servidor.

**Não remova a etapa de proteção nem sua saída `exclude`.** A Action possui remoções
incrementais por padrão; apenas omitir `dangerous-clean-slate` não seria suficiente.
Os filtros se aplicam ao estado remoto antes do cálculo das diferenças. Assim, um
arquivo removido/renomeado no Git permanece no servidor e deixa de ser gerenciado.
Arquivos remotos que nunca estiveram no estado também são preservados. Limpezas de
arquivos obsoletos serão sempre manuais.

Arquivos da aplicação com o mesmo caminho serão atualizados no deploy real. No
primeiro envio, sem estado anterior, a Action pode reenviar todos os arquivos do
pacote, inclusive os já existentes. Confira as substituições na simulação. Não
edite simultaneamente o estado remoto ou execute outro publicador sobre o mesmo
destino. O FTPS não oferece uma troca atômica do site inteiro.

As credenciais vêm exclusivamente dos Secrets e não são gravadas em arquivos.
Não há `setup-node`: a Action usa o runtime fornecido pelo GitHub. Os auxiliares
usam Python padrão do runner Ubuntu, sem instalar dependências.

## Primeiro teste

1. Confirme `FTP_SERVER` (somente hostname), `FTP_USERNAME`, `FTP_PASSWORD` e
   cadastre `FTP_SERVER_DIR`. Não cole a senha nos logs ou no repositório.
2. Envie apenas os arquivos de implantação inicialmente:

   ```powershell
   git add .github/workflows/deploy.yml .github/scripts .github/DEPLOY.md
   git commit -m "Configura deploy FTPS com simulacao e protecao de arquivos"
   git push origin main
   ```

3. Em GitHub → Actions → **Deploy estudosgigi (FTPS)**, abra a execução. Também é
   possível usar **Run workflow**, selecionando `main`.
4. Confira `PACKAGE`, `IGNORE`, `PRESERVE REMOTE` e o plano da Action. Não deve haver
   remoções. O destino vem de `FTP_SERVER_DIR`; o GitHub mascara o valor do Secret
   nos logs, portanto compare com o diretório confirmado no cliente FTP/cPanel.
5. Confirme conexão, autenticação e listagem funcionando. O dry-run não testa
   permissão de escrita, espaço disponível nem execução PHP/MySQL. Não testa o site.

## Ativar o deploy definitivo

Depois de validar o plano, a raiz web e os arquivos de produção preservados, altere
apenas `DRY_RUN: 'true'` para `DRY_RUN: 'false'` no workflow, faça commit e push na
`main`. Esse próprio push já executa a primeira publicação real. A partir daí cada
push na `main` publica automaticamente. Para voltar à simulação, retorne a `true`.

## Erros no GitHub Actions

| Mensagem/sintoma | Verificação |
| --- | --- |
| Missing GitHub Secret | Nome e disponibilidade dos quatro Secrets no repositório |
| DNS, timeout, conexão recusada | Host, porta 21, serviço FTPS e bloqueios de firewall |
| 530 / Login incorrect | Usuário FTP completo, senha e status da conta |
| Certificado inválido / hostname mismatch | Solicitar ao suporte o hostname coberto pelo certificado; não desativar validação |
| 550 / Failed to change directory | Caminho relativo à raiz dessa conta, existência e permissões da pasta |
| 425 / 426 durante listagem | Portas passivas, firewall e configuração TLS do servidor |
| MLSD não suportado | Solicitar suporte a listagem estruturada ao provedor; a proteção interrompe o job |
| Unknown/Unsafe deployment state | Revisar o arquivo de estado; não ignorar a proteção |
| Falha de upload após dry-run OK | Permissão de escrita, quota/espaço e conexão de dados |
| Site com erro PHP ou 404 após envio | Raiz web `public/`, configuração de produção, banco e versão PHP |

Referência: https://github.com/SamKirkland/FTP-Deploy-Action/tree/v4.4.0
