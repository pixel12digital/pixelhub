# Investigação: Áudio outbound — cliente 81642320 recebe "áudio não disponível"

**Data:** 04/02/2026  
**Sintoma:** Apenas o cliente 53 8164-2320 recebe erro "este áudio não está mais disponível, peça a Charles para reenviá-lo" ao receber áudio enviado pelo sistema. Outros destinatários não relatam o problema.

---

## 1. O que podemos investigar do nosso lado

### 1.1 Banco de dados

| O quê | Como | Objetivo |
|-------|------|----------|
| Áudios outbound ao 81642320 | `database/diagnostico-audio-outbound-81642320.php` | Listar todos os envios de áudio para esse número |
| Comparar com outros destinatários | Mesmo script, seção 2 | Ver se há diferença em `file_size`, `stored_path` |
| Arquivo existe no storage? | Script verifica `file_exists(storage/...)` | Se `stored_path` preenchido mas arquivo ausente → problema de persistência |
| Estrutura do payload | Script seção 3 | Confirmar que **não** armazenamos `audio_format` (OGG vs WebM) |

**Script:** `php database/diagnostico-audio-outbound-81642320.php`

### 1.2 Logs do servidor

| O quê | Comando / local | Objetivo |
|-------|-----------------|----------|
| Envios ao 81642320 | `grep -E "555381642320|81642320" logs/pixelhub.log` | Ver se há envios registrados |
| Formato enviado (OGG vs WebM) | `grep "audio_mime=audio/webm" logs/pixelhub.log` | Se aparecer: WebM foi enviado (fallback quando ffmpeg falha) |
| Resposta do gateway | `grep "sendAudioBase64Ptt" logs/pixelhub.log` | Ver `success`, `error`, `correlationId` |
| Erros de conversão | `grep "convertWebMToOgg\|FFMPEG_FAILED\|EXEC_DISABLED" logs/pixelhub.log` | Se ffmpeg falha, enviamos WebM ao gateway |

**Windows (PowerShell):**
```powershell
Get-Content logs\pixelhub.log -Tail 5000 | Select-String -Pattern "81642320|sendAudioBase64Ptt|audio_mime"
```

### 1.3 Código — o que NÃO armazenamos hoje

- **`audio_format_sent`** (OGG ou WebM) — não está no payload do evento.
- **`audio_mime`** — enviado ao gateway mas não persistido.
- Sem isso, não dá para saber pelo banco se um áudio específico foi enviado como OGG ou WebM.

---

## 2. Melhoria sugerida: persistir formato no payload

Para facilitar futuras investigações, podemos gravar o formato enviado no payload do evento:

**Arquivo:** `src/Controllers/CommunicationHubController.php` (após linha ~1844)

```php
if ($messageType === 'audio') {
    $eventPayload['message'] = [
        'to' => $phoneNormalized,
        'type' => 'audio',
        'timestamp' => time()
    ];
    // NOVO: registrar formato enviado para diagnóstico
    $eventPayload['audio_format_sent'] = !empty($audioOptions['audio_mime']) ? 'webm' : 'ogg';
}
```

Assim, consultas no banco poderão filtrar por `audio_format_sent = 'webm'` e correlacionar com reclamações.

---

## 3. Checklist de investigação

- [ ] Rodar `diagnostico-audio-outbound-81642320.php` em produção (banco remoto)
- [ ] Verificar se há áudios outbound ao 81642320 e se os arquivos existem em `storage/`
- [ ] Buscar nos logs por envios ao 555381642320 e por `audio_mime=audio/webm`
- [ ] Se WebM for enviado com frequência: avaliar instalar/ajustar ffmpeg no HostMedia para converter localmente
- [ ] Implementar `audio_format_sent` no payload para próximos envios
- [ ] Pedir ao cliente: verificar armazenamento, versão do WhatsApp, tipo de conexão

---

## 4. Hipóteses (prioridade)

1. **WebM enviado em vez de OGG** — ffmpeg indisponível/falha no HostMedia → gateway converte na VPS → possível problema de compatibilidade no dispositivo do cliente.
2. **Dispositivo/rede do cliente** — armazenamento cheio, conexão instável, WhatsApp desatualizado.
3. **Região/operadora** — DDD 53 com rota ou qualidade de rede pior até os servidores do WhatsApp.

---

## 5. Referências

- `docs/DIAGNOSTICO_AUDIO_81642320_1138.md` — problema inbound (áudio não chega ao Inbox)
- `database/verificar-audio-outbound.php` — diagnóstico genérico de áudios outbound
- `src/Controllers/CommunicationHubController.php` — fluxo de envio (linhas ~1610–1660, 1870–1905)
