# 🔧 Corrigir Problema de Portas 80 e 443

## ⚠️ Problema

O Nginx não consegue iniciar porque as portas 80 e 443 já estão em uso pelo AzuraCast (Docker). A sintaxe está OK, mas o `restart` falha.

---

## 🛠️ Solução: Usar Reload ao Invés de Restart

O `reload` funciona porque não tenta fazer bind novamente:

```bash
# 1. Verificar se Nginx está rodando (pode estar em estado parcial)
ps aux | grep nginx

# 2. Se não estiver rodando, iniciar
systemctl start nginx

# 3. Se falhar, usar reload (não restart)
systemctl reload nginx

# 4. Verificar status
systemctl status nginx
```

---

## 🔧 Verificar Configurações nas Portas 80 e 443

Se ainda falhar, verifique se há configurações tentando usar essas portas:

```bash
# 1. Ver todas as configurações tentando usar porta 80
grep -r "listen 80" /etc/nginx/conf.d/ /etc/nginx/sites-enabled/ 2>/dev/null

# 2. Ver todas as configurações tentando usar porta 443
grep -r "listen 443" /etc/nginx/conf.d/ /etc/nginx/sites-enabled/ 2>/dev/null

# 3. Ver se nosso arquivo tem listen 80 ou 443
grep "listen" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf
```

---

## ✅ Solução: Remover Listen 80 e 443 do Nosso Arquivo

Se nosso arquivo tiver `listen 80` ou `listen 443`, remova (AzuraCast já usa essas portas):

```bash
# 1. Ver configuração atual
grep "listen" /etc/nginx/conf.d/00-wpp.pixel12digital.com.br.conf

# 2. Se tiver listen 80 ou 443, comentar ou remover
# (Mas provavelmente não tem, já que configuramos para 8443)
```

---

## 🎯 Prioridade: Restaurar Nginx

Primeiro, vamos fazer o Nginx funcionar novamente:

```bash
# 1. Verificar processos do Nginx
ps aux | grep nginx

# 2. Se houver processos, matar e reiniciar
pkill nginx
systemctl start nginx

# 3. Se não funcionar, usar reload
systemctl reload nginx 2>/dev/null || systemctl start nginx
```

