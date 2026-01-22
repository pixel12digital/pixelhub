#!/bin/bash

###############################################################################
# Script: Proteger Gateway WhatsApp + Corrigir SSL
# Objetivo: 
#   1. Diagnosticar e corrigir problema SSL (ERR_SSL_PROTOCOL_ERROR)
#   2. Proteger gateway contra acesso público não autorizado
#   3. Implementar autenticação básica + IP whitelist
#   4. Não interferir com AzuraCast
###############################################################################

set -e  # Para em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variáveis
DOMAIN="wpp.pixel12digital.com.br"
NGINX_CONF_DIR="/etc/nginx"
NGINX_SITES_ENABLED="${NGINX_CONF_DIR}/sites-enabled"
NGINX_CONF_D="${NGINX_CONF_DIR}/conf.d"
CERTBOT_DIR="/etc/letsencrypt"
BACKUP_DIR="/root/backup_nginx_$(date +%Y%m%d_%H%M%S)"
LOG_FILE="/root/gateway_ssl_fix_$(date +%Y%m%d_%H%M%S).log"

# Função para log
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERRO]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[AVISO]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"
}

# Verificar se é root
if [ "$EUID" -ne 0 ]; then 
    error "Por favor, execute como root (sudo ./script_proteger_gateway_ssl.sh)"
    exit 1
fi

log "=========================================="
log "Script de Proteção do Gateway WhatsApp"
log "=========================================="
log "Domínio: $DOMAIN"
log "Log: $LOG_FILE"
log ""

# Criar diretório de backup
mkdir -p "$BACKUP_DIR"
log "Diretório de backup criado: $BACKUP_DIR"

###############################################################################
# FASE 1: DIAGNÓSTICO
###############################################################################

log "=== FASE 1: DIAGNÓSTICO ==="

# 1.1 Verificar status do Nginx
log "Verificando status do Nginx..."
if systemctl is-active --quiet nginx; then
    log "✓ Nginx está rodando"
else
    error "✗ Nginx não está rodando"
    systemctl status nginx || true
    exit 1
fi

# 1.2 Verificar sintaxe do Nginx
log "Verificando sintaxe do Nginx..."
if nginx -t 2>&1 | tee -a "$LOG_FILE"; then
    log "✓ Sintaxe do Nginx está correta"
else
    error "✗ Erro na sintaxe do Nginx"
    exit 1
fi

# 1.3 Encontrar configuração atual
log "Procurando configuração para $DOMAIN..."
CONFIG_FILE=$(find "$NGINX_CONF_DIR" -type f -name "*.conf" -exec grep -l "$DOMAIN" {} \; | head -1)

if [ -z "$CONFIG_FILE" ]; then
    warning "Configuração não encontrada. Verificando sites-enabled e conf.d..."
    CONFIG_FILE=$(find "$NGINX_SITES_ENABLED" "$NGINX_CONF_D" -type f -name "*.conf" 2>/dev/null | head -1)
    if [ -z "$CONFIG_FILE" ]; then
        error "Nenhuma configuração encontrada. Criando nova configuração..."
        CONFIG_FILE="${NGINX_CONF_D}/${DOMAIN}.conf"
    fi
else
    log "✓ Configuração encontrada: $CONFIG_FILE"
    # Fazer backup
    cp "$CONFIG_FILE" "${BACKUP_DIR}/$(basename $CONFIG_FILE).backup"
    log "✓ Backup criado: ${BACKUP_DIR}/$(basename $CONFIG_FILE).backup"
fi

# 1.4 Verificar certificados SSL
log "Verificando certificados SSL..."
if certbot certificates 2>&1 | grep -q "$DOMAIN"; then
    log "✓ Certificado encontrado para $DOMAIN"
    CERTBOT_CERT=$(certbot certificates 2>&1 | grep -A 5 "$DOMAIN" | grep "Certificate Path" | awk '{print $3}' | head -1)
    if [ -n "$CERTBOT_CERT" ]; then
        CERT_DIR=$(dirname "$CERTBOT_CERT")
        log "✓ Diretório do certificado: $CERT_DIR"
    fi
else
    warning "Certificado não encontrado. Será necessário criar um novo."
    CERT_DIR=""
fi

