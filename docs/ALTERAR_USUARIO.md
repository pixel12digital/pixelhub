# 🔐 Alterar Nome de Usuário

## 🛠️ Adicionar Novo Usuário

Execute:

```bash
# 1. Adicionar novo usuário "wpp.pixel12" (sem -c para não sobrescrever)
htpasswd /etc/nginx/.htpasswd_wpp.pixel12digital.com.br wpp.pixel12

# 2. Verificar que foi adicionado
cat /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 3. Recarregar Nginx
nginx -s reload

# 4. Testar novo usuário
curl -k -u "wpp.pixel12:SUA_SENHA" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🗑️ Remover Usuário Antigo (Opcional)

Se quiser remover o usuário antigo "[USUARIO_REMOVIDO]":

```bash
# 1. Remover usuário antigo
htpasswd -D /etc/nginx/.htpasswd_wpp.pixel12digital.com.br "[USUARIO_REMOVIDO]"

# 2. Verificar
cat /etc/nginx/.htpasswd_wpp.pixel12digital.com.br

# 3. Recarregar Nginx
nginx -s reload
```

---

## ✅ Resultado

Após executar, você terá:
- **Usuário**: `wpp.pixel12`
- **Senha**: (a que você digitar no htpasswd)
- **URL**: `https://wpp.pixel12digital.com.br:8443`

