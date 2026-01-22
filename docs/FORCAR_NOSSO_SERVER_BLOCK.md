# 🔧 Forçar Uso do Nosso Server Block

## ⚠️ Problema Persistente

O header `X-Server-Block` não aparece, confirmando que nosso server block não está sendo usado. O server block do `agentes` está respondendo.

---

## 🛠️ Solução: Verificar e Corrigir

Execute:

```bash
# 1. Verificar se arquivo foi renomeado
ls -la /etc/nginx/conf.d/00-*.conf

# 2. Ver TODOS os server blocks na porta 8443 com server_name
nginx -T 2>/dev/null | grep -B 10 -A 5 "listen.*8443" | grep -A 5 "server_name"

# 3. Ver qual arquivo tem o server block do agentes
grep -l "agentes.pixel12digital.com.br" /etc/nginx/conf.d/*.conf

# 4. Ver configuração completa do agentes
cat /etc/nginx/conf.d/*.conf | grep -B 5 -A 20 "agentes.pixel12digital.com.br" | head -40

# 5. Testar com Host header específico para forçar nosso server block
curl -k -v -H "Host: wpp.pixel12digital.com.br" https://212.85.11.238:8443 2>&1 | head -50
```

---

## 🔧 Solução Alternativa: Remover Temporariamente o Conflito

Se o problema persistir, podemos temporariamente desabilitar o server block do agentes na porta 8443:

```bash
# 1. Fazer backup do arquivo do agentes
cp /etc/nginx/conf.d/*agentes*.conf /etc/nginx/conf.d/*agentes*.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Comentar o server block HTTPS do agentes (porta 8443)
sed -i '/listen 8443 ssl http2;/,/^}/s/^/#/' /etc/nginx/conf.d/*agentes*.conf

# 3. Validar e recarregar
nginx -t && systemctl reload nginx

# 4. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

**⚠️ ATENÇÃO:** Isso pode afetar o acesso ao `agentes.pixel12digital.com.br`. Se necessário, podemos configurar o agentes para usar outra porta.

---

## ✅ Verificação

Após corrigir:

```bash
# Deve aparecer X-Server-Block agora
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep X-Server-Block

# Deve dar 401 sem autenticação
curl -k -I https://wpp.pixel12digital.com.br:8443
```