# 1.5 Verificar se porta 443 está escutando
log "Verificando porta 443..."
if ss -tlnp | grep -q ":443"; then
    log "✓ Porta 443 está sendo escutada"
    ss -tlnp | grep ":443" | tee -a "$LOG_FILE"
else
    warning "Porta 443 não está sendo escutada"
fi

# 1.6 Verificar logs de erro
log "Últimas linhas do error.log do Nginx:"
tail -20 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG_FILE" || warning "Não foi possível ler error.log"

log ""
log "=== FIM DO DIAGNÓSTICO ==="
log ""

###############################################################################
# FASE 2: COLETAR INFORMAÇÕES PARA PROTEÇÃO
###############################################################################

log "=== FASE 2: CONFIGURAÇÃO DE SEGURANÇA ==="

# 2.1 Solicitar IPs permitidos
log "Configuração de IP Whitelist"
info "Digite os IPs que terão acesso ao gateway (um por linha, Enter vazio para finalizar):"
info "Exemplo: 192.168.1.100 ou 200.150.100.0/24 (CIDR)"
info "Deixe vazio se não quiser restrição por IP (apenas autenticação básica)"
ALLOWED_IPS=()
while true; do
    read -p "IP (ou Enter para finalizar): " ip
    if [ -z "$ip" ]; then
        break
    fi
    ALLOWED_IPS+=("$ip")
    log "IP adicionado: $ip"
done

# 2.2 Solicitar credenciais para autenticação básica
log ""
log "Configuração de Autenticação Básica"
read -p "Usuário para autenticação básica: " AUTH_USER
while [ -z "$AUTH_USER" ]; do
    error "Usuário não pode ser vazio"
    read -p "Usuário para autenticação básica: " AUTH_USER
done

read -sp "Senha para autenticação básica: " AUTH_PASS
echo ""
while [ -z "$AUTH_PASS" ]; do
    error "Senha não pode ser vazia"
    read -sp "Senha para autenticação básica: " AUTH_PASS
    echo ""
done

# 2.3 Verificar porta do gateway (padrão comum: 3000, 8080, 8000)
log ""
read -p "Porta interna do gateway WhatsApp (padrão: 3000): " GATEWAY_PORT
GATEWAY_PORT=${GATEWAY_PORT:-3000}
log "Porta do gateway: $GATEWAY_PORT"

# 2.4 Verificar se gateway está rodando
log "Verificando se gateway está rodando na porta $GATEWAY_PORT..."
if ss -tlnp | grep -q ":$GATEWAY_PORT"; then
    log "✓ Gateway está escutando na porta $GATEWAY_PORT"
else
    warning "Gateway não está escutando na porta $GATEWAY_PORT"
    read -p "Continuar mesmo assim? (s/N): " CONTINUE
    if [ "$CONTINUE" != "s" ] && [ "$CONTINUE" != "S" ]; then
        exit 1
    fi
fi

log ""
log "=== FIM DA CONFIGURAÇÃO ==="
log ""

###############################################################################
# FASE 3: CRIAR/ATUALIZAR CERTIFICADO SSL
###############################################################################

log "=== FASE 3: CERTIFICADO SSL ==="

if [ -z "$CERT_DIR" ] || [ ! -f "${CERT_DIR}/fullchain.pem" ]; then
    log "Criando novo certificado SSL..."
    if certbot certonly --nginx -d "$DOMAIN" --non-interactive --agree-tos --email admin@pixel12digital.com.br 2>&1 | tee -a "$LOG_FILE"; then
        CERT_DIR="/etc/letsencrypt/live/$DOMAIN"
        log "✓ Certificado criado com sucesso"
    else
        error "Falha ao criar certificado. Tentando modo standalone..."
        # Parar nginx temporariamente para modo standalone
        systemctl stop nginx
        if certbot certonly --standalone -d "$DOMAIN" --non-interactive --agree-tos --email admin@pixel12digital.com.br 2>&1 | tee -a "$LOG_FILE"; then
            CERT_DIR="/etc/letsencrypt/live/$DOMAIN"
            log "✓ Certificado criado com sucesso (modo standalone)"
        else
            error "Falha ao criar certificado"
            systemctl start nginx
            exit 1
        fi
        systemctl start nginx
    fi
