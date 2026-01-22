# 🔧 Recarregar Nginx Usando PID Correto

## ⚠️ Problema

O `nginx -s reload` falhou porque o PID file está incorreto. O processo está rodando, mas o systemd não consegue gerenciá-lo.

---

## 🛠️ Solução: Recarregar Usando PID Direto

Execute:

```bash
# 1. Encontrar o PID do master process do Nginx
MASTER_PID=$(ps aux | grep "nginx: master process" | grep -v grep | awk '{print $2}')

# 2. Verificar se encontrou
echo "Master PID: $MASTER_PID"

# 3. Recarregar usando o PID direto
kill -HUP $MASTER_PID

# 4. Verificar se funcionou
sleep 2
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

---

## ✅ Alternativa: Reiniciar Processo do Nginx

Se o reload não funcionar, podemos reiniciar o processo:

```bash
# 1. Matar processo atual
pkill nginx

# 2. Iniciar Nginx (sem systemd, direto)
nginx

# 3. Verificar se está rodando
ps aux | grep nginx | grep -v grep

# 4. Testar
curl -k -I https://wpp.pixel12digital.com.br:8443 | grep -E "X-Server-Block|401|200"
```

---

## 🔍 Verificar Se Há Outros Arquivos Usando 80/443

Antes de reiniciar, verifique se há outros arquivos tentando usar essas portas:

```bash
# Ver todos os arquivos tentando usar 80 ou 443
grep -r "listen 80\|listen 443" /etc/nginx/conf.d/ /etc/nginx/sites-enabled/ 2>/dev/null | grep -v "8443"
```

