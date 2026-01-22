# 🔐 Corrigir Senha de Autenticação

## ⚠️ Problema

O header `X-Server-Block: wpp-gateway` está aparecendo! ✅
A autenticação está funcionando! ✅
Mas a senha não está correta (password mismatch).

---

## 🛠️ Solução: Verificar/Recriar Senha

Execute:

```bash
# 1. Verificar se o arquivo de autenticação existe
ls -la /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 2. Ver usuários no arquivo (sem mostrar senha)
cat /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 3. Recriar senha para o usuário (você vai precisar digitar a senha)
htpasswd -c /etc/nginx/.htpasswd_wpp.pixel12digital.com.br "Los@ngo#081081"

# 4. Validar e recarregar Nginx
nginx -t && nginx -s reload

# 5. Testar novamente (substitua SUA_SENHA pela senha que você digitou)
curl -k -u "Los@ngo#081081:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
```

---

## ✅ Alternativa: Adicionar Novo Usuário

Se preferir criar um novo usuário:

```bash
# 1. Adicionar novo usuário (sem -c para não sobrescrever)
htpasswd /etc/nginx/.htpasswd_wpp.pixel12digital.com.br novo_usuario

# 2. Recarregar Nginx
nginx -s reload

# 3. Testar
curl -k -u "novo_usuario:senha" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🎯 Importante

- O `-c` no `htpasswd` cria um novo arquivo (sobrescreve)
- Sem `-c`, adiciona ao arquivo existente
- Use `-c` apenas na primeira vez ou para recriar