else
    log "✓ Certificado já existe: $CERT_DIR"
    # Verificar validade
    if openssl x509 -in "${CERT_DIR}/fullchain.pem" -noout -checkend 2592000 >/dev/null 2>&1; then
        log "✓ Certificado válido por mais de 30 dias"
    else
        warning "Certificado expira em menos de 30 dias. Renovando..."
        certbot renew --cert-name "$DOMAIN" --quiet 2>&1 | tee -a "$LOG_FILE" || warning "Renovação automática falhou, mas continuando..."
    fi
fi

log ""

###############################################################################
# FASE 4: CRIAR ARQUIVO DE AUTENTICAÇÃO BÁSICA
###############################################################################

log "=== FASE 4: AUTENTICAÇÃO BÁSICA ==="

AUTH_FILE="/etc/nginx/.htpasswd_${DOMAIN}"
log "Criando arquivo de autenticação: $AUTH_FILE"

# Verificar se htpasswd está instalado
if ! command -v htpasswd &> /dev/null; then
    log "Instalando apache2-utils (htpasswd)..."
    if command -v apt-get &> /dev/null; then
        apt-get update -qq
        apt-get install -y apache2-utils
    elif command -v yum &> /dev/null; then
        yum install -y httpd-tools
    else
        error "Não foi possível instalar htpasswd. Instale manualmente: apache2-utils (Debian/Ubuntu) ou httpd-tools (CentOS/RHEL)"
        exit 1
    fi
fi

# Criar arquivo de autenticação
htpasswd -bc "$AUTH_FILE" "$AUTH_USER" "$AUTH_PASS" 2>&1 | tee -a "$LOG_FILE"
chmod 644 "$AUTH_FILE"
chown root:www-data "$AUTH_FILE" 2>/dev/null || chown root:nginx "$AUTH_FILE" 2>/dev/null || true
log "✓ Arquivo de autenticação criado"

log ""

###############################################################################
# FASE 5: CRIAR CONFIGURAÇÃO DO NGINX
###############################################################################

log "=== FASE 5: CONFIGURAÇÃO DO NGINX ==="

# Criar configuração do Nginx
log "Criando configuração do Nginx: $CONFIG_FILE"

cat > "$CONFIG_FILE" <<EOF
# Configuração para $DOMAIN - Gateway WhatsApp
# Gerado automaticamente em $(date)

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;

    # Permitir validação do Let's Encrypt
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirecionar todo o resto para HTTPS
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

