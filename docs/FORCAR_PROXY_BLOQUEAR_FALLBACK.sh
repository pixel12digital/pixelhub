#!/bin/bash

###############################################################################
# Script: Forçar Proxy e Bloquear Fallback para Arquivo Estático
###############################################################################

CONFIG_FILE="/etc/nginx/conf.d/wpp.pixel12digital.com.br.conf"
BACKUP_FILE="${CONFIG_FILE}.backup_$(date +%Y%m%d_%H%M%S)"

echo "Fazendo backup..."
cp "$CONFIG_FILE" "$BACKUP_FILE"

echo "Criando nova configuração forçando proxy..."

# Criar nova configuração completa
cat > "$CONFIG_FILE" << 'EOF'
# Configuração para wpp.pixel12digital.com.br - Gateway WhatsApp
# Gerado automaticamente em $(date)

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name wpp.pixel12digital.com.br www.wpp.pixel12digital.com.br;

    # Permitir validação do Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirecionar todo o resto para HTTPS
    location / {
        return 301 https://$server_name:8443$request_uri;
    }
}

# Configuração HTTPS
server {
    listen 8443 ssl http2 default_server;
    listen [::]:8443 ssl http2 default_server;
    server_name wpp.pixel12digital.com.br www.wpp.pixel12digital.com.br;

    # Certificados SSL
    ssl_certificate /etc/letsencrypt/live/wpp.pixel12digital.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wpp.pixel12digital.com.br/privkey.pem;

    # Configurações SSL modernas e seguras
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Headers de segurança
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logs
    access_log /var/log/nginx/wpp.pixel12digital.com.br_access.log;
    error_log /var/log/nginx/wpp.pixel12digital.com.br_error.log;

    # Tamanho máximo de upload
    client_max_body_size 100M;

    # Timeout aumentado para conexões WebSocket
    proxy_connect_timeout 7d;
    proxy_send_timeout 7d;
    proxy_read_timeout 7d;

    # IMPORTANTE: NÃO configurar root ou index aqui!
    # Isso faria o Nginx servir arquivos estáticos

    # Configuração de proxy reverso para o gateway
    location / {
        # Autenticação básica (PRIMEIRO)
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file /etc/nginx/.htpasswd_wpp.pixel12digital.com.br;

        # Proxy reverso para o gateway (FORÇADO, sem fallback)
        proxy_pass http://172.19.0.1:3000;
        proxy_http_version 1.1;
        
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

        # Buffering desabilitado para streaming
        proxy_buffering off;
        proxy_cache off;
        
        # Não servir arquivos estáticos (forçar proxy)
        # Se proxy falhar, retornar erro ao invés de servir arquivo
    }

    # Permitir acesso ao Let's Encrypt sem autenticação
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
}
EOF

echo "Validando configuração..."
if nginx -t; then
    echo "✓ Sintaxe válida"
    echo "Recarregando Nginx..."
    systemctl reload nginx
    echo "✓ Nginx recarregado"
    echo ""
    echo "Teste agora:"
    echo "  curl -k -I https://wpp.pixel12digital.com.br:8443"
else
    echo "✗ Erro na sintaxe. Restaurando backup..."
    cp "$BACKUP_FILE" "$CONFIG_FILE"
    exit 1
fi

