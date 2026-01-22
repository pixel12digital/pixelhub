#!/bin/bash

###############################################################################
# Script: Corrigir Configuração Nginx - Conflito com AzuraCast
# Objetivo: Ajustar configuração para usar porta 8443 ao invés de 443
#           (AzuraCast já está usando 443)
###############################################################################

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DOMAIN="wpp.pixel12digital.com.br"
CONFIG_FILE="/etc/nginx/conf.d/wpp.pixel12digital.com.br.conf"
BACKUP_FILE="${CONFIG_FILE}.backup_$(date +%Y%m%d_%H%M%S)"
EXTERNAL_PORT="8443"  # Porta externa alternativa

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERRO]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[AVISO]${NC} $1"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Verificar se é root
if [ "$EUID" -ne 0 ]; then 
    error "Por favor, execute como root (sudo ./corrigir_configuracao_nginx.sh)"
    exit 1
fi

log "=========================================="
log "Corrigindo Configuração Nginx"
log "=========================================="
log ""

# Verificar se arquivo existe
if [ ! -f "$CONFIG_FILE" ]; then
    error "Arquivo de configuração não encontrado: $CONFIG_FILE"
    exit 1
fi

# Fazer backup
log "Criando backup: $BACKUP_FILE"
cp "$CONFIG_FILE" "$BACKUP_FILE"
log "✓ Backup criado"

# Ler configuração atual
log "Lendo configuração atual..."
CERT_DIR=$(grep "ssl_certificate" "$CONFIG_FILE" | head -1 | awk '{print $2}' | sed 's/\/fullchain.pem;//' | xargs dirname)
AUTH_FILE=$(grep "auth_basic_user_file" "$CONFIG_FILE" | awk '{print $2}' | tr -d ';')
GATEWAY_PORT=$(grep "proxy_pass" "$CONFIG_FILE" | grep -o "127.0.0.1:[0-9]*" | cut -d: -f2)

log "Certificado: $CERT_DIR"
log "Autenticação: $AUTH_FILE"
log "Gateway interno: $GATEWAY_PORT"

# Verificar se há IP whitelist
HAS_WHITELIST=false
if grep -q "deny all;" "$CONFIG_FILE"; then
    HAS_WHITELIST=true
    ALLOWED_IPS=($(grep "allow " "$CONFIG_FILE" | awk '{print $2}' | tr -d ';'))
    log "IP Whitelist encontrada: ${#ALLOWED_IPS[@]} IPs"
fi

log ""
log "Criando nova configuração na porta $EXTERNAL_PORT..."

# Criar nova configuração
cat > "$CONFIG_FILE" <<EOF
# Configuração para $DOMAIN - Gateway WhatsApp
# Porta externa: $EXTERNAL_PORT (AzuraCast usa 443)
# Gerado automaticamente em $(date)

# Redirecionar HTTP para HTTPS na porta alternativa
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    # Permitir validação do Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirecionar para HTTPS na porta $EXTERNAL_PORT
    location / {
        return 301 https://\$server_name:$EXTERNAL_PORT\$request_uri;
    }
}

# Configuração HTTPS na porta alternativa
server {
    listen $EXTERNAL_PORT ssl http2;
    listen [::]:$EXTERNAL_PORT ssl http2;
    server_name $DOMAIN www.$DOMAIN;

    # Certificados SSL
    ssl_certificate ${CERT_DIR}/fullchain.pem;
    ssl_certificate_key ${CERT_DIR}/privkey.pem;

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
    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;

    # Tamanho máximo de upload
    client_max_body_size 100M;

    # Timeout aumentado para conexões WebSocket
    proxy_connect_timeout 7d;
    proxy_send_timeout 7d;
    proxy_read_timeout 7d;

    # Configuração de proxy reverso para o gateway
    location / {
EOF

# Adicionar IP whitelist se existir
if [ "$HAS_WHITELIST" = true ] && [ ${#ALLOWED_IPS[@]} -gt 0 ]; then
    cat >> "$CONFIG_FILE" <<EOF
        # IP Whitelist
        deny all;
EOF
    for ip in "${ALLOWED_IPS[@]}"; do
        echo "        allow $ip;" >> "$CONFIG_FILE"
    done
    cat >> "$CONFIG_FILE" <<EOF
        # Fim da whitelist
EOF
fi

# Continuar com autenticação e proxy
cat >> "$CONFIG_FILE" <<EOF

        # Autenticação básica
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file $AUTH_FILE;

        # Proxy reverso para o gateway
        proxy_pass http://127.0.0.1:$GATEWAY_PORT;
        proxy_http_version 1.1;
        
        # Headers para WebSocket
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Headers padrão do proxy
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header X-Forwarded-Host \$host;
        proxy_set_header X-Forwarded-Port \$server_port;

        # Buffering desabilitado para streaming
        proxy_buffering off;
        proxy_cache off;
    }

    # Permitir acesso ao Let's Encrypt sem autenticação
    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
}
EOF

log "✓ Configuração criada"

# Validar sintaxe
log "Validando sintaxe..."
if nginx -t 2>&1; then
    log "✓ Sintaxe válida"
else
    error "✗ Erro na sintaxe. Restaurando backup..."
    cp "$BACKUP_FILE" "$CONFIG_FILE"
    exit 1
fi

# Recarregar Nginx
log "Recarregando Nginx..."
if systemctl reload nginx 2>&1; then
    log "✓ Nginx recarregado com sucesso"
else
    error "✗ Falha ao recarregar Nginx"
    exit 1
fi

log ""
log "=========================================="
log "CONFIGURAÇÃO CORRIGIDA COM SUCESSO!"
log "=========================================="
log ""
log "Resumo:"
log "  - Domínio: $DOMAIN"
log "  - Porta externa: $EXTERNAL_PORT (HTTPS)"
log "  - Gateway interno: http://127.0.0.1:$GATEWAY_PORT"
log "  - Backup: $BACKUP_FILE"
log ""
log "IMPORTANTE:"
log "  - Acesse o gateway em: https://$DOMAIN:$EXTERNAL_PORT"
log "  - AzuraCast continua funcionando normalmente na porta 443"
log "  - Certifique-se de abrir a porta $EXTERNAL_PORT no firewall"
log ""
log "Para abrir a porta no firewall (UFW):"
log "  ufw allow $EXTERNAL_PORT/tcp"
log ""
log "Para abrir a porta no firewall (iptables):"
log "  iptables -A INPUT -p tcp --dport $EXTERNAL_PORT -j ACCEPT"
log ""

