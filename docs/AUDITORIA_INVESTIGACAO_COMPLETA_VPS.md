# Auditoria: Investigação Completa da VPS – Timeout de Áudio WhatsApp

**Data da auditoria:** 27/01/2026  
**Escopo:** Investigação completa na VPS srv817568 para localizar a origem do 504 Gateway Time-out (~46–76s) no envio de áudio WhatsApp; mapeamento dos vhosts Nginx, identificação do vhost ativo em 8443 e conclusão sobre a causa do timeout.

**Referências:**  
`docs/REGRA_TRIANGULACAO.md`, `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md`, `.cursor/rules/regra-vps.mdc`

---

## 1. Contexto e objetivo

| Item | Descrição |
|------|------------|
| **Problema** | Envio de áudio de 4–10s retorna **504 Gateway Time-out** em ~46–76s. |
| **Hipótese inicial** | Timeout do proxy Nginx (60s) ou do gateway/Node/WPPConnect. |
| **Objetivo** | Identificar **onde** o timeout ocorre: Nginx (VPS), HostMedia (cURL/PHP) ou backend (172.19.0.1:3000). |
| **VPS** | **srv817568** (IP público: 212.85.11.238). HostMedia acessa o gateway em `https://wpp.pixel12digital.com.br:8443/…`. |
| **Regra** | **Regra de Triangulação:** apenas o Cursor pede comandos ao Charles na VPS; um bloco por vez; outputs exatos a retornar. |

---

## 2. Mapeamento da VPS – Blocos executados

Todos os blocos foram executados pelo Charles na VPS **srv817568**, com saída copiada e consolidada abaixo.

---

### 2.1 BLOCO “Origem 443/8443/9443” – Onde está o listen 8443?

**Comandos:**

```bash
echo "=== 1) Origem do config 443/8443/9443 no Nginx ==="
nginx -T 2>/dev/null | grep -E "configuration file|listen |server_name " | head -80
```

**Resultados relevantes:**

| Origem no `nginx -T` | listen | server_name |
|----------------------|--------|-------------|
| **`/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`** | **8443** ssl http2 default_server | wpp.pixel12digital.com.br www.wpp.pixel12digital.com.br |
| `/etc/nginx/sites-enabled/whatsapp-multichannel` | 8081 | _ |
| `/etc/nginx/sites-enabled/wpp.pixel12digital.com.br` | (não listado no trecho) | — |

**Conclusão BLOCO 1:** O vhost que **efetivamente** escuta em **8443** e atende `wpp.pixel12digital.com.br` vem de **`/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`**, e **não** de `sites-available` / `sites-enabled`.

---

### 2.2 BLOCO “Backup + 120s em sites-available”

**Objetivo:** Fazer backup e inserir `proxy_connect_timeout 120s`, `proxy_send_timeout 120s`, `proxy_read_timeout 120s` nos arquivos **`wpp_ssl_8443`** e **`wpp_443_proxy_to_9443`** em `sites-available`.

**Comandos (resumo):**

- Backup com timestamp de `wpp_ssl_8443` e `wpp_443_proxy_to_9443`.
- `sed` para inserir os três timeouts 120s após cada `proxy_pass` para `127.0.0.1:3000` e `127.0.0.1:3100`.
- `nginx -t` e `service nginx reload`.

**Resultados:**

| Etapa | Resultado |
|-------|-----------|
| **Backup** | Criados `wpp_ssl_8443.bak.20260127_141830` e `wpp_443_proxy_to_9443.bak.20260127_141830`. |
| **Alteração** | Ambos os arquivos alterados; timeouts 120s inseridos após cada `proxy_pass` (3000 e 3100). |
| **nginx -t** | `syntax is ok`, `test is successful`. |
| **Reload** | `service nginx reload` executado (saída vazia = sucesso). |
| **Conferência** | `grep -n` confirmou `proxy_connect_timeout 120s`, `proxy_send_timeout 120s`, `proxy_read_timeout 120s` em todos os blocos com `proxy_pass` nesses dois arquivos. |

**Descoberta crítica:** Os arquivos editados estão em **`/etc/nginx/sites-available/`**. O `nginx -T` já tinha mostrado que o **listen 8443** vem de **`conf.d/00-wpp.pixel12digital.com.br.conf`**. Ou seja, as alterações em `sites-available` **podem não** ser carregadas para o vhost que atende a porta 8443 usada pelo HostMedia.

