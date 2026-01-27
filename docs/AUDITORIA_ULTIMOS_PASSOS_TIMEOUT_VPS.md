# Auditoria completa dos últimos passos – Timeout de áudio e VPS

**Data da auditoria:** 27/01/2026  
**Escopo:** Investigação do timeout ~46s no envio de áudio WhatsApp; blocos de comandos VPS (BLOCO 1, 2, 3); alteração de timeouts Nginx; reload do Nginx na VPS srv817568.

---

## 1. Contexto e objetivo

| Item | Descrição |
|------|------------|
| **Problema** | Envio de áudio de 4–10s retorna 500 em ~46s com mensagem de timeout (WPPCONNECT_TIMEOUT ou similar). |
| **Hipótese inicial** | Timeout do proxy Nginx (60s) ou do gateway/Node/WPPConnect. |
| **Objetivo** | Identificar onde o timeout ocorre e, na VPS, aumentar timeouts do Nginx de 60s para 120s; depois testar de novo o áudio. |
| **Regra** | **Regra de Triangulação:** só o Cursor pede comandos ao Charles na VPS; um bloco por vez; outputs exatos a retornar; após retorno: outputs + resumo + hipótese + próximo bloco ou patch local. |

**Referências obrigatórias:**

- `docs/REGRA_TRIANGULACAO.md` – fluxo VPS
- `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` – diretriz + Pacote de Execução VPS (seção 4)
- `.cursor/rules/regra-vps.mdc` – regra aplicada sempre

---

## 2. Blocos executados na VPS (Charles)

### 2.1 BLOCO 1 – Pré-check

**Comandos enviados (resumo):**

- `pm2 list`
- `grep -r 'proxy_.*timeout|...' /etc/nginx/sites-enabled/ ...`
- `sudo nginx -t`
- `ls -la /etc/nginx/sites-enabled/`

**Resultados consolidados:**

| Verificação | Resultado |
|-------------|-----------|
| **PM2** | App **wpp-ui** (id 0), **online**, uptime 5D, user root. |
| **nginx -t** | OK (syntax + test successful). |
| **sites-enabled** | `whatsapp-multichannel` → `/etc/nginx/sites-available/whatsapp-multichannel`; outro site `wpp.pixel12digital.com.br` (1 byte). |
| **Timeouts** | Saída do grep de timeouts não veio completa no retorno; BLOCO 2 detalhou. |

---

### 2.2 BLOCO 2 – Timeouts no site do gateway

**Comando enviado:**

```bash
grep -n 'proxy_\|timeout\|listen\|server_name' /etc/nginx/sites-available/whatsapp-multichannel | head -60
```

**Resultados:**

| Elemento | Linhas / valor |
|----------|-----------------|
| **listen** | 8081 |
| **server_name** | _ |
| **proxy_pass** | http://localhost:3000 (em mais de um location) |
| **proxy_connect_timeout** | 60s (linha 36) |
| **proxy_send_timeout** | 60s (linha 37) |
| **proxy_read_timeout** | 60s (linha 38) |

**Conclusão BLOCO 2:** Timeouts do proxy estavam em **60s** nas linhas 36–38. O atraso de ~46s no Hub é compatível com esse limite ou com timeout interno do Node/WPPConnect.

---

### 2.3 BLOCO 3 – Backup, 60s→120s, nginx -t, reload

**Objetivo:** No arquivo `whatsapp-multichannel`, alterar os três `proxy_*_timeout` de 60s para 120s, testar a config e recarregar o Nginx.

**Fonte dos comandos:**  
`docs/bloco3-timeout-nginx.sh` ou o bloco de código da seção 4.2.1 de `DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md`.

**Sequência executada (resumo):**

1. **Backup** – `sudo cp "$FILE" "${FILE}.bak.$(date +%Y%m%d_%H%M%S)"` → OK.
2. **Exibição do backup** – `ls -la "${FILE}.bak."* | tail -1` → OK (caminho do .bak retornado).
3. **Alteração** – `sudo sed -i ... 60s → 120s ...` → OK.
4. **Grep das linhas alteradas** – saída com as 3 linhas em 120s.
5. **nginx -t** – `syntax is ok`, `test is successful`.
6. **Reload** – comando original era `sudo kill -HUP $(cat /var/run/nginx.pid)`.

