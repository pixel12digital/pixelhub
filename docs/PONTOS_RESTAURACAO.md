# Pontos de Restauração do Sistema

Este documento lista os pontos de restauração estáveis do sistema PixelHub.

## Como Restaurar

### Opção 1: Restaurar para uma tag específica
```bash
cd /home/pixel12digital/hub.pixel12digital.com.br
git fetch --tags
git checkout <tag-name>
curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"
```

### Opção 2: Restaurar para um commit específico
```bash
cd /home/pixel12digital/hub.pixel12digital.com.br
git fetch origin
git reset --hard <commit-hash>
curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"
```

---

## Tags de Restauração

### `v1.1.0-stable-media` (29/01/2026) - ATUAL

**Commit:** `9bc7a96`

**Descrição:** Sistema de mídia completo funcionando (áudio, vídeo, imagens). Ponto antes de otimizações de performance.

**Funcionalidades confirmadas:**
- Tudo de v1.0.0-stable-comm
- Download de vídeos (não apenas thumbnail)
- Correção de arquivos JSON salvos incorretamente
- Scripts de diagnóstico de mídia

**Para restaurar:**
```bash
git fetch --tags
git checkout v1.1.0-stable-media
curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"
```

---

### `v1.0.0-stable-comm` (29/01/2026)

**Commit:** `702f8ec`

**Descrição:** Ponto de restauração estável do sistema de comunicação WhatsApp.

**Funcionalidades confirmadas:**
- Recebimento de webhooks do WPP Connect Gateway
- Detecção de mídia (áudio/ptt, imagem, vídeo, documento) em payloads WPP Connect
- Download de mídia via endpoint `/api/media/{channel}/{messageId}`
- Extração correta de áudio de JSON aninhado (workaround para bug do wppconnectAdapter)
- Salvamento de arquivos com mime_type correto

**Fixes incluídos:**
1. `8df2d1f` - Suporte ao formato WPP Connect para audio inbound
2. `938afac` - Usa message id ao invés de mediaKey para download
3. `c8486eb` - Usa endpoint correto `/api/media/{channel}/{messageId}`
4. `702f8ec` - Extrai audio de JSON aninhado

**Para restaurar:**
```bash
git fetch --tags
git checkout v1.0.0-stable-comm
curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"
```

---

## Histórico de Problemas Resolvidos

### Audio Inbound não funcionava (29/01/2026)

**Sintomas:**
- Eventos de áudio chegavam no banco mas `stored_path` ficava vazio
- Arquivos salvos tinham `mime_type: application/json` em vez de `audio/ogg`
- Player de áudio mostrava 0:00 / 0:00

**Causa raiz:**
1. `WhatsAppMediaService` não detectava mídia no formato WPP Connect (`raw.payload.type`)
2. Usava `mediaKey` (chave de criptografia) em vez de `messageId` para download
3. Chamava endpoint errado no gateway (`/api/channels/...` em vez de `/api/media/...`)
4. Gateway `wppconnectAdapter` codificava JSON inteiro como base64

**Solução:**
- Commits `8df2d1f` até `702f8ec` corrigem todos os pontos acima

---

## Notas Importantes

1. **Sempre limpar OPCache** após restaurar: `curl -s "https://hub.pixel12digital.com.br/clear-opcache.php"`
2. **VPS não precisa de mudanças** - Os fixes são todos no lado HostMedia
3. **Audios antigos** salvos como JSON não serão recuperáveis (precisariam reprocessar do gateway)