---

### 2.3 BLOCO B' – Conteúdo do vhost ativo em 8443 (conf.d)

**Objetivo:** Confirmar qual arquivo define o vhost 8443 e se ele já tem (ou não) `proxy_*_timeout` adequados.

**Comandos:**

```bash
echo "=== B'1) Conteúdo do vhost 8443 (conf.d) ==="
cat /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

echo ""
echo "=== B'2) Outros conf em conf.d (wpp/8443) ==="
ls -la /etc/nginx/conf.d/
grep -l "8443\|wpp\.pixel12\|9443" /etc/nginx/conf.d/*.conf 2>/dev/null || true

echo ""
echo "=== B'3) Inclui sites-available no nginx? ==="
grep -E "include|sites-available|conf\.d" /etc/nginx/nginx.conf
```

**Resultados B'1 – Conteúdo de `00-wpp.pixel12digital.com.br.conf`:**

- **listen:** 8443 ssl http2 default_server (IPv4 e IPv6).
- **server_name:** wpp.pixel12digital.com.br www.wpp.pixel12digital.com.br.
- **SSL:** Let’s Encrypt em `/etc/letsencrypt/live/wpp.pixel12digital.com.br/`.
- **No nível do server (já presentes):**
  ```nginx
  proxy_connect_timeout 7d;
  proxy_send_timeout 7d;
  proxy_read_timeout 7d;
  ```
