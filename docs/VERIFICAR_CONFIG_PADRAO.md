# 🔍 Verificar Configuração Padrão do Nginx

## ⚠️ Problema Persistente

Ainda está servindo arquivo estático mesmo após remover `root`. Pode haver uma configuração padrão do Nginx.

---

## 📋 Comandos de Diagnóstico

Execute:

```bash
# 1. Ver configuração padrão do Nginx (pode ter root/index global)
grep -E "root|index" /etc/nginx/nginx.conf

# 2. Ver se há server block padrão
grep -A 10 "server {" /etc/nginx/nginx.conf | head -30

# 3. Ver configuração completa atual
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 4. Ver logs de erro detalhados
tail -30 /var/log/nginx/wpp.pixel12digital.com.br_error.log

# 5. Ver logs de acesso (ver se proxy está sendo chamado)
tail -30 /var/log/nginx/wpp.pixel12digital.com.br_access.log

# 6. Testar proxy diretamente com verbose
curl -k -v https://wpp.pixel12digital.com.br:8443 2>&1 | head -50
```

---

## 🛠️ Possível Solução: Forçar Proxy e Bloquear Fallback

Se houver configuração padrão, precisamos garantir que o proxy seja usado e bloquear qualquer fallback:

```bash
# Editar configuração manualmente
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**Garantir que o location / tenha:**

```nginx
    location / {
        # Autenticação básica
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
        
        # Forçar proxy (sem fallback)
        proxy_pass http://172.19.0.1:3000;
        proxy_http_version 1.1;
        
        # Headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Não servir arquivos estáticos
        try_files $uri $uri/ =404;
    }
```

---

## 🔧 Solução Alternativa: Verificar se Proxy Está Funcionando

```bash
# Testar se proxy está sendo chamado
curl -k -v https://wpp.pixel12digital.com.br:8443 2>&1 | grep -E "HTTP|X-|server|proxy"

# Ver se há erro de conexão no log
grep -i "connect\|proxy\|172.19.0.1" /var/log/nginx/wpp.pixel12digital.com.br_error.log
```

