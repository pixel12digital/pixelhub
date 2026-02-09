# Cron: Healthcheck de Sessões WhatsApp

**Objetivo:** Evitar perda de mensagens quando sessões WhatsApp (pixel12digital, imobsites) desconectam. O script verifica status e dispara tentativa de reconexão (como o clique em "QR Code" na UI).

**Contexto:** Sessão pixel12digital desconectou entre 08/02 e 09/02; a mensagem da Adriana (5511984078606) foi perdida. O gateway-wrapper não tem auto-reconnect; chamar `GET /api/channels/{id}/qr` em sessão desconectada força o WPPConnect a tentar reconectar.

**Onde rodar comandos:**
- **HostMedia** = servidor onde o PixelHub está em produção (PHP, banco, cron)
- **Local** = sua máquina de desenvolvimento (se .env apontar para gateway e banco)
- **VPS** = gateway WPPConnect (SSH em wpp.pixel12digital.com.br)

---

## 1. Execução manual

**Onde:** HostMedia ou Local (precisa acessar banco e gateway)

```bash
# Verificar (não altera nada)
php scripts/healthcheck_whatsapp_sessions.php --dry-run

# Executar
php scripts/healthcheck_whatsapp_sessions.php
```

---

## 2. Cron no HostMedia (cPanel ou SSH)

**Onde:** HostMedia (cPanel ou SSH do servidor de produção)

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
2. **Canais desconectados (API):** Para cada canal com `status !== 'connected'`, chama `GET /api/channels/{id}/qr`.
3. **Canais "zombie":** A UI pode mostrar "Conectado" mesmo quando a sessão não recebe eventos de mensagem. O script verifica em `webhook_raw_logs` a última mensagem recebida por canal; se não houver mensagem há 4h (ou `--silent-hours`), força getQr().
4. O WPPConnect tenta reconectar; se o token ainda for válido, reconecta sem exibir QR.
5. Loga em `logs/healthcheck-sessions.log` apenas quando há desconexões ou zombies (evita poluir o log).

**Opção `--silent-hours=N`:** considerar zombie se não houver mensagem há N horas (padrão: 4).

---

## 4. Arquivos

| Arquivo | Descrição |
|---------|-----------|
| `scripts/healthcheck_whatsapp_sessions.php` | Script principal |
| `logs/healthcheck-sessions.log` | Log (criado pelo cron) |

---

## 5. "Nenhum canal encontrado ou resposta inválida"

Se o script retornar essa mensagem, rode com `--verbose` para ver o erro:

```bash
# [HostMedia]
php scripts/healthcheck_whatsapp_sessions.php --verbose
```

**Causas comuns:**

| Causa | Verificar |
|-------|-----------|
| URL sem porta 8443 | `.env` deve ter `WPP_GATEWAY_BASE_URL=https://wpp.pixel12digital.com.br:8443` |
| Secret incorreto | `WPP_GATEWAY_SECRET` igual ao "Gateway Secret" da UI (wpp.pixel12digital.com.br:8443/ui/sessoes) |
| Gateway inacessível | HostMedia consegue acessar o gateway? Teste: `curl -s -o /dev/null -w "%{http_code}" https://wpp.pixel12digital.com.br:8443/api/channels` |

**Teste manual (HostMedia):**

```bash
# Obter o secret da UI (wpp.pixel12digital.com.br:8443 → Sessão pixel12digital → Gateway Secret)
SECRET="seu_secret_aqui"
curl -s -H "X-Gateway-Secret: $SECRET" "https://wpp.pixel12digital.com.br:8443/api/channels"
# Se retornar JSON com {"success":true,"channels":[...]}, está OK.
```

---

## 6. Critério de aceite

- Cron configurado e rodando a cada 15 min.
- Quando sessão desconecta, em até 15 min o script dispara getQr e a sessão tenta reconectar.
- Log em `logs/healthcheck-sessions.log` apenas quando há canais desconectados.
