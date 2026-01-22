# 🔄 Redirecionar para Dashboard Após Autenticação

## 🛠️ Configurar Redirecionamento

Execute na VPS:

```bash
# 1. Fazer backup da configuração atual
cp /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Editar configuração para adicionar redirecionamento
nano /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
```

---

## 📝 Modificar Configuração

Adicione um `location /` que redireciona para `/ui/` (ou a rota do dashboard do seu gateway):

```nginx
location = / {
    auth_basic "Acesso Restrito - Gateway WhatsApp";
    auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;
    
    return 302 /ui/;
}

location / {
    auth_basic "Acesso Restrito - Gateway WhatsApp";
    auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;

    proxy_pass http://172.19.0.1:3000;
    proxy_http_version 1.1;
    
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;

    proxy_buffering off;
    proxy_cache off;
}
```

---

## 🔧 Alternativa: Script Automático

Execute este script para fazer a alteração automaticamente:

```bash
# Script para adicionar redirecionamento
cat > /tmp/adicionar_redirect.sh << 'EOF'
#!/bin/bash
CONF_FILE="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"
BACKUP_FILE="${CONF_FILE}.backup_$(date +%Y%m%d_%H%M%S)"

# Backup
cp "$CONF_FILE" "$BACKUP_FILE"

# Adicionar location = / antes do location / existente
sed -i '/location \/ {/i\
    location = / {\
        auth_basic "Acesso Restrito - Gateway WhatsApp";\
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;\
        return 302 /ui/;\
    }\
' "$CONF_FILE"

echo "Redirecionamento adicionado!"
echo "Backup salvo em: $BACKUP_FILE"
EOF

chmod +x /tmp/adicionar_redirect.sh
/tmp/adicionar_redirect.sh

# Validar e recarregar
nginx -t && nginx -s reload
```

---

## 🎯 Rotas Comuns do Dashboard

Se `/ui/` não funcionar, tente estas rotas comuns:

- `/ui/` - Interface web (mais comum)
- `/dashboard/` - Dashboard
- `/admin/` - Painel administrativo
- `/web/` - Interface web

Para testar qual rota funciona:

```bash
# Testar diferentes rotas
curl -k -u "wpp.pixel12:SUA_SENHA" https://wpp.pixel12digital.com.br:8443/ui/ | head -20
curl -k -u "wpp.pixel12:SUA_SENHA" https://wpp.pixel12digital.com.br:8443/dashboard/ | head -20
```

---

## ✅ Após Configurar

1. Acesse: `https://wpp.pixel12digital.com.br:8443`
2. Digite usuário e senha
3. Será redirecionado automaticamente para `/ui/` (ou a rota configurada)