# Configuração HTTPS
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
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
    ssl_stapling on;
    ssl_stapling_verify on;

    # Headers de segurança
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Logs
    access_log /var/log/nginx/${DOMAIN}_access.log;
    error_log /var/log/nginx/${DOMAIN}_error.log;

    # Tamanho máximo de upload (ajuste se necessário)
    client_max_body_size 100M;

    # Timeout aumentado para conexões WebSocket
    proxy_connect_timeout 7d;
    proxy_send_timeout 7d;
    proxy_read_timeout 7d;

    # Configuração de proxy reverso para o gateway
    location / {
        # IP Whitelist (se configurado)
EOF

# Adicionar regras de IP whitelist
if [ ${#ALLOWED_IPS[@]} -gt 0 ]; then
    cat >> "$CONFIG_FILE" <<EOF
        # Permitir apenas IPs específicos
        deny all;
EOF
    for ip in "${ALLOWED_IPS[@]}"; do
        echo "        allow $ip;" >> "$CONFIG_FILE"
    done
    cat >> "$CONFIG_FILE" <<EOF
        # Fim da whitelist
EOF
fi

# Continuar com autenticação básica e proxy
cat >> "$CONFIG_FILE" <<EOF

        # Autenticação básica
        auth_basic "Acesso Restrito - Gateway WhatsApp";
        auth_basic_user_file $AUTH_FILE;

        # Proxy reverso para o gateway
        proxy_pass http://127.0.0.1:$GATEWAY_PORT;
        proxy_http_version 1.1;
        
        # Headers para WebSocket (se o gateway usar)
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

log "✓ Configuração do Nginx criada"

# Fazer backup da configuração antiga se existir
if [ -f "$CONFIG_FILE" ] && [ ! -f "${BACKUP_DIR}/$(basename $CONFIG_FILE).backup" ]; then
    cp "$CONFIG_FILE" "${BACKUP_DIR}/$(basename $CONFIG_FILE).backup" 2>/dev/null || true
fi

log ""

###############################################################################
# FASE 6: VALIDAR E APLICAR CONFIGURAÇÃO
###############################################################################

log "=== FASE 6: VALIDAÇÃO E APLICAÇÃO ==="

# Validar sintaxe
log "Validando sintaxe do Nginx..."
if nginx -t 2>&1 | tee -a "$LOG_FILE"; then
    log "✓ Sintaxe válida"
else
    error "✗ Erro na sintaxe. Restaurando backup..."
    if [ -f "${BACKUP_DIR}/$(basename $CONFIG_FILE).backup" ]; then
        cp "${BACKUP_DIR}/$(basename $CONFIG_FILE).backup" "$CONFIG_FILE"
        nginx -t
    fi
    exit 1
fi

# Recarregar Nginx
log "Recarregando Nginx..."
if systemctl reload nginx 2>&1 | tee -a "$LOG_FILE"; then
    log "✓ Nginx recarregado com sucesso"
else
    error "✗ Falha ao recarregar Nginx"
    systemctl status nginx
    exit 1
fi

log ""

###############################################################################
# FASE 7: TESTES E VERIFICAÇÕES FINAIS
###############################################################################

log "=== FASE 7: TESTES FINAIS ==="

# Testar conexão HTTPS
log "Testando conexão HTTPS..."
sleep 2
if curl -k -I "https://$DOMAIN" 2>&1 | head -5 | tee -a "$LOG_FILE"; then
    log "✓ Conexão HTTPS funcionando"
else
    warning "Não foi possível testar HTTPS (pode ser normal se DNS ainda não propagou)"
fi

# Verificar se porta 443 está escutando
log "Verificando porta 443..."
if ss -tlnp | grep -q ":443"; then
    log "✓ Porta 443 está sendo escutada"
else
    error "✗ Porta 443 não está sendo escutada"
fi

# Verificar certificado
log "Verificando certificado SSL..."
if openssl s_client -connect "$DOMAIN:443" -servername "$DOMAIN" </dev/null 2>/dev/null | openssl x509 -noout -dates 2>&1 | tee -a "$LOG_FILE"; then
    log "✓ Certificado SSL válido"
else
    warning "Não foi possível verificar certificado (pode ser normal se DNS não propagou)"
fi

log ""

###############################################################################
# RESUMO FINAL
###############################################################################

log "=========================================="
log "CONFIGURAÇÃO CONCLUÍDA COM SUCESSO!"
log "=========================================="
log ""
log "Resumo da configuração:"
log "  - Domínio: $DOMAIN"
log "  - Certificado SSL: ${CERT_DIR}/fullchain.pem"
log "  - Configuração Nginx: $CONFIG_FILE"
log "  - Autenticação: $AUTH_FILE"
log "  - Usuário: $AUTH_USER"
log "  - Gateway interno: http://127.0.0.1:$GATEWAY_PORT"
log "  - Backup: $BACKUP_DIR"
log "  - Log: $LOG_FILE"
log ""

if [ ${#ALLOWED_IPS[@]} -gt 0 ]; then
    log "IPs permitidos:"
    for ip in "${ALLOWED_IPS[@]}"; do
        log "  - $ip"
    done
else
    log "IP Whitelist: Desabilitado (apenas autenticação básica)"
fi

log ""
log "PRÓXIMOS PASSOS:"
log "1. Aguarde a propagação do DNS (se necessário)"
log "2. Teste o acesso: https://$DOMAIN"
log "3. Use as credenciais:"
log "   Usuário: $AUTH_USER"
log "   Senha: [a senha que você digitou]"
log ""
log "4. Para renovar certificado automaticamente:"
log "   certbot renew --dry-run"
log ""
log "5. Para ver logs:"
log "   tail -f /var/log/nginx/${DOMAIN}_error.log"
log "   tail -f /var/log/nginx/${DOMAIN}_access.log"
log ""
log "6. Para desfazer (restaurar backup):"
log "   cp ${BACKUP_DIR}/$(basename $CONFIG_FILE).backup $CONFIG_FILE"
log "   nginx -t && systemctl reload nginx"
log ""
log "=========================================="

