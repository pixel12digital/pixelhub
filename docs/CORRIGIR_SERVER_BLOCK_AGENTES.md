# 🔧 Corrigir Conflito com Server Block do Agentes

## ⚠️ Problema Identificado

O server block do `agentes.pixel12digital.com.br` está respondendo ao invés do nosso. Isso acontece porque:
1. Tem `root /var/www/html;` configurado (servindo arquivo estático)
2. Pode estar sendo carregado primeiro
3. O `server_name` matching pode não estar funcionando corretamente

---

## 🛠️ Solução: Renomear Arquivo para Carregar Primeiro

Execute:

```bash
# 1. Ver ordem atual
ls -la /etc/nginx/conf.d/*.conf

# 2. Renomear nosso arquivo para carregar ANTES (alfabeticamente)
mv /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 3. Validar e recarregar
nginx -t && systemctl reload nginx

# 4. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|HTTP|401|200"
```

---

## 🔧 Solução Alternativa: Verificar server_name Matching

Se renomear não funcionar, pode ser problema de `server_name` matching:

```bash
# Verificar se server_name está correto
grep "server_name" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# Testar com Host header explícito
curl -k -v -H "Host: wpp.pixel12digital.com.br" https://212.85.11.238:8443 2>&1 | grep -E "HTTP|X-Server-Block|401|200"
```

---

## ✅ Verificação Final

Após corrigir:

```bash
# 1. Verificar header customizado (deve aparecer)
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep X-Server-Block

# 2. Sem autenticação (deve dar 401)
curl -k -I https://wpp.pixel12digital.com.br:8443

# 3. Com autenticação (deve dar 404 do gateway)
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🎯 Resultado Esperado

- **Header X-Server-Block aparece**: Confirma que nosso server block está sendo usado
- **Sem autenticação**: `401 Unauthorized`
- **Com autenticação**: `404` (do gateway Express) ou `200`
- **Não mais arquivo estático**: Headers diferentes

