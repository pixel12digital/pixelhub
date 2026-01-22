# 🔧 Remover Root Estático e Forçar Proxy

## ⚠️ Problema Identificado

O Nginx está servindo arquivo estático de `/var/www/html` ao invés de fazer proxy para o gateway. Isso acontece porque há uma diretiva `root` configurada.

---

## 🛠️ Correção

Execute:

```bash
# 1. Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Remover qualquer diretiva root do server block HTTPS
sed -i '/^[[:space:]]*root[[:space:]]/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Garantir que não há index configurado
sed -i '/^[[:space:]]*index[[:space:]]/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 4. Limpar linhas vazias e comentários soltos
sed -i '/^[[:space:]]*# Retornar erro se gateway não estiver disponível[[:space:]]*$/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i '/^[[:space:]]*$/N;/^\n$/d' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 5. Validar
nginx -t

# 6. Se OK, recarregar
systemctl reload nginx

# 7. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Verificação

Após corrigir, verifique:

```bash
# 1. Verificar que não há mais root/index
grep -E "root|index" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 2. Testar sem autenticação (deve dar 401)
curl -k -I https://wpp.pixel12digital.com.br:8443

# 3. Testar com autenticação (deve dar 200 ou 404 do gateway)
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🎯 Resultado Esperado

- **Sem autenticação**: `401 Unauthorized`
- **Com autenticação**: `404` (do gateway) ou `200` (se gateway tiver rota)
- **Não mais arquivo estático**: Headers diferentes (sem `last-modified`, `etag`, etc.)

