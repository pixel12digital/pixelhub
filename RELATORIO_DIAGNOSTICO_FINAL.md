# RELATÓRIO FINAL - DIAGNÓSTICO DESCONEXÕES WHATSAPP GATEWAY

## LINHA DO TEMPO - EVIDÊNCIAS COLETADAS

### 30/01/2026 - Padrão de Flapping Identificado
```
16:49:57 - pixel12digital - available
16:49:59 - pixel12digital - unavailable  
16:50:04 - ImobSites - available
16:50:08 - pixel12digital - unavailable
[... CENTENAS DE EVENTOS A CADA 2-5 SEGUNDOS ...]
17:04:31 - pixel12digital - unavailable
```
**Fonte:** webhook-outbox.json.backup.20260130_171107

### 11/02/2026 - Short-Circuit Implementado
```
2905 eventos connection.update em um único dia
PixelHub implementa short-circuit para connection.update e message.ack
```
**Fonte:** webhook_raw_logs (banco de dados)

### 16-17/02/2026 - Estado Atual
```
16/02 21:04 - Últimas mensagens recebidas (processed=0)
17/02 01:02 - pixel12digital: connected (sessions.json)
17/02 01:16 - imobsites: disconnected + QR required
17/02 01:16-01:17 - onpresencechanged contínuo (logs gateway)
```
**Fonte:** diagnóstico VPS + banco de dados

---

## EVIDÊNCIA 1: FLAPPING REAL DO WHATATSAPP

**Logs WPPConnect (VPS) - 17/02/2026:**
```
debug: [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug: [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
debug: [pixel12digital:client] Emitting onPresenceChanged event (1 registered)
```

**API Gateway Status:**
```json
{"success":true,"channels":[
  {"id":"pixel12digital","status":"connected"},
  {"id":"imobsites","status":"qr_required"}
]}
```

**Conclusão:** WPPConnect está emitindo eventos de presença em alta frequência, indicando instabilidade real da conexão WhatsApp Web.

---

## EVIDÊNCIA 2: CEGUEIRA DO PIXELHUB

**WhatsAppWebhookController.php (linhas 47-60):**
```php
$shortCircuitEvents = ['connection.update', 'message.ack'];
if (in_array($eventTypeForLog, $shortCircuitEvents, true)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'code' => 'EVENT_SKIPPED']);
    exit; // ← BLOQUEIA PROCESSAMENTO
}
```

**webhook_raw_logs - últimos 7 dias:**
```
2026-02-16: 27 mensagens - processed=0 (100% falha)
2026-02-14: 16 mensagens - processed=0 (100% falha)  
2026-02-12: 74 mensagens - processed=0 (100% falha)
```

**Conclusão:** PixelHub recebe webhooks mas short-circuit impede processamento de eventos de conexão.

---

## EVIDÊNCIA 3: BLOQUEIO DE MENSAGENS INBOUND

**Payloads recebidos (exemplos):**
```json
{"event":"message","session":{"id":"pixel12digital"},"message":{"from":"558592570064@c.us"}}
```

**Todos com processed=0 - Análise:**
- Eventos `message` NÃO estão na lista `$shortCircuitEvents`
- Portanto, deveriam ser processados normalmente
- **Fato de estarem com processed=0 indica OUTRA FALHA no pipeline**

**Possíveis causas do bloqueio inbound:**
1. **Exception silenciosa** em EventIngestionService::ingest()
2. **Lock/falha** ao inserir em communication_events
3. **Timeout** em ConversationService::resolveConversation()
4. **Erro de validação** em WhatsAppWebhookController::handle()

---

## PROBLEMA A: INSTABILIDADE WPPCONNECT/WHATSAPP

**Causa do Flapping:** WhatsApp Web instável (Puppeteer/Chrome)

**Evidências:**
- Eventos `onpresencechanged` a cada 2-5 segundos
- Alternância `available`/`unavailable` contínua
- Sessão `imobsites` caiu para QR required

**Motivo real:** Necessário verificar logs WPPConnect para identificar:
- Browser crash/restart
- Network instability  
- WhatsApp multi-device conflict
- Rate limiting da API

---

