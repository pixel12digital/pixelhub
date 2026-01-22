# 🔧 Corrigir IP do Proxy

## ⚠️ Problema Identificado

O gateway está rodando em `172.19.0.1:3000` (rede Docker), mas o Nginx está tentando conectar em `127.0.0.1:3000`.

**Solução:** Alterar o `proxy_pass` para usar o IP correto.

---

## 🛠️ Correção

Execute:

```bash
# 1. Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Remover a correção anterior que deu erro
sed -i '/error_page 502 503 504 = @gateway_down;/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i '/location @gateway_down/,/}/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i '/proxy_intercept_errors on;/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Alterar proxy_pass de 127.0.0.1:3000 para 172.19.0.1:3000
sed -i 's|proxy_pass http://127.0.0.1:3000;|proxy_pass http://172.19.0.1:3000;|g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 4. Validar
nginx -t

# 5. Se OK, recarregar
systemctl reload nginx

# 6. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Teste Completo

Após corrigir:

```bash
# 1. Testar sem autenticação (deve dar 401)
curl -k -I https://wpp.pixel12digital.com.br:8443

# 2. Testar com autenticação (deve dar 200)
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443

# 3. Ver logs
tail -10 /var/log/nginx/wpp.pixel12digital.com.br_access.log
```

---

## 🎯 Resultado Esperado

- **Sem autenticação**: `401 Unauthorized` (autenticação funcionando!)
- **Com autenticação**: `200 OK` (gateway funcionando!)
- **Logs**: Mostrando requisições com códigos 401 e 200