**Resultado do reload (primeira tentativa):**

- `kill -HUP $(cat /var/run/nginx.pid)` → **Falhou.**  
- Saída: "Usage: kill [options] <pid> [...]" (nenhum PID enviado).  
- **Causa provável:** `/var/run/nginx.pid` vazio ou inexistente na VPS srv817568.

**Conclusão parcial BLOCO 3:** Backup, sed e `nginx -t` **ok**. Reload **não** aplicado na primeira rodada.

---

## 3. Incidente: colagem do documento inteiro no terminal

**O que aconteceu:**  
O Charles colou no terminal da VPS **o conteúdo completo** de `DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` (markdown, tabelas, listas), em vez de só o bloco de comandos bash.

**Efeito:**  
O shell interpretou linhas do doc como comando:

- "command not found" (ex.: `pedir`, `Qualquer`)
- "syntax error near unexpected token `('"
- "No such file or directory" em trechos que pareciam caminhos
- "base64: extra operand 'removed'"

**Ajustes feitos no repositório:**

1. **Aviso na diretriz (seção 4.2.1):**  
   - Texto explícito: **"COPIAR SOMENTE O BLOCO DE COMANDOS"**.  
   - "NÃO colar o resto do documento (títulos, tabelas, texto em markdown)."  
   - Explicação: colar o doc inteiro gera "command not found" e "syntax error".

2. **Arquivo só com comandos:**  
   - Criado **`docs/bloco3-timeout-nginx.sh`** contendo **apenas** as linhas bash do BLOCO 3.  
   - Na diretriz: "Alternativa: copiar o conteúdo do arquivo **`docs/bloco3-timeout-nginx.sh`**."

**Arquivos alterados:**

- `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` (aviso + referência ao .sh)

**Arquivo criado:**

- `docs/bloco3-timeout-nginx.sh`

---

## 4. Reload do Nginx – tentativas e comando que funcionou

**Comandos tentados pelo Charles (após ajuste do BLOCO 3):**

| Comando | Resultado |
|---------|-----------|
| `sudo nginx -s reload` | `[notice] signal process started` + `[error] invalid PID number "" in "/run/nginx.pid"`. Reload **não** concluído (pid file vazio). |
| `sudo systemctl reload nginx` | "nginx.service is not active, cannot reload." Nginx **não** gerenciado por systemd nesse servidor. |
| `sudo service nginx reload` | Saída vazia, prompt normal. Em ambiente SysV/init, **sucesso**. |

**Conclusão:**  
Na VPS **srv817568**, o reload que **funcionou** foi:

```bash
sudo service nginx reload
```

**Estado do Nginx nessa VPS:**

- **PID file:** `/run/nginx.pid` existe mas está **vazio** ou inválido → `nginx -s reload` e `kill -HUP $(cat /var/run/nginx.pid)` falham.
- **systemd:** serviço nginx **não** ativo/gerenciado por systemd.
- **Init/SysV:** Nginx controlado por **`service nginx`**; reload deve ser feito com `service nginx reload`.

---

## 5. Alterações no repositório (resumo)

### 5.1 Documentos e scripts

| Arquivo | Alteração |
|---------|-----------|
| **`docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md`** | Seção 4.2.1 (BLOCO 3): aviso "COPIAR SOMENTE O BLOCO DE COMANDOS"; referência a `docs/bloco3-timeout-nginx.sh`; comando de reload trocado de `kill -HUP $(cat /var/run/nginx.pid)` para `sudo nginx -s reload 2>/dev/null \|\| sudo systemctl reload nginx 2>/dev/null \|\| sudo service nginx reload`; subseção "Se apenas o reload falhar" com os três comandos (nginx -s reload, systemctl, service). |
| **`docs/bloco3-timeout-nginx.sh`** | Criado; contém só os comandos do BLOCO 3; reload usa a cadeia nginx -s / systemctl / service. |

