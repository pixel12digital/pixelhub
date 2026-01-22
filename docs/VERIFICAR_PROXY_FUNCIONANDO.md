# 🔍 Verificar se Proxy Está Funcionando

## ⚠️ Problema Persistente

Ainda está servindo arquivo estático. Precisamos verificar se o proxy está sendo executado.

---

## 📋 Diagnóstico Completo

Execute:

```bash
# 1. Ver configuração atual completa
cat /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 2. Testar gateway diretamente no IP do Docker
curl -v http://172.19.0.1:3000 2>&1 | head -40

# 3. Ver logs de erro detalhados (ver se há erro de proxy)
tail -50 /var/log/nginx/wpp.pixel12digital.com.br_error.log

# 4. Ver logs de acesso (ver se proxy está sendo chamado)
tail -20 /var/log/nginx/wpp.pixel12digital.com.br_access.log

# 5. Verificar se há try_files ou fallback configurado
grep -E "try_files|error_page|fallback" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 6. Testar com Host header específico
curl -k -v -H "Host: wpp.pixel12digital.com.br" https://212.85.11.238:8443 2>&1 | head -50
```

---

## 🛠️ Possível Solução: Forçar Proxy e Bloquear Fallback

Se o proxy não estiver funcionando, vamos forçar e bloquear qualquer fallback:

```bash
# Editar manualmente
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**Garantir que o location / tenha APENAS proxy, sem fallback:**

```nginx
    location / {
        # Autenticação básica
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
        
        # Proxy reverso (SEM try_files, SEM root, SEM index)
        proxy_pass http://172.19.0.1:3000;
        proxy_http_version 1.1;
        
        # Headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Não servir arquivos estáticos
        proxy_buffering off;
        proxy_cache off;
    }
```

---

## 🔧 Verificar se Gateway Está Respondendo

```bash
# Testar gateway diretamente
curl -v http://172.19.0.1:3000 2>&1 | grep -E "HTTP|404|200|Connection"

# Se não responder, verificar container
docker logs gateway-wrapper --tail 20
```

