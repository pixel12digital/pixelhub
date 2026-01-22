# 🔧 Corrigir Proxy Fallback e Autenticação

## ⚠️ Problema Identificado

1. **Gateway não está rodando** na porta 3000 (`Failed to connect`)
2. **Nginx está servindo arquivo estático** quando proxy falha (retorna 200 com HTML)
3. **Autenticação não está sendo aplicada** no fallback

---

## 🔍 Diagnóstico

Execute:

```bash
# 1. Verificar se gateway está rodando
ss -tlnp | grep :3000
docker ps | grep -i gateway
docker ps | grep -i wpp

# 2. Verificar se há root/index configurado no server block
grep -E "root|index" /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf

# 3. Verificar configuração padrão do Nginx
grep -A 10 "server {" /etc/nginx/nginx.conf | head -20
```

---

## 🛠️ Correção

### Opção 1: Adicionar tratamento de erro no proxy

Modificar a configuração para retornar erro quando proxy falha:

```bash
# Fazer backup
cp /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# Editar configuração
nano /etc/nginx/conf.d/wpp.pixel12digital.com.br.conf
```

**Adicionar ANTES do `location /`:**

```nginx
    # Retornar erro se gateway não estiver disponível
    error_page 502 503 504 = @gateway_down;
    
    location @gateway_down {
        return 503 "Gateway WhatsApp não está disponível. Tente novamente mais tarde.";
        add_header Content-Type text/plain;
    }
```

**E modificar o `location /` para garantir que autenticação seja sempre verificada:**

```nginx
    location / {
        # Autenticação básica (SEMPRE verificar primeiro)
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
        
        # Se autenticação falhar, retornar 401
        satisfy any;
        
        # Proxy reverso para o gateway
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        
        # Tratamento de erro do proxy
        proxy_intercept_errors on;
        proxy_next_upstream error timeout invalid_header http_500 http_502 http_503;
        
        # Headers para WebSocket
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Headers padrão do proxy
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # Buffering desabilitado
        proxy_buffering off;
        proxy_cache off;
    }
```

---

### Opção 2: Verificar e iniciar o gateway

O problema principal é que o gateway não está rodando. Precisamos:

```bash
# 1. Verificar containers Docker
docker ps -a | grep -i gateway
docker ps -a | grep -i wpp

# 2. Ver logs do gateway (se existir)
docker logs gateway-wrapper --tail 50
docker logs wppconnect-server --tail 50

# 3. Iniciar gateway se estiver parado
docker start gateway-wrapper
# ou
docker start wppconnect-server
```

---

## ✅ Solução Rápida: Bloquear acesso quando gateway não está disponível

```bash
# Criar script de correção
cat > /tmp/corrigir_proxy.sh << 'EOF'
#!/bin/bash
CONFIG_FILE="/etc/nginx/conf.d/wpp.pixel12digital.com.br.conf"
BACKUP_FILE="${CONFIG_FILE}.backup_$(date +%Y%m%d_%H%M%S)"

# Backup
cp "$CONFIG_FILE" "$BACKUP_FILE"

# Adicionar tratamento de erro ANTES do location /
sed -i '/location \/ {/i\    # Retornar erro se gateway não estiver disponível\n    error_page 502 503 504 = @gateway_down;\n    \n    location @gateway_down {\n        return 503 "Gateway WhatsApp não está disponível";\n        add_header Content-Type text/plain;\n    }' "$CONFIG_FILE"

# Adicionar proxy_intercept_errors no location /
sed -i '/proxy_pass http:\/\/127.0.0.1:3000;/a\        proxy_intercept_errors on;' "$CONFIG_FILE"

# Validar
nginx -t && systemctl reload nginx && echo "✓ Configuração aplicada" || echo "✗ Erro na configuração"
EOF

chmod +x /tmp/corrigir_proxy.sh
/tmp/corrigir_proxy.sh
```

---

## 🎯 Prioridade: Iniciar o Gateway

O problema principal é que **o gateway não está rodando**. Precisamos:

1. **Identificar qual container é o gateway**
2. **Iniciar o gateway**
3. **Depois corrigir a autenticação**

Execute:

```bash
# Ver todos os containers
docker ps -a

# Ver containers relacionados a gateway/wpp
docker ps -a | grep -E "gateway|wpp|whatsapp"
```

Compartilhe o resultado para identificarmos qual container iniciar.

