# Diagnóstico: Áudios não estão sendo salvos/reproduzidos

**Data:** 2026-01-17  
**Problema:** Áudios registrados no banco mas arquivos físicos não existem no servidor

---

## 🔴 Problema Identificado

### Situação atual:
- ✅ Registros em `communication_media` existem (6 áudios encontrados)
- ✅ Diretório `storage/whatsapp-media/` existe e tem permissão de escrita
- ❌ **6 de 7 arquivos de mídia NÃO existem fisicamente no servidor**
- ❌ Media URLs no payload estão vazias (ou null)

---

## 🔍 Análise do Código

### Fluxo de processamento de mídia:

1. **WhatsAppMediaService::processMediaFromEvent()** (linha 21)
   - Extrai `mediaId` e `channelId` do payload
   - Chama `WhatsAppGatewayClient::downloadMedia()` (linha 233)
   
2. **Se download falhar** (linha 236):
   - Salva registro com `stored_path = null`
   - **Mas observação:** Registros no banco TÊM `stored_path` definido
   
3. **Se download for bem-sucedido** (linha 242-272):
   - Gera `$storedPath` (linha 252)
   - Salva arquivo com `file_put_contents()` (linha 256)
   - **Se `file_put_contents()` falhar:** retorna com `stored_path = null`
   - **Se sucesso:** salva registro no banco com `$storedPath` (linha 264)

---

## 🎯 Possíveis Causas

### Hipótese 1: Download do gateway está falhando
**Sintoma:** Media URLs no payload estão vazias
**Evidência:** Verificação mostrou `payload_media_url` e `payload_mediaUrl` vazios
**Teste necessário:** Verificar logs do WhatsAppGatewayClient para erros de download

### Hipótese 2: `file_put_contents()` retorna true mas arquivo não é salvo
**Sintoma:** Registros têm `stored_path` mas arquivo não existe
**Causa possível:** Problema de caminho absoluto vs relativo, ou arquivo sendo deletado
**Teste necessário:** Adicionar verificação `file_exists()` após `file_put_contents()`

### Hipótese 3: Arquivo sendo salvo em local diferente
**Sintoma:** Registro tem `stored_path` correto mas arquivo não existe no caminho esperado
**Causa possível:** Diferença entre `$fullPath` (absoluto) e `$storedPath` (relativo)
**Teste necessário:** Verificar se `$fullPath` está correto antes de salvar

---

## ✅ Próximos Passos

1. **Verificar logs do WhatsAppGatewayClient:**
   - Procurar por `[WhatsAppGateway::downloadMedia]` nos logs
   - Verificar se há erros de cURL ou HTTP

2. **Adicionar validação após `file_put_contents()`:**
   - Verificar `file_exists($fullPath)` após salvar
   - Se não existir, não salvar `stored_path` no banco

3. **Verificar logs do WhatsAppMediaService:**
   - Procurar por `[WhatsAppMediaService] Falha ao baixar mídia` ou `[WhatsAppMediaService] Falha ao salvar arquivo`

4. **Testar download manual:**
   - Verificar se endpoint `/api/channels/{channelId}/media/{mediaId}` está funcionando no gateway

---

## 📝 Observações

- **Registros no banco têm `stored_path`** = download provavelmente foi chamado e registro foi salvo
- **Arquivos não existem** = `file_put_contents()` pode estar retornando `true` mas não salvando, OU arquivos foram deletados
- **Media URLs vazias no payload** = pode ser que o gateway não esteja enviando `mediaUrl` no payload

**Conclusão:** O problema está provavelmente no processo de download ou salvamento de arquivo, não na permissão ou no diretório.








