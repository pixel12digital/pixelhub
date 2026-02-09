# Investigação: Renato (5381642320) - mensagens não aparecem no Inbox

**Data:** 2026-02-09  
**Cenário:**  
- Mensagem enviada via WhatsApp Web (pixel12digital) para Renato 5381642320 — não aparece no Inbox  
- Áudio recebido de Renato para pixel12digital — não aparece no Inbox  

---

## 1. Resultado do diagnóstico

### Scripts executados

**Onde:** HostMedia ou Local (precisa acessar o banco do PixelHub)

```bash
php database/diagnostico-renato-81642320-inbox.php
php database/diagnostico-webhook-09-02.php
```

### Achados

| Verificação | Resultado |
|-------------|-----------|
| Eventos em `communication_events` para Renato | ✓ 30 eventos até **07/02 14:19** |
| Eventos em **09/02** para Renato | ❌ **Nenhum** |
| Conversa em `conversations` | ✓ id=109, contact=555381642320, channel=pixel12digital |
| `webhook_raw_logs` – eventos de mensagem em 09/02 | ❌ **Nenhum** (message, onmessage, onselfmessage, message.sent) |
| `webhook_raw_logs` – eventos pixel12digital em 09/02 | ❌ **Nenhum** |
| Último evento de mensagem recebido (qualquer sessão) | **07/02 22:13:58** |
| Último message para pixel12digital | **07/02 22:13:58** |

---

## 2. Causa raiz

O gateway **não está enviando** eventos de mensagem (`onmessage`, `message`, `message.sent`) ao webhook desde **07/02 22:13**.

**Incoerência conhecida:** A UI da VPS (wpp.pixel12digital.com.br:8443) mostra "Conectado" mesmo quando o **dispositivo do usuário está desconectado** — o status da UI não reflete o estado real. O healthcheck detecta isso via `webhook_raw_logs` e força reconexão.

**Causa confirmada (09/02):** Usuário relatou "Sessão UI aparece conectado e meu dispositivo está desconectado". Enquanto o celular está offline/desconectado do WhatsApp, o WPPConnect pode manter status "Conectado" na API, mas nenhum evento de mensagem chega. **Solução:** Reconectar o celular (internet + WhatsApp) e, na UI, usar "Desconectar" → "QR Code" para reemparelhar.

- **09/02:** 402 eventos `connection.update` chegaram, mas **zero** eventos de mensagem.
- A sessão pixel12digital provavelmente está **desconectada** (mesmo padrão do caso Adriana 5511984078606).
- Quando desconectada:
  - Mensagens enviadas via WhatsApp Web: passam pelo cliente WhatsApp Web, mas o WPPConnect não recebe `onselfmessage`.
  - Mensagens recebidas de Renato: o WPPConnect não recebe `onmessage`.

---

## 3. Solução

O **healthcheck de sessões** (`scripts/healthcheck_whatsapp_sessions.php`) trata esse cenário:

- Cron: a cada 15 minutos  
- **Canais desconectados (API):** chama `getQr()` quando `status !== 'connected'`.
- **Canais "zombie":** A UI pode mostrar "Conectado" mesmo quando a sessão não recebe eventos. O script verifica em `webhook_raw_logs` a última mensagem; se não houver mensagem há 4h, força `getQr()` mesmo com status "connected".

**Ação imediata – forçar reconexão agora:**

**Onde:** HostMedia ou Local (precisa acessar banco e gateway)

```bash
php scripts/healthcheck_whatsapp_sessions.php
```

Se a sessão estiver desconectada, o script dispara a tentativa de reconexão (como o clique em "QR Code" na UI).

**Verificar:**

1. **Cron no HostMedia:** o crontab está configurado?  
   **Onde:** HostMedia (cPanel → Cron Jobs ou `crontab -e` via SSH)  
   ```bash
   */15 * * * * cd /path/to/pixelhub && php scripts/healthcheck_whatsapp_sessions.php >> /path/to/logs/healthcheck.log 2>&1
   ```

2. **Estado da sessão na VPS:** usar o bloco de comandos abaixo para o Charles.

---

## 4. Bloco VPS para o Charles

**Objetivo:** conferir estado da sessão pixel12digital e últimos eventos de mensagem.

**Onde rodar:** SSH da VPS (wpp.pixel12digital.com.br)

```bash
echo "=== 1) gateway-wrapper: onmessage para pixel12digital (últimas 72h) ==="
docker logs gateway-wrapper --since 72h 2>&1 | grep -i "onmessage" | grep -i "pixel12digital" | tail -30

echo ""
echo "=== 2) gateway-wrapper: alguma linha contendo 81642320 ou 5381642320 (72h) ==="
docker logs gateway-wrapper --since 72h 2>&1 | grep -E "81642320|5381642320" | tail -20

echo ""
echo "=== 3) Tipos de evento recebidos em 09/02 (contagem) ==="
docker logs gateway-wrapper 2>&1 | grep -E "2026-02-09" | grep "Received webhook" | grep -oE '"eventType":"[^"]+"' | sort | uniq -c | sort -rn
```

**Retornar:** saída completa dos comandos 1, 2 e 3.

---

## 5. Arquivos de diagnóstico

- `database/diagnostico-renato-81642320-inbox.php` – eventos e conversas para Renato  
- `database/diagnostico-webhook-09-02.php` – eventos de mensagem em `webhook_raw_logs`  

---

## 6. Referências

- `docs/INVESTIGACAO_INBOX_5511984078606_PIXEL12DIGITAL.md` – caso Adriana (mesma causa)  
- `docs/BLOCO_VPS_SESSAO_PIXEL12_E_MSG_5511984078606.md` – blocos VPS anteriores  
- `docs/CRON_HEALTHCHECK_SESSOES_WHATSAPP.md` – configuração do cron  