- **location /**  
  - `proxy_pass http://172.19.0.1:3000;`  
  - Headers (Host, X-Real-IP, X-Forwarded-*, etc.), `proxy_http_version 1.1`, `proxy_buffering off`, `proxy_cache off`.  
  - **Sem** `proxy_*_timeout` no `location` → valem os **7d** do server.
- **Logs:**  
  - `access_log /var/log/nginx/wpp.pixel12digital.com.br_access.log`  
  - `error_log /var/log/nginx/wpp.pixel12digital.com.br_error.log`

**Resultados B'2:** O único `.conf` em `conf.d` que trata 8443/wpp é `00-wpp.pixel12digital.com.br.conf`. Há vários `.backup_*` no mesmo diretório.

**Resultados B'3 – includes no `nginx.conf`:**

- `include /etc/nginx/modules-enabled/*.conf;`
- `include /etc/nginx/mime.types;`
- **`include /etc/nginx/conf.d/*.conf;`** ← carrega `00-wpp.pixel12digital.com.br.conf`
- **`include /etc/nginx/sites-enabled/*;`** ← carrega sites-enabled (não os arquivos em sites-available que foram editados, a menos que haja symlinks)

**Conclusão BLOCO B':** O vhost **ativo** em 8443 é **`/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf`**. Esse arquivo **já** define `proxy_connect_timeout 7d`, `proxy_send_timeout 7d`, `proxy_read_timeout 7d` no **server**. Portanto o Nginx **não** é a causa do 504 em ~46–76s.

---

## 3. Resumo da arquitetura Nginx na VPS

| Item | Valor |
|------|--------|
| **Vhost que atende 8443** | `/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf` |
| **Backend do `location /`** | `http://172.19.0.1:3000` (rede Docker) |
| **Timeouts no vhost 8443** | **7d** (server level) → Nginx não interrompe em 60s |
| **Arquivos em sites-available editados** | `wpp_ssl_8443`, `wpp_443_proxy_to_9443` – **não** são a configuração ativa para 8443 |
| **Includes nginx.conf** | `conf.d/*.conf` e `sites-enabled/*` |
| **Logs do vhost 8443** | `wpp.pixel12digital.com.br_access.log`, `wpp.pixel12digital.com.br_error.log` |

---

## 4. Conclusões da auditoria

### 4.1 Onde está (e onde não está) o timeout de ~60s

| Camada | Conclusão |
|--------|-----------|
| **Nginx (vhost 8443)** | **Descartada.** O vhost ativo tem `proxy_*_timeout 7d`. O corte em ~46–76s **não** vem do Nginx. |
| **Arquivos sites-available** | As alterações em `wpp_ssl_8443` e `wpp_443_proxy_to_9443` **não** afetam o tráfego em 8443, pois o vhost ativo é o de `conf.d/00-wpp.pixel12digital.com.br.conf`. |
| **HostMedia (cURL/PHP)** | **Candidato.** Verificar `CURLOPT_TIMEOUT` / `CURLOPT_CONNECTTIMEOUT` (e qualquer outro timeout) em `WhatsAppGatewayClient` ou onde a chamada ao gateway é feita. Valores em 45–60s explicam o 504 nesse intervalo. |
| **Backend 172.19.0.1:3000** | **Candidato.** WPPConnect/Node pode estar fechando a conexão ou devolvendo erro após um timeout interno. Requer logs no processo que escuta na 3000 (e, se aplicável, no container), com timestamp e, se existir, `X-Request-Id` / `request_id`. |

### 4.2 Conclusão principal

**A investigação completa na VPS mostra que o Nginx não é a causa do 504 Gateway Time-out em ~46–76s.** O vhost que atende a API em 8443 já possui `proxy_connect_timeout 7d`, `proxy_send_timeout 7d` e `proxy_read_timeout 7d`. O timeout deve ser tratado em:

1. **Código local (HostMedia):** timeouts de cURL/PHP na chamada ao gateway.  
2. **Gateway (processo em 172.19.0.1:3000):** timeouts internos e logs por etapa (incluindo Correlation ID / `X-Request-Id`), conforme `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` (seções 3.D/E).

---

## 5. Próximos passos recomendados

| # | Ação | Onde |
|---|------|------|
| 1 | Verificar e, se necessário, aumentar timeout de cURL/PHP na chamada ao gateway (ex.: 120s ou 180s para áudio). | Repositório: `WhatsAppGatewayClient` ou equivalente |
| 2 | Re-testar envio de áudio 4–10s e observar: tempo até sucesso/erro, body do erro (`request_id`, `error_code`, `origin`, `reason`). | HostMedia / Hub |
| 3 | No gateway (VPS), implementar leitura de `X-Request-Id`, logs por etapa com `[req=<id>]` e respostas de erro com `request_id` e `reason` (ex.: TIMEOUT_STAGE_CONVERT / TIMEOUT_STAGE_WPPCONNECT). | Spec em `docs/DIRETRIZ_EXECUCAO_E_PACOTE_VPS.md` (seções 3.D e 3.E) |
| 4 | Opcional: usar `wpp.pixel12digital.com.br_access.log` e `wpp.pixel12digital.com.br_error.log` para cruzar `request_id` / timestamp do Hub com o que chegou ao Nginx. | VPS (Charles), apenas leitura de logs |

---

## 6. Referência rápida – Comandos úteis na VPS (somente leitura/diagnóstico)

Para futuras checagens, sem alterar configuração:

```bash
# Qual vhost atende 8443
nginx -T 2>/dev/null | grep -E "configuration file|listen |server_name " | head -80

# Conteúdo do vhost ativo 8443
cat /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# Timeouts no vhost ativo
grep -n "proxy_.*_timeout" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# Últimas linhas dos logs do vhost 8443
tail -50 /var/log/nginx/wpp.pixel12digital.com.br_access.log
tail -50 /var/log/nginx/wpp.pixel12digital.com.br_error.log
```

---

## 7. Resumo executivo

| Fase | O que foi feito | Resultado |
|------|------------------|-----------|
| **Origem 443/8443/9443** | `nginx -T \| grep` | Vhost 8443 = **conf.d/00-wpp.pixel12digital.com.br.conf**. |
| **Backup + 120s em sites-available** | Backup, sed, nginx -t, reload | Alterações em **wpp_ssl_8443** e **wpp_443_proxy_to_9443**; esses arquivos **não** são o vhost ativo em 8443. |
| **BLOCO B'** | `cat` do conf.d, `ls`/`grep` conf.d, `grep` nginx.conf | Vhost ativo em 8443 já tem **proxy_*_timeout 7d** no server. |
| **Conclusão** | — | **Nginx descartado** como causa do 504 em ~60s. Foco em **HostMedia (cURL)** e **backend 172.19.0.1:3000**. |

**Estado final da auditoria:** Investigação da VPS concluída. Não é necessária nova alteração no Nginx para resolver o 504 em ~46–76s; as próximas ações são em código (HostMedia) e, se for o caso, no gateway (logs e timeouts por etapa).
