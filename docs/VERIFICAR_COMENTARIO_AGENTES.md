# 🔍 Verificar se Comentário Foi Aplicado

## ⚠️ Problema Persistente

Ainda está servindo arquivo estático. Precisamos verificar se o comentário foi aplicado corretamente.

---

## 📋 Verificação

Execute:

```bash
# 1. Ver se comentário foi aplicado
grep -A 5 "Configuração HTTPS na porta 8443" /etc/nginx/sites-enabled/agentes_ssl_8443

# 2. Ver TODOS os server blocks ativos na porta 8443
nginx -T 2>/dev/null | grep -B 10 -A 5 "listen.*8443" | grep -A 5 "server_name"

# 3. Ver se há outros arquivos com server block na 8443
grep -r "listen.*8443" /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null

# 4. Ver configuração atual do agentes
cat /etc/nginx/sites-enabled/agentes_ssl_8443
```

---

## 🛠️ Solução: Desabilitar Arquivo Completamente

Se o comentário não funcionou, podemos desabilitar o arquivo completamente:

```bash
# 1. Desabilitar arquivo (remover link simbólico)
mv /etc/nginx/sites-enabled/agentes_ssl_8443 /etc/nginx/sites-available/agentes_ssl_8443.disabled

# 2. Validar e recarregar
nginx -t && systemctl reload nginx

# 3. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

---

## ✅ Alternativa: Verificar se Há Outro Server Block

Pode haver outro server block servindo arquivo estático. Verifique:

```bash
# Ver todos os server blocks ativos
nginx -T 2>/dev/null | grep -B 5 -A 30 "listen.*8443"
```

