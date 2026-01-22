# 🔍 Verificar Server Block Padrão Final

## ⚠️ Problema Persistente

Ainda está servindo arquivo estático. Os headers mostram `content-security-policy` e `referrer-policy` que não estão na nossa configuração, indicando que outro server block está respondendo.

---

## 📋 Diagnóstico Final

Execute:

```bash
# 1. Ver TODOS os server blocks ativos na porta 8443 (completo)
nginx -T 2>/dev/null | grep -B 20 "listen.*8443" | grep -A 60 "server {"

# 2. Ver se há server block padrão no nginx.conf
grep -A 50 "server {" /etc/nginx/nginx.conf | head -80

# 3. Ver TODOS os arquivos em sites-enabled
ls -la /etc/nginx/sites-enabled/

# 4. Ver conteúdo de TODOS os arquivos em sites-enabled
for file in /etc/nginx/sites-enabled/*; do
    echo "=== $file ==="
    grep -E "listen.*8443|server_name.*wpp|root.*html" "$file" 2>/dev/null | head -5
done

# 5. Ver qual server block está realmente respondendo (testar com IP direto)
curl -k -v https://212.85.11.238:8443 2>&1 | grep -E "HTTP|server:|X-Server-Block|content-security-policy" | head -10
```

---

## 🛠️ Solução: Verificar Arquivo sites-available

O arquivo pode estar em sites-available e ser carregado de outra forma:

```bash
# 1. Ver arquivos em sites-available
ls -la /etc/nginx/sites-available/ | grep -E "agentes|wpp"

# 2. Ver se há link simbólico quebrado
find /etc/nginx/sites-enabled -type l -exec ls -la {} \;
```

---

## ✅ Solução Alternativa: Recarregar Nginx Completamente

Pode ser cache do Nginx. Tente:

```bash
# 1. Parar Nginx completamente
systemctl stop nginx

# 2. Iniciar novamente
systemctl start nginx

# 3. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

