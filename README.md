# IluminusTech — Backend Supabase

## Estrutura de arquivos

```
/
├── config.php              ← Credenciais (Supabase, Brevo, hCaptcha)
├── supabase.php            ← Classe cliente da API REST do Supabase
├── session_check.php       ← Middleware de sessão PHP
├── login.php               ← Autenticação via tabela "usuarios"
├── enviar_codigo.php       ← Gera código e envia e-mail (Brevo)
├── verificar_codigo.php    ← Valida código contra o banco
├── alterar_senha.php       ← Atualiza senha_hash no Supabase
├── sistema.php             ← Logout / ações da área logada
├── supabase_extras.sql     ← Funções e índices extras (execute no Supabase)
└── assets/
    └── js/
        └── script.js       ← JS do fluxo de recuperação de senha
```

---

## Passo a passo de configuração

### 1 — Banco de dados (Supabase)

1. Acesse [supabase.com](https://supabase.com) e crie um projeto.
2. Vá em **SQL Editor** e execute o schema principal (`schema.sql`).
3. Execute também o `supabase_extras.sql` para criar funções e índices extras.

### 2 — Credenciais

Edite `config.php` com os dados do seu projeto:

```php
// Supabase → Settings > API
define('SUPABASE_URL',         'https://XXXX.supabase.co');
define('SUPABASE_SERVICE_KEY', 'eyJ...');   // service_role key (nunca no frontend!)
define('SUPABASE_ANON_KEY',    'eyJ...');

// Brevo → My account > SMTP & API > API Keys
define('BREVO_API_KEY',        'xkeysib-...');
define('BREVO_SENDER_EMAIL',   'voce@seudominio.com.br');

// hCaptcha → hcaptcha.com > Sites > Secret Key
define('HCAPTCHA_SECRET',      'ES_...');
```

### 3 — Compatibilidade de hash bcrypt

O schema SQL usa `crypt('senha', gen_salt('bf'))` do pgcrypto para gerar hashes  
no formato `$2a$...`. O PHP verifica esses hashes com `password_verify()`,  
que é compatível com o formato `$2a$` (Blowfish/bcrypt). ✅

Ao **alterar** a senha pelo PHP (`alterar_senha.php`), o novo hash é gerado com  
`password_hash($senha, PASSWORD_BCRYPT)` → formato `$2y$`, que o PostgreSQL  
também aceita no `crypt()`. ✅

### 4 — Requisitos do servidor PHP

| Requisito   | Versão mínima |
|-------------|---------------|
| PHP         | 8.1+          |
| Extensões   | `curl`, `json`, `session`, `openssl` |

### 5 — Segurança em produção

- ✅ Nunca exponha `SUPABASE_SERVICE_KEY` no frontend
- ✅ Use HTTPS (o cookie de sessão tem `secure: true` automaticamente em HTTPS)
- ✅ Ajuste as políticas RLS no Supabase conforme o grupo de usuário
- ✅ Rode `limpar_codigos_expirados()` periodicamente (pg_cron ou cron externo)
- ✅ Altere a senha do usuário `admin` padrão imediatamente em produção

---

## Fluxo de recuperação de senha

```
recuperar.html
  └─→ POST enviar_codigo.php   (salva na tabela recuperacao_senha, envia e-mail)
        └─→ verificar_codigo.html
              └─→ POST verificar_codigo.php  (busca no banco, marca como usado)
                    └─→ nova_senha.html
                          └─→ POST alterar_senha.php  (atualiza senha_hash)
                                └─→ login.html
```

---

## Fluxo de login

```
login.html
  └─→ POST login.php
        ├─ Verifica hCaptcha
        ├─ Busca usuário no Supabase (tabela "usuarios")
        ├─ Valida bcrypt com password_verify()
        └─ Abre sessão PHP → redireciona para sistema.html
```

---

## Logout

O `sistema.html` faz duas chamadas:

1. `POST sistema.php` com `acao=logout_confirm` → exibe SweetAlert de confirmação
2. `POST sistema.php` com `acao=logout` → destrói a sessão e redireciona para login

Atualize o JavaScript do `sistema.html` para enviar o campo `acao`:

```js
// Confirmar logout
const resposta = await fetch("sistema.php", {
    method: "POST",
    body: new URLSearchParams({ acao: "logout_confirm" })
});

// Efetivar logout (após confirmação do SweetAlert)
const resposta = await fetch("sistema.php", {
    method: "POST",
    body: new URLSearchParams({ acao: "logout" })
});
```
