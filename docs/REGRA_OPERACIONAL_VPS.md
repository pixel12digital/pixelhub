# Regra operacional fixa (Cursor) + Pacote de Execução VPS

**Para o Cursor:** Quando qualquer ação depender da VPS, você **não executa nada na VPS**. Em vez disso, você entrega no chat um **Pacote de Execução VPS** no formato abaixo. Tudo que for VPS deve vir em comandos copiar/colar, pré-checks, outputs esperados, rollback e critério de aceite.

---

## 1. Ambiente / fonte de verdade

| Item | Fonte | Quem executa |
|------|--------|--------------|
| Código PixelHub | Desenvolvido local, versionado no GitHub, publicado na HostMedia | Cursor pode editar local; deploy via commit → GitHub → produção |
| DB remoto | Compartilhado (local e produção). Cursor pode consultar/validar via DB | Cursor pode consultar |
| VPS (Gateway / WPPConnect) | Nginx, PM2, app Node do gateway | **Cursor NÃO executa.** Entrega Pacote de Execução VPS; quem tem acesso roda na VPS |

---

## 2. Formato obrigatório do “Pacote de Execução VPS”

Use este bloco sempre que houver ação na VPS. Preencha todas as seções.

```markdown
**VPS – OBJETIVO:** (ex.: eliminar WPPCONNECT_TIMEOUT em áudio)
**SERVIÇO:** (ex.: nginx + pm2 gateway)
**RISCO:** baixo | médio | alto + motivo
**ROLLBACK:** comandos + quais arquivos voltam

### 1) Pré-check (não muda nada)
**Comandos:**
(lista, copiar/colar)

**Você me retorna:**
(outputs específicos que eu devo colar de volta no chat)

### 2) Execução (mudança)
**Comandos:**
(lista em ordem, copiar/colar)

**Arquivos tocados:**
- caminho absoluto + o que muda

### 3) Reinício/Reload
**Comandos:**
(lista)

### 4) Verificação
**Comandos:**
(lista)

**Critério de sucesso:**
(objetivo mensurável, pass/fail)
```

---

## 3. Correlation ID (obrigatório Hostmidia + gateway)

Para cortar diagnóstico de horas para minutos:

- **Hostmidia:** Já envia `X-Request-Id` no request para o gateway (valor = `requestId` do `CommunicationHubController::send()`). O `WhatsAppGatewayClient` repassa esse header quando `setRequestId($requestId)` for chamado antes da requisição.
- **Gateway (VPS):** Deve:
  1. Ler o header `X-Request-Id` em cada request.
  2. Logar esse ID em **cada etapa**: `received` → `decode` (se houver) → `convert` (se WebM→OGG) → `sendVoiceBase64` → `returned`.

Quando o usuário reportar “500 às 11:57”, você pede os logs do PM2 filtrados por esse request-id e vê em qual etapa parou.

---

## 4. Mensagem curta para exigir o formato (colar no Cursor)

Quando o Cursor disser só “o que conferir na VPS” ou “verifique na VPS”, responda com **uma** das frases abaixo (copiar e colar no chat):

> Converta isso em **Pacote de Execução VPS** com comandos copiar/colar + outputs que eu devo retornar + rollback + critério de aceite. Use o formato em `docs/REGRA_OPERACIONAL_VPS.md`.

Ou, mais direto:

> Tudo que for VPS precisa vir em **Pacote de Execução VPS**: comandos copiar/colar, pré-checks, o que eu devo retornar, rollback e critério de aceite. Formato em `docs/REGRA_OPERACIONAL_VPS.md`.

---

## 5. Pacote VPS – Diagnóstico de timeout (gateway) [PRONTO PARA USAR]

**Contexto:** Log mostra ~46s + 500 com `WPPCONNECT_TIMEOUT`. Objetivo é confirmar **onde** o tempo estoura: Nginx proxy vs gateway Node vs WPPConnect.

**VPS – OBJETIVO:** Diagnosticar onde o timeout de ~46s ocorre (nginx vs gateway vs wppconnect).  
**SERVIÇO:** nginx (proxy do gateway) + PM2 (app do gateway Node).  
**RISCO:** baixo (apenas leitura e coleta de logs; não altera config em 1–4).  
**ROLLBACK:** Não há alteração de config neste pacote. Se em outro pacote você tiver editado nginx ou código do gateway, rollback = restaurar backup dos arquivos e recarregar nginx / reiniciar PM2 conforme aquele pacote.

---

