#!/bin/bash
# BLOCO AUTH-J — Caminho 2.2: Inversão de lógica de auth
# Basic Auth só em /ui/ e em location = /; location / e /api/ sem Basic Auth.
# Uso na VPS: copiar TODO o conteúdo e colar no terminal (como root).
# Requer: 00-wpp.pixel12digital.com.br.conf com location ^~ /api/ e location /

set -e
CONF="/etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf"

echo "=== AUTH-J1: Backup ==="
sudo cp "$CONF" "${CONF}.bak.$(date +%Y%m%d_%H%M%S)"
ls -la "${CONF}.bak."* 2>/dev/null | tail -1

echo ""
echo "=== AUTH-J2: Remover headers X-Location-Match (rollback AUTH-I) ==="
sudo sed -i '/add_header X-Location-Match "api" always;/d' "$CONF"
sudo sed -i '/add_header X-Location-Match "root" always;/d' "$CONF"

echo ""
echo "=== AUTH-J3: Inserir location /ui/ com auth ANTES de location / ==="
# Linha onde está "    location / {" (o catch-all, não "location = /")
LINE=$(grep -n '^    location / {$' "$CONF" | head -1 | cut -d: -f1)
if [ -z "$LINE" ]; then
  echo "ERRO: nao encontrou '    location / {' em $CONF"
  exit 1
fi
HEAD=$((LINE - 1))
# Bloco location /ui/ com auth (mesmo proxy que location /)
UI_BLOCK='    location /ui/ {
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

'
head -n "$HEAD" "$CONF" > /tmp/ngx_wpp_authj.conf
echo -n "$UI_BLOCK" >> /tmp/ngx_wpp_authj.conf
tail -n +"$LINE" "$CONF" >> /tmp/ngx_wpp_authj.conf
sudo cp /tmp/ngx_wpp_authj.conf "$CONF"
rm -f /tmp/ngx_wpp_authj.conf

echo ""
echo "=== AUTH-J4: Remover auth de location / (deixar auth_basic off) ==="
sudo sed -i '/^    location \/ {$/,/^    }$/ {
  s/^        auth_basic "Acesso Restrito - Gateway WhatsApp";$/        auth_basic off;/
  /^        auth_basic_user_file \/etc\/nginx\/.htpasswd_wpp\.pixel12digital\.com\.br;$/d
}' "$CONF"

echo ""
echo "=== AUTH-J5: Conferir locations /api/, /ui/, / ==="
grep -n "location.*/api/\|location /ui/\|location /" "$CONF" | head -15

echo ""
echo "=== AUTH-J6: nginx -t e reload ==="
sudo nginx -t && sudo service nginx reload

echo ""
echo "=== AUTH-J7: Verificacao — curl /api/messages (esperado: 200 ou 400, NAO 401) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 -X POST \
  -H "Host: wpp.pixel12digital.com.br" \
  -H "Content-Type: application/json" \
  -H "X-Gateway-Secret: FAKE_FOR_TEST" \
  "https://127.0.0.1:8443/api/messages"

echo ""
echo "=== AUTH-J8: Verificacao — /ui/ deve pedir Basic (401 sem credenciais) ==="
curl -sk -o /dev/null -w "http_code=%{http_code}\n" --max-redirs 0 "https://127.0.0.1:8443/ui/" \
  -H "Host: wpp.pixel12digital.com.br"

echo ""
echo "=== Concluido. Se AUTH-J7 mostrar 200 ou 400, /api esta liberado do Basic Auth. ==="
