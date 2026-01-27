#!/bin/bash
#
# Aplica patch no Nginx para liberar /api/* do Basic Auth (vhost 8443).
# Uso na VPS: sudo bash patch-nginx-liberar-api-basic-auth.sh
# Requer: arquivo /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
#         com bloco "location = /" que contém "return 302 /ui/;"
#
set -e
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

if [ ! -f "$CONF" ]; then
  echo "ERRO: Arquivo não encontrado: $CONF"
  exit 1
fi

if grep -q "location /api/" "$CONF"; then
  echo "AVISO: location /api/ já existe. Nada a fazer."
  grep -n "location /api/" "$CONF"
  exit 0
fi

echo "=== Backup ==="
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
ls -la "${CONF}.bak."* 2>/dev/null | tail -1

echo ""
echo "=== Localizar linha do fechamento de 'location = /' (após 'return 302 /ui/;') ==="
# Linha do "}" que fecha "location = /" (primeira linha só com } após "return 302 /ui/;")
INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^[[:space:]]*\}[[:space:]]*$/{print NR; exit}' "$CONF" 2>/dev/null || true)
if [ -z "$INSERT_AFTER" ]; then
  # Fallback: } com 4 espaços, comum em nginx
  INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^    \}$/{print NR; exit}' "$CONF" 2>/dev/null || true)
fi
if [ -z "$INSERT_AFTER" ]; then
  echo "ERRO: Não foi possível localizar o fechamento do bloco 'location = /'."
  echo "      Verifique se o arquivo contém 'return 302 /ui/;' e um '}' logo em seguida."
  exit 1
fi
echo "Inserir bloco location /api/ após linha: $INSERT_AFTER"

echo ""
echo "=== Conteúdo do bloco a inserir ==="
PATCH_FILE=$(mktemp)
cat > "$PATCH_FILE" << 'PATCHEOF'

    location /api/ {
        auth_basic off;

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

PATCHEOF

echo "Inserindo após linha $INSERT_AFTER..."
sudo sed -i "${INSERT_AFTER}r $PATCH_FILE" "$CONF"
rm -f "$PATCH_FILE"

echo ""
echo "=== Conferir se location /api/ foi inserido ==="
grep -n "location /api/" "$CONF" || { echo "ERRO: Inserção falhou."; exit 1; }

echo ""
echo "=== Testar configuração Nginx ==="
sudo nginx -t

echo ""
echo "=== Reload Nginx (srv817568: service nginx reload) ==="
sudo service nginx reload

echo ""
echo "=== Concluído. Retestar áudio pelo Hub. ==="