### 1) Pré-check (não muda nada)

**Comandos (copiar/colar um bloco por vez):**

```bash
# A) Qual site está em uso para o gateway?
ls -la /etc/nginx/sites-enabled/ | grep -E 'wpp|whatsapp|gateway'

# B) Timeouts realmente carregados no Nginx (config ativa)
grep -r 'proxy_.*timeout\|proxy_connect\|proxy_send\|proxy_read' /etc/nginx/sites-enabled/ 2>/dev/null || grep -r 'proxy_.*timeout' /etc/nginx/ 2>/dev/null | grep -v '\.default\|#'

# C) Teste de config do Nginx
sudo nginx -t

# D) Quando foi o último reload do Nginx (se disponível)
ls -la /var/run/nginx.pid 2>/dev/null; stat /var/run/nginx.pid 2>/dev/null
```

**Você me retorna:** a saída de A, B, C e D (colar no chat).

---

### 2) PM2 / gateway saudável e logs na janela do erro

**Comandos (ajuste HORARIO para o momento do 500, ex.: 2026-01-27 11:56):**

```bash
# E) Listar processos PM2 e identificar o app do gateway
pm2 list

# F) Nome do app do gateway (ex.: wpp-ui, gateway, etc.) – use no próximo comando
# pm2 describe <nome-ou-id>

# G) Logs de 200–400 linhas (ajuste --lines e --nostream)
pm2 logs --lines 400 --nostream

# H) Se você sabe o horário exato do 500 (ex.: 11:56:55 UTC), filtrar por período
# Exemplo: logs das 11:55 às 11:58
pm2 logs --lines 500 --nostream 2>&1 | tail -400
```

**Você me retorna:**  
- Saída de `pm2 list`.  
- Trecho do log do PM2 do intervalo em que deu 500 (incluindo stacktrace, se houver).  
- Se aparecer **X-Request-Id** ou **request-id** em algum log, copie essas linhas.

---

### 3) FFmpeg e permissões para o usuário do PM2

**Comandos:**

```bash
# I) FFmpeg instalado e versão
ffmpeg -version 2>&1 | head -3

# J) Usuário do processo PM2 do gateway (troque APP pelo nome do app)
pm2 describe APP 2>/dev/null | grep -E 'exec cwd|script path|username|uid'
# Se não tiver "username", rode com o usuário que inicia o PM2:
whoami; id

# K) O usuário enxerga ffmpeg?
sudo -u $(whoami) ffmpeg -version 2>&1 | head -1
# Se o PM2 roda como outro user (ex.: www-data), teste:
sudo -u www-data ffmpeg -version 2>&1 | head -1

# L) Libopus na build do ffmpeg
ffmpeg -version 2>&1 | grep -i opus
```

**Você me retorna:** saídas de I, J, K e L (quem é o user do PM2 e se ele vê ffmpeg e opus).

---

### 4) Timeout interno no gateway (Node)

**Comando (troque CAMINHO_DO_GATEWAY pelo diretório do app, ex.: /var/www/gateway ou /home/user/wpp-ui):**

```bash
# M) Buscar timeouts no código do gateway
grep -Rn 'timeout\|setTimeout\|30000\|30\*1000\|AbortController' CAMINHO_DO_GATEWAY --include='*.js' --include='*.ts' 2>/dev/null | head -80
```

**Você me retorna:**  
- Caminho exato do diretório do gateway (onde você rodou o grep).  
- As linhas encontradas (arquivo + número + trecho).  
- Se possível, o trecho de código que envia áudio para o WPPConnect (sendVoiceBase64 ou equivalente).

---

### 5) Resumo do que você deve retornar para o Cursor

| Item | O que colar no chat |
|------|----------------------|
| Nginx | Saída de A, B, C (sites-enabled, timeouts, nginx -t). |
| Nginx reload | Saída de D (quando foi o último reload, se disponível). |
| PM2 | Saída de E (pm2 list) + trecho do log do horário do 500 (F/G/H). |
| FFmpeg | Saídas de I, J, K, L (versão, user do PM2, ffmpeg visível para esse user, opus). |
| Gateway Node | Caminho do app + saída do grep (M) (timeouts/setTimeout/30000 etc.). |

**Critério de sucesso deste pacote:**  
Ter todas as saídas acima no chat para o Cursor (ou você) decidir o próximo passo: aumentar timeouts no Nginx, aumentar/remover timeout no Node, ou ajustar conversão WebM→OGG no gateway.
