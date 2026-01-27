# === BLOCO 3: Backup, 60s→120s, nginx -t, reload ===
# Copiar este arquivo inteiro e colar no terminal da VPS (root ou com sudo).
FILE=/etc/nginx/sites-available/whatsapp-multichannel
sudo cp "$FILE" "${FILE}.bak.$(date +%Y%m%d_%H%M%S)"
echo "--- Backup criado ---"
ls -la "${FILE}.bak."* 2>/dev/null | tail -1
sudo sed -i -e 's/proxy_connect_timeout 60s;/proxy_connect_timeout 120s;/' -e 's/proxy_send_timeout 60s;/proxy_send_timeout 120s;/' -e 's/proxy_read_timeout 60s;/proxy_read_timeout 120s;/' "$FILE"
echo "--- Linhas com timeouts após alteração ---"
grep -n 'proxy_connect_timeout\|proxy_send_timeout\|proxy_read_timeout' "$FILE"
echo "--- nginx -t ---"
sudo nginx -t
echo "--- Reload Nginx ---"
sudo nginx -s reload 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || sudo service nginx reload
echo "BLOCO 3 concluído."
