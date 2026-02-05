# Diagnóstico Local — Áudio e Mídia (verificado no código)

**Data:** 05/02/2026  
**Objetivo:** Consolidar o que o código local faz e o que depende do HostMedia/Gateway.

---

## 1. Fluxo de envio de áudio (outbound)

### 1.1 Navegador → formato gravado

| Arquivo | Linha | Comportamento |
|---------|-------|---------------|
| `views/layout/main.php` | 2669 | `MediaRecorder.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : ''` |
| `views/communication_hub/index.php` | 3968-3971 | `candidates = ['audio/ogg;codecs=opus', 'audio/ogg']` → usa o primeiro suportado |

**Resultado por navegador:**
- **Firefox:** suporta OGG/Opus → grava em OGG → envia OGG direto (sem conversão)
- **Chrome/Edge:** não suporta OGG no MediaRecorder → usa default = **WebM/Opus** → precisa conversão

### 1.2 Backend — conversão WebM→OGG

| Arquivo | Método | O que faz |
|---------|--------|-----------|
| `CommunicationHubController.php` | `convertWebMToOggBase64()` | Usa `exec('ffmpeg -y -i input.webm -c:a libopus -b:a 32k -ar 16000 output.ogg')` |
| | | Verifica `disable_functions` → se `exec` bloqueado: retorna `EXEC_DISABLED` |
| | | Se ffmpeg falha: retorna `FFMPEG_FAILED`, `FFMPEG_OUTPUT_INVALID`, `OGG_READ_FAILED` |
| | | **Fallback:** se conversão falha → envia WebM + `audio_mime: "audio/webm"` ao gateway |

### 1.3 Fallback para gateway (quando HostMedia falha)

```php
// CommunicationHubController.php ~1620
$audioOptions = ['audio_mime' => 'audio/webm', 'is_voice' => true];
// $b64 continua o WebM original
```

O gateway **deveria** converter, mas o diagnóstico VPS mostrou: **ffmpeg existe, código de conversão NÃO**.

---

## 2. Diagnóstico do ambiente HostMedia

| Recurso | Arquivo | Como verificar |
|---------|---------|----------------|
| **diagnostic-audio-env.php** | `public/diagnostic-audio-env.php` | `GET https://hub.pixel12digital.com.br/diagnostic-audio-env.php` |
| | | Retorna: `exec_available`, `ffmpeg_in_path`, `recommendation` (`hostmidia_convert` ou `gateway_convert`) |

**O que o script verifica:**
- `disable_functions` contém `exec` ou `shell_exec`?
- `ffmpeg -version` retorna 0?

---

## 3. Resumo da cadeia de falhas

```
Chrome grava WebM
    → HostMedia recebe WebM
    → convertWebMToOggBase64() tenta ffmpeg
        → Se exec bloqueado OU ffmpeg ausente: EXEC_DISABLED / ffmpeg_in_path=false
        → Se ffmpeg falha: FFMPEG_FAILED
    → Fallback: envia WebM + audio_mime ao gateway
    → Gateway tem ffmpeg mas NÃO tem código que use
    → WebM repassado ao WPPConnect sem conversão
    → WhatsApp pode aceitar mas cliente vê "Este áudio não está mais disponível"
```

---

## 4. O que fazer (ordem de prioridade)

| # | Ação | Onde | Impacto |
|---|------|------|---------|
| 1 | **Rodar diagnostic-audio-env.php em produção** | HostMedia | Saber se HostMedia pode converter |
| 2 | **Implementar conversão no gateway** | VPS (gateway-wrapper) | Resolve fallback quando HostMedia falha |
| 3 | **Instalar ffmpeg no HostMedia** (se ausente) | Pedir ao suporte | Evita depender do gateway |
| 4 | **Habilitar exec no PHP** (se bloqueado) | php.ini HostMedia | Permite convertWebMToOggBase64 |

---

## 5. Arquivos relevantes (local)

| Componente | Arquivo |
|------------|---------|
| Conversão WebM→OGG | `src/Controllers/CommunicationHubController.php` ~414-495 |
| Fallback audio_mime | `src/Controllers/CommunicationHubController.php` ~1612-1642 |
| Envio áudio | `src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php` sendAudioBase64Ptt |
| MediaRecorder Inbox | `views/layout/main.php` ~2669 |
| MediaRecorder Hub | `views/communication_hub/index.php` ~3968-3981 |
| Diagnóstico ambiente | `public/diagnostic-audio-env.php` |

---

**Próximo passo imediato:** Abrir `https://hub.pixel12digital.com.br/diagnostic-audio-env.php` e ver o JSON. Se `recommendation: "gateway_convert"`, o problema está confirmado: HostMedia não converte e o gateway não implementa a conversão.
