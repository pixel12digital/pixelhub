# Cron: Healthcheck de Sessões WhatsApp

**Objetivo:** Evitar perda de mensagens quando sessões WhatsApp (pixel12digital, imobsites) desconectam. O script verifica status e dispara tentativa de reconexão (como o clique em "QR Code" na UI).

**Contexto:** Sessão pixel12digital desconectou entre 08/02 e 09/02; a mensagem da Adriana (5511984078606) foi perdida. O gateway-wrapper não tem auto-reconnect; chamar `GET /api/channels/{id}/qr` em sessão desconectada força o WPPConnect a tentar reconectar.

---

## 1. Execução manual

```bash
# Verificar (não altera nada)
php scripts/healthcheck_whatsapp_sessions.php --dry-run

# Executar
php scripts/healthcheck_whatsapp_sessions.php
```

---

## 2. Cron no HostMedia (cPanel ou SSH)

**Frequência sugerida:** A cada 15 minutos (evita sobrecarga e detecta desconexões em até 15 min).

**cPanel → Cron Jobs:**

```
*/15 * * * * cd /home/USUARIO/public_html/painel.pixel12digital && php scripts/healthcheck_whatsapp_sessions.php >> logs/healthcheck-sessions.log 2>&1
```

Substitua `USUARIO` pelo usuário do hosting (ex: `pixel12digital`). O caminho pode ser `hub.pixel12digital.com.br` ou equivalente.

**SSH (crontab -e):**

```bash
*/15 * * * * cd /home/pixel12digital/hub.pixel12digital.com.br && php scripts/healthcheck_whatsapp_sessions.php >> logs/healthcheck-sessions.log 2>&1
```

---

## 3. O que o script faz

1. Chama `GET /api/channels` no gateway.
2. Para cada canal com `status !== 'connected'`, chama `GET /api/channels/{id}/qr`.
3. O WPPConnect tenta reconectar; se o token ainda for válido, reconecta sem exibir QR.
4. Loga em `logs/healthcheck-sessions.log` apenas quando há desconexões (evita poluir o log).

---

## 4. Arquivos

| Arquivo | Descrição |
|---------|-----------|
| `scripts/healthcheck_whatsapp_sessions.php` | Script principal |
| `logs/healthcheck-sessions.log` | Log (criado pelo cron) |

---

## 5. Critério de aceite

- Cron configurado e rodando a cada 15 min.
- Quando sessão desconecta, em até 15 min o script dispara getQr e a sessão tenta reconectar.
- Log em `logs/healthcheck-sessions.log` apenas quando há canais desconectados.
