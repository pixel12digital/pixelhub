# 🔍 Verificar Arquivo wpp_ssl_8443

## ⚠️ Problema Identificado

Há outro arquivo: `/etc/nginx/sites-enabled/wpp_ssl_8443` que pode estar servindo arquivo estático!

---

## 📋 Verificação

Execute:

```bash
# 1. Ver conteúdo do arquivo wpp_ssl_8443
cat /etc/nginx/sites-enabled/wpp_ssl_8443

# 2. Ver se tem root configurado
grep -E "root|index" /etc/nginx/sites-enabled/wpp_ssl_8443

# 3. Ver se tem server block na porta 8443
grep -A 30 "listen.*8443" /etc/nginx/sites-enabled/wpp_ssl_8443
```

---

## 🛠️ Solução: Desabilitar ou Corrigir

Se o arquivo tiver `root` configurado, desabilite-o:

```bash
# 1. Ver conteúdo primeiro
cat /etc/nginx/sites-enabled/wpp_ssl_8443

# 2. Se tiver root configurado, desabilitar
mv /etc/nginx/sites-enabled/wpp_ssl_8443 /etc/nginx/sites-available/wpp_ssl_8443.disabled

# 3. Validar e recarregar
nginx -t && systemctl reload nginx

# 4. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

---

## ✅ Resultado Esperado

Após desabilitar:
- Header `X-Server-Block: wpp-gateway` aparece
- Sem autenticação: `401 Unauthorized`
- Com autenticação: `404` (do gateway) ou `200`

