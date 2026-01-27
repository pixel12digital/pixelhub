#!/bin/bash
# Bloco único para o Charles colar no terminal da VPS.
# Cria o script patch-nginx-liberar-api-basic-auth.sh e executa.
# Copiar TODO o conteúdo deste arquivo e colar no terminal (como root ou com sudo).

cat > ~/patch-nginx-liberar-api-basic-auth.sh << 'ENDOFSCRIPT'
#!/bin/bash
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
echo "=== Localizar linha do fechamento de location = / ==="
INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^[[:space:]]*\}[[:space:]]*$/{print NR; exit}' "$CONF" 2>/dev/null || true)
if [ -z "$INSERT_AFTER" ]; then
  INSERT_AFTER=$(awk '/return 302 \/ui\//{found=1; next} found && /^    \}$/{print NR; exit}' "$CONF" 2>/dev/null || true)
fi
if [ -z "$INSERT_AFTER" ]; then
  echo "ERRO: Nao foi possivel localizar o fechamento do bloco location = /"
  exit 1
fi
echo "Inserir apos linha: $INSERT_AFTER"

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

sudo sed -i "${INSERT_AFTER}r $PATCH_FILE" "$CONF"
rm -f "$PATCH_FILE"

echo ""
echo "=== Conferir location /api/ ==="
grep -n "location /api/" "$CONF"

echo ""
echo "=== nginx -t ==="
sudo nginx -t

echo ""
echo "=== service nginx reload ==="
sudo service nginx reload

echo ""
echo "=== Concluido. Retestar audio pelo Hub. ==="
ENDOFSCRIPT

chmod +x ~/patch-nginx-liberar-api-basic-auth.sh
sudo bash ~/patch-nginx-liberar-api-basic-auth.sh