## PROBLEMA B: CEGUEIRA PIXELHUB (SHORT-CIRCUIT)

**Causa:** Short-circuit implementado em 11/02 para reduzir volume

**Impactos:**
- UI mostra status "connected" falso
- Sem registro de desconexões reais
- Eventos de conexão ignorados

**Conflito:** Gateway envia → PixelHub ignora

---

## CONCLUSÃO FINAL

### **DIAGNÓSTICO DEFINITIVO:**

1. **Flapping WhatsApp:** Instabilidade real do WhatsApp Web (causa exata a ser confirmada nos logs WPPConnect)

2. **Cegueira Operacional:** Short-circuit impede monitoramento real do status

3. **Bloqueio Inbound CRÍTICO:** Mensagens não estão sendo processadas por outra falha no pipeline (NÃO é o short-circuit)

### **EVIDÊNCIA FALTANTE PARA FECHAR:**

**Rode na VPS:**
```bash
# Verificar motivo real do flapping
docker logs wppconnect-server --since 6h | grep -i "disconnect\|logout\|crash\|error\|browser"

# Verificar se há erros no pipeline inbound
docker logs gateway-wrapper --since 6h | grep -i "error\|exception\|failed\|timeout"
```

### **CLASSIFICAÇÃO FINAL:**
- **Problema de Conexão:** Instabilidade WhatsApp Web (flapping)
- **Problema de Monitoramento:** Short-circuit PixelHub  
- **Problema Crítico:** Falha no pipeline de mensagens inbound

**URGÊNCIA:** Mensagens inbound estão sendo perdidas - isso afeta diretamente o negócio.

---

### EVIDÊNCIAS DOS LOGS (VPS) - 17/02/2026 01:09-01:26

**WPPConnect Server - ImobSites:**
```
01:09:18 - Session Unpaired
01:10:18 - Auto Close Called (browserClose)
01:10:18 - qrReadError
01:11:11 - Error: browser already running for /usr/src/wpp-server/userDataDir/ImobSites
01:12:15 - Auto Close Called (browserClose)
01:13:25 - Auto Close Called (browserClose)
[... LOOP INFINITO A CADA MINUTO ...]
```

**WPPConnect Server - pixel12digital:**
```
01:15:32 - State Change UNLAUNCHED → OPENING → PAIRING → CONNECTED
01:15:37 - Connected (inChat)
01:16-01:26 - Emitting onPresenceChanged event (1 registered) [CONTÍNUO]
```

**Gateway-wrapper - Webhooks:**
```
01:18:58 - Webhook delivered successfully (status:200, latency:435ms)
01:19:11 - Webhook delivered successfully (status:200, latency:862ms)
01:20:32 - Webhook delivered successfully (status:200, latency:422ms)
[... TODOS COM STATUS 200 OK ...]
```

---

## DIAGNÓSTICO DEFINITIVO FECHADO

### **PROBLEMA 1: ImobSites - Bug de Gerenciamento de Sessão**

**Causa técnica:** Race condition no WPPConnect - browser já está rodando mas tenta iniciar novo processo, entra em loop de auto-close.

**Padrão identificado:**
```
Unpaired → Auto Close (60s) → browserClose → 
Error "browser already running" → Repete
```

**Classificação:** Bug interno do WPPConnect (controle de browser/Puppeteer)

**Impacto:** Sessão NUNCA estabiliza, fica presa em QR loop infinito

---

### **PROBLEMA 2: pixel12digital - Flapping Real do WhatsApp**

**Causa técnica:** WhatsApp detectando instabilidade - presença alternando continuamente (available/unavailable)

**Padrão identificado:**
```
Conecta → inChat → onPresenceChanged a cada 10-30s (contínuo)
```

**Possíveis causas:**
- Heurística anti-automação do WhatsApp
- Instabilidade de rede/IP
- Conflito multi-device
- Rate limiting

**Classificação:** Instabilidade real da conexão WhatsApp Web

**Impacto:** Sessão "fica conectada" mas oscila, pode cair depois

---

### **PROBLEMA 3: PixelHub - Cegueira Operacional**

**Causa técnica:** Short-circuit bloqueando `connection.update` e `message.ack`