### 5.2 Inconsistências corrigidas na diretriz

| Local | Situação anterior | Correção aplicada |
|-------|-------------------|-------------------|
| **Seção 4.3 (Reload / Restart)** | Usava `sudo kill -HUP $(cat /var/run/nginx.pid)`. | Substituído por `sudo nginx -t && (sudo nginx -s reload 2>/dev/null \|\| sudo systemctl reload nginx 2>/dev/null \|\| sudo service nginx reload)`; adicionada nota "na srv817568 usar: sudo service nginx reload". |
| **Seção 4.5 (Rollback)** | Idem. | Mesmo padrão de reload; nota para srv817568 mantida. |

---

## 6. Estado atual (pós-auditoria)

### 6.1 VPS srv817568

| Item | Estado |
|------|--------|
| **Site do gateway** | `/etc/nginx/sites-available/whatsapp-multichannel` |
| **Timeouts no arquivo** | **120s** (proxy_connect, proxy_send, proxy_read) |
| **Backup** | Existente (`.bak.YYYYMMDD_HHMMSS` gerado pelo BLOCO 3) |
| **nginx -t** | OK |
| **Nginx em execução** | Config **recarregada** via `sudo service nginx reload` |
| **Gateway (PM2)** | wpp-ui (id 0), online; **não** reiniciado |
| **Comando de reload a usar** | `sudo service nginx reload` |

### 6.2 Repositório

| Item | Estado |
|------|--------|
| **Regra de Triangulação** | `docs/REGRA_TRIANGULACAO.md` + `.cursor/rules/regra-vps.mdc` |
| **Diretriz + Pacote VPS** | `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` (seção 4 com BLOCO 3 atualizado) |
| **Script BLOCO 3** | `docs/bloco3-timeout-nginx.sh` (reload com fallbacks) |
| **Spec gateway D+E** | Descritas na diretriz (seção 3); **não** implementadas no gateway |

### 6.3 Pendências

1. **Teste de aceite:** Rodar de novo o envio de áudio no Hub (4–10s) e verificar se deixa de dar 500 ou se o tempo até o erro muda.
2. **Se ainda falhar ~46s:** Tratar como timeout **interno** do gateway/Node (WPPConnect) e seguir a spec **E** (timeouts por etapa no gateway).
3. **Opcional:** Ajustar as seções 4.3 e 4.5 da diretriz para usar o reload que funciona na srv817568, evitando `kill -HUP` quando o pid file não for confiável.

---

## 7. Critérios de aceite (relembre)

- **Nginx:** timeouts 120s no `whatsapp-multichannel`; config válida; reload aplicado (`service nginx reload`).
- **Teste de áudio:** Envio de áudio 4–10s conclui em **menos de 15s** sem 500.
- **Se D+E forem implementados no gateway:** Erro no Hub traz `request_id`; logs do gateway mostram todas as etapas com esse ID; timeouts por etapa (convert / wppconnect) configurados.

---

## 8. Resumo executivo

| Fase | O que foi feito | Resultado |
|------|------------------|-----------|
| **BLOCO 1** | Pré-check PM2, nginx -t, sites-enabled | Gateway online; Nginx ok; site do gateway identificado. |
| **BLOCO 2** | Grep de timeouts em whatsapp-multichannel | Timeouts 60s nas linhas 36–38. |
| **BLOCO 3** | Backup, sed 60s→120s, nginx -t, reload | Backup e 120s aplicados; nginx -t ok; reload inicial falhou (pid file). |
| **Incidente** | Colagem do doc inteiro no terminal | Aviso na diretriz + `bloco3-timeout-nginx.sh` criado. |
| **Reload** | nginx -s reload; systemctl; service | **service nginx reload** aplicado com sucesso na srv817568. |

**Estado final:** Timeouts do proxy em **120s** e Nginx recarregado. Próximo passo é **testar o envio de áudio** no Hub e, se ainda houver timeout ~46s, focar na spec **E** no gateway.
