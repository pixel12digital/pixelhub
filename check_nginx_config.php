<?php
// Script para verificar configuração do NGINX
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificação da Configuração NGINX</h1>";

echo "<h2>Comandos para executar no servidor:</h2>";
echo "<pre><code># 1. Encontrar arquivos de configuração NGINX
find /etc -name \"*.conf\" | grep nginx 2>/dev/null
find /usr -name \"nginx.conf\" 2>/dev/null
find /home -name \"*.conf\" | grep nginx 2>/dev/null

# 2. Verificar configuração principal
cat /etc/nginx/nginx.conf

# 3. Verificar configuração do site
cat /etc/nginx/sites-available/default
cat /etc/nginx/sites-available/hub.pixel12digital.com.br
cat /etc/nginx/conf.d/*.conf

# 4. Verificar se há configuração para o domínio
ls -la /etc/nginx/sites-enabled/
ls -la /etc/nginx/sites-available/

# 5. Testar configuração NGINX
/usr/sbin/nginx -t

# 6. Verificar processo NGINX
ps aux | grep nginx | grep -v grep

# 7. Verificar logs em locais alternativos
find /var/log -name \"*nginx*\" 2>/dev/null
find /home -name \"*nginx*\" 2>/dev/null
find /usr -name \"*nginx*\" 2>/dev/null

# 8. Verificar se PHP-FPM está rodando
ps aux | grep php-fpm
ps aux | grep php-cgi</code></pre>";

echo "<h2>Problema Provável:</h2>";
echo "<p>O NGINX não está configurado para processar .htaccess ou para fazer rewrite das URLs.</p>";
echo "<p>O NGINX precisa de configuração explícita para funcionar como o Apache.</p>";

echo "<h2>Solução Possível:</h2>";
echo "<p>Precisamos adicionar no NGINX algo como:</p>";
echo "<pre><code>location / {
    try_files \$uri \$uri/ /index.php?\$query_string;
}

location ~ \\.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    include fastcgi_params;
}</code></pre>";

echo "<h2>Teste Importante:</h2>";
echo "<pre><code># Testar se o PHP está funcionando diretamente
curl \"https://hub.pixel12digital.com.br/index.php\"

# Testar com parâmetro
curl \"https://hub.pixel12digital.com.br/index.php?url=/opportunities\"</code></pre>";

?>