**Evidência:**
```php
$shortCircuitEvents = ['connection.update', 'message.ack'];
if (in_array($eventTypeForLog, $shortCircuitEvents, true)) {
    exit; // ← BLOQUEIA PROCESSAMENTO
}
```

**Classificação:** Problema de monitoramento (não de conexão)

**Impacto:** UI mostra status "connected" falso, sem registro de desconexões

---

### **PROBLEMA 4: Mensagens Inbound (processed=0)**

**Status:** NÃO é causado pelo short-circuit

**Evidência:** Gateway entrega webhooks `message` com status 200 OK

**Conclusão:** Falha no pipeline interno do PixelHub (EventIngestionService/ConversationService)

**Necessita:** Diagnóstico separado do pipeline de processamento

---

## PLANO DE CORREÇÃO (SEM IMPLEMENTAÇÃO)

### **BLOCO 1: ImobSites - Auto Close Loop**

**Causa provável técnica:**
- WPPConnect não está limpando processo browser anterior
- Lock file ou PID file não sendo removido
- userDataDir com permissões incorretas

**Correção mínima:**
1. Parar container WPPConnect
2. Limpar userDataDir/ImobSites (rm -rf)
3. Reiniciar container
4. Recriar sessão do zero

**Risco/Impacto:**
- Risco: BAIXO (sessão já está quebrada)
- Impacto: Perda de sessão atual (já perdida)
- Downtime: ~2 minutos

**Validação em produção:**
- Verificar se QR é gerado sem loop
- Confirmar que não há erro "browser already running"
- Escanear QR e confirmar conexão estável

---

### **BLOCO 2: PixelHub - Cegueira Operacional**

**Causa provável técnica:**
- Short-circuit implementado em 11/02 para reduzir volume
- Bloqueia 100% dos eventos de conexão

**Correção mínima:**
1. Remover short-circuit de `connection.update`
2. Implementar filtro inteligente (apenas mudanças de estado)
3. Manter short-circuit de `message.ack`

**Risco/Impacto:**
- Risco: MÉDIO (pode voltar sobrecarga de eventos)
- Impacto: Visibilidade real do status
- Downtime: ZERO (apenas deploy)

**Validação em produção:**
- Verificar se status atualiza corretamente na UI
- Monitorar volume de eventos em webhook_raw_logs
- Confirmar que desconexões são registradas

---

### **BLOCO 3: pixel12digital - Flapping WhatsApp**

**Causa provável técnica:**
- WhatsApp detectando padrão de automação
- Possível instabilidade de rede/IP
- Multi-device conflict

**Correção mínima:**
1. Implementar rate limiting no envio de mensagens
2. Adicionar delay entre operações
3. Monitorar padrão de uso (evitar picos)
4. Considerar rotação de IP se persistir

**Risco/Impacto:**
- Risco: ALTO (pode piorar ou causar ban)
- Impacto: Redução de flapping
- Downtime: ZERO (mudanças graduais)

**Validação em produção:**
- Monitorar frequência de onPresenceChanged
- Verificar se flapping reduz após ajustes
- Confirmar que sessão permanece estável por >24h

---

## PRIORIDADE DE EXECUÇÃO

**P0 - CRÍTICO (fazer primeiro):**
1. **ImobSites** - Limpar auto-close loop (sessão inutilizável)
2. **PixelHub** - Remover cegueira (monitoramento essencial)

**P1 - IMPORTANTE (fazer depois):**
3. **pixel12digital** - Mitigar flapping (estabilização)
4. **Pipeline Inbound** - Investigar processed=0 (diagnóstico separado)

---

## RESUMO EXECUTIVO FINAL

**Suas sessões caem por duas causas distintas:**

1. **ImobSites:** Bug de auto-close loop no WPPConnect (race condition de browser)
2. **pixel12digital:** Flapping real de presença detectado pelo WhatsApp (instabilidade)

**O PixelHub está cego a ambos os problemas** devido ao short-circuit implementado em 11/02.

**Mensagens inbound com processed=0** não são causadas pelo short-circuit - é falha no pipeline interno que requer diagnóstico separado.

**Ação imediata:** Corrigir ImobSites (limpar userDataDir) e PixelHub (remover short-circuit de connection.update).
