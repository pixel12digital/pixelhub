# 🔧 Remover Listen 80 e 443 do Nosso Arquivo

## ⚠️ Problema

O nosso arquivo tem `listen 80` configurado, mas a porta 80 já está em uso pelo AzuraCast. Precisamos remover.

---

## 🛠️ Solução: Remover Listen 80

Execute:

```bash
# 1. Ver configuração atual
cat /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf | grep -A 5 "listen 80"

# 2. Comentar ou remover o server block HTTP (porta 80)
# Como AzuraCast já usa 80, não precisamos dele
sed -i '/server {/,/^}$/ {
    /listen 80/ {
        :a
        N
        /^}$/!ba
        s/^/#/g
    }
}' /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 3. Ou simplesmente remover o server block HTTP completamente
# (Mais simples - não precisamos dele já que AzuraCast usa 80)
sed -i '/# Redirecionar HTTP para HTTPS/,/^}$/d' /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
sed -i '/server {/,/^}$/ {
    /listen 80/ {
        :a
        N
        /^}$/!ba
        d
    }
}' /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 4. Validar
nginx -t

# 5. Recarregar usando processo existente
nginx -s reload

# 6. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

---

## ✅ Solução Simples: Recriar Apenas HTTPS

Como não precisamos do HTTP (AzuraCast já usa), vamos recriar apenas o HTTPS:

```bash
# 1. Fazer backup
cp /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf.backup_$(date +%Y%m%d_%H%M%S)

# 2. Recriar apenas HTTPS (sem HTTP)
cat > /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf << 'EOF'
# Configuração para wpp.pixel12digital.com.br - Gateway WhatsApp
# Apenas HTTPS na porta 8443 (HTTP na 80 é do AzuraCast)

server {
    listen 8443 ssl http2 default_server;
    listen [::]:8443 ssl http2 default_server;
    server_name wpp.pixel12digital.com.br www.wpp.pixel12digital.com.br;

    ssl_certificate /etc/letsencrypt/live/wpp.pixel12digital.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/wpp.pixel12digital.com.br/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Server-Block "wpp-gateway" always;

    access_log /var/log/nginx/wpp.pixel12digital.com.br_access.log;
    error_log /var/log/nginx/wpp.pixel12digital.com.br_error.log;

    client_max_body_size 100M;

    proxy_connect_timeout 7d;
    proxy_send_timeout 7d;
    proxy_read_timeout 7d;

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

    location /.well-known/acme-challenge/ {
        root /var/www/html;
        allow all;
    }
}
EOF

# 3. Validar
nginx -t

# 4. Recarregar usando processo existente
nginx -s reload

# 5. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

