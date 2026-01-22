# 🔧 Corrigir Conflito de Server Blocks na Porta 8443

## ⚠️ Problema Identificado

Há **DOIS server blocks** escutando na porta **8443**:
1. `wpp.pixel12digital.com.br` (nosso - com proxy)
2. `agentes.pixel12digital.com.br` (outro - com `root /var/www/html;`)

O outro server block tem `root` configurado, o que pode estar servindo arquivo estático quando há conflito.

---

## 🛠️ Solução: Adicionar default_server ao Nosso Server Block

Execute:

```bash
# 1. Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Adicionar default_server ao nosso server block
sed -i 's/listen 8443 ssl http2;/listen 8443 ssl http2 default_server;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
sed -i 's/listen \[::\]:8443 ssl http2;/listen [::]:8443 ssl http2 default_server;/g' /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Validar
nginx -t

# 4. Se OK, recarregar
systemctl reload nginx

# 5. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Verificação

Após corrigir:

```bash
# 1. Verificar que default_server foi adicionado
grep "default_server" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 2. Testar sem autenticação (deve dar 401 agora!)
curl -k -I https://wpp.pixel12digital.com.br:8443

# 3. Testar com autenticação (deve dar 200 ou 404 do gateway)
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🎯 Resultado Esperado

- **Sem autenticação**: `401 Unauthorized` (autenticação funcionando!)
- **Com autenticação**: `404` (do gateway Express) ou `200` (se houver rota)
- **Não mais arquivo estático**: Headers diferentes

---

## 📝 Nota

O outro server block (`agentes.pixel12digital.com.br`) continuará funcionando normalmente porque usa `server_name` específico. O `default_server` apenas garante que requisições sem `Host` correto sejam atendidas pelo nosso server block.

