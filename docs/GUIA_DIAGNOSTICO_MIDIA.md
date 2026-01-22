# Guia de Diagnóstico: Mídia não aparece na Thread

## 🎯 Sequência de Diagnóstico (Ordem Exata)

### Prova #1: O endpoint entrega o arquivo de verdade?

**Ação:**
1. Abra a thread no navegador (com sessão ativa)
2. Abra DevTools (F12) → Network
3. Recarregue a página
4. Procure por requisição para `/communication-hub/media?path=...`

**O que deve acontecer:**
- ✅ Status: **200 OK**
- ✅ Content-Type: `audio/ogg`
- ✅ Arquivo deve tocar ou baixar

**Se der erro:**
- **302/401/403**: Endpoint requer autenticação (sessão expirada ou não enviada)
- **404**: Rota/BASE_PATH incorretos
- **200 mas Content-Type: text/html**: Recebendo página de login/erro no lugar do áudio

**Teste direto:**
Copie a URL do `media.url` e cole na barra de endereços (com sessão ativa):
```
/communication-hub/media?path=whatsapp-media%2F2026%2F01%2F16%2Ff6528d90b33fe0db1a41f275ab9c8346.ogg
```

---

### Prova #2: A página está tentando buscar o áudio?

**Ação:**
1. DevTools (F12) → Network
2. Filtre por `media` ou `ogg`
3. Recarregue a thread

**O que deve aparecer:**
- ✅ Uma requisição para `/communication-hub/media?path=...`

**Se não aparecer:**
- ❌ O elemento `<audio>` não está sendo renderizado no DOM
- ❌ Ou está sendo removido pelo JavaScript

**Se aparecer e falhar:**
- ❌ Problema é endpoint/URL/CSP/CORS

---

### Prova #3: O `<audio>` existe no DOM?

**Ação:**
1. DevTools (F12) → Elements
2. Ctrl+F e procure por `<audio` na conversa

**O que deve aparecer:**
- ✅ Elemento `<audio>` com `src` preenchido

**Se não existir:**
- ❌ A condição `message.media && message.media.url` não está sendo satisfeita
- ❌ Ou o bloco PHP não está sendo executado

**Se existir:**
- Verifique o `src` final
- Teste abrindo o `src` em nova aba (com sessão ativa)

---

### Prova #4: BASE_PATH / Prefixo Errado

**Problema:**
- URL gerada como `/communication-hub/media?...` (relativa)
- Mas app pode estar em subpasta (ex: `/hub/communication-hub/...`)
- Resultado: URL quebra

**Verificar:**
1. Inspecionar elemento `<audio>` no DOM
2. Ver `src` final
3. Comparar com URL esperada

**Correção aplicada:**
- Agora sempre usa `pixelhub_url()` quando disponível
- Gera URL absoluta correta

**Teste:**
```javascript
// No console do navegador
console.log(document.querySelector('audio')?.src);
// Deve mostrar URL completa com domínio correto
```

---

### Prova #5: CSP (Content Security Policy)

**Problema:**
- CSP pode estar bloqueando carregamento de mídia
- `<audio>` existe, mas browser bloqueia a carga

**Verificar:**
1. DevTools → Console
2. Procure por erros como:
   - `Refused to load media from ... because it violates the following Content Security Policy directive: media-src`
   - `Content Security Policy: The page's settings blocked the loading of a resource`

**Correção:**
Se houver CSP, garantir:
```
media-src 'self' blob: data:;
```

---

## 🔧 Scripts de Teste Criados

### 1. `database/testar-endpoint-media.php`
Testa se o endpoint está acessível e gera URL correta.

**Uso:**
```bash
php database/testar-endpoint-media.php
```

### 2. `database/debug-thread-completo.php`
Verifica todos os pontos do fluxo.

**Uso:**
```bash
php database/debug-thread-completo.php
```

### 3. `database/testar-thread-completo.php`
Simula retorno completo da thread.

**Uso:**
```bash
php database/testar-thread-completo.php
```

---

## 🐛 Debug Adicionado

### No Backend (PHP)
Logs temporários adicionados em:
- `views/communication_hub/thread.php` (linha 78+)
- Verifica estrutura da mídia antes de renderizar

### No Frontend (JavaScript)
Console logs adicionados em:
- `views/communication_hub/thread.php` (linha 253+)
- Mostra quando mídia é detectada e renderizada

**Para ver os logs:**
1. Abra DevTools → Console
2. Recarregue a thread
3. Procure por `[THREAD JS DEBUG]` ou `[THREAD DEBUG]`

---

## ✅ Checklist Rápido

- [ ] Endpoint `/communication-hub/media` acessível (status 200)
- [ ] Content-Type correto (`audio/ogg`)
- [ ] Requisição aparece no Network tab
- [ ] Elemento `<audio>` existe no DOM
- [ ] `src` do `<audio>` está correto
- [ ] Sessão ativa (cookies sendo enviados)
- [ ] Sem erros de CSP no console
- [ ] BASE_PATH aplicado corretamente na URL

---

## 🎯 Próximo Passo Imediato

**1. Abrir DevTools → Network**
**2. Recarregar thread**
**3. Verificar requisição para `/communication-hub/media`**
**4. Enviar:**
   - Status HTTP
   - Headers da resposta
   - Screenshot do Network tab

Com essas informações, identificamos exatamente a causa e aplicamos o patch correto.

