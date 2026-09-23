# humanaClinica (ProjetoSenhas)

Sistema de senhas / filas para clínicas (Laravel + Livewire + Vite).

## Requisitos

- PHP **8.3+** (extensões: `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `curl`, `zip`, `gd` ou equivalente, `intl` recomendado)
- Composer 2
- Node.js 20+ (build do frontend)
- MariaDB 10.11+ / MySQL 8+ (produção) ou SQLite (desenvolvimento local)

## Desenvolvimento local

```bash
composer install
cp .env.example .env
php artisan key:generate
# Com DB_CONNECTION=sqlite (padrão do .env.example):
touch database/database.sqlite
php artisan migrate
# Opcional — cria admin local (bloqueado em APP_ENV=production):
# preencha BOOTSTRAP_ADMIN_EMAIL e BOOTSTRAP_ADMIN_PASSWORD no .env
php artisan db:seed
npm install
npm run dev
php artisan serve
```

Ou use `composer run setup` / `composer run dev` conforme `composer.json`.

## Deploy em produção (VPS)

O document root do Nginx deve apontar **apenas** para a pasta `public/` deste projeto (ex.: `/var/www/humanaSaude/public`). Nunca exponha a raiz do repositório.

`public/build` **não** é versionado: o build Vite roda no deploy.

### 1. Código e dependências PHP

```bash
cd /var/www/humanaSaude
git clone https://github.com/restritodk/ProjetoSenhas.git .
composer install --no-dev --optimize-autoloader
```

### 2. Ambiente

```bash
cp .env.example .env
# Edite .env manualmente na VPS — nunca commite segredos:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_URL=https://seu-dominio
#   APP_KEY= (gerar no passo seguinte)
#   DB_CONNECTION=mariadb (+ host, database, username, password)
#   QUEUE_CONNECTION=sync
#   SESSION_SECURE_COOKIE=true
#   LOG_LEVEL=warning
php artisan key:generate
```

### 3. Banco de dados

Crie o banco/usuário MariaDB na VPS e então:

```bash
php artisan migrate --force
```

Não use `migrate:fresh` / `migrate:refresh` em produção.

### 4. Frontend (Vite)

```bash
npm ci
npm run build
```

### 5. Storage e caches

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permissões (ajuste o usuário do PHP-FPM, tipicamente `www-data`):

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
```

### 6. Primeiro administrador (one-shot)

Não há seed automático de admin em produção. Após o migrate, execute **uma vez**:

```bash
php artisan app:bootstrap-first-administrator --email=admin@sua-clinica.com
```

O comando pedirá a senha de forma oculta no terminal (mín. 8 caracteres).  
Alternativa não interativa (a senha pode ficar no histórico do shell — evite quando possível):

```bash
php artisan app:bootstrap-first-administrator \
  --email=admin@sua-clinica.com \
  --password='SENHA_FORTE' \
  --name='Administrador' \
  --clinic-name='Humana Saúde' \
  --clinic-slug=humana-saude \
  --unit-name='Hospital Toledo' \
  --unit-slug=hospital-toledo
```

O comando:

- cria clínica + unidade iniciais (se necessário), tipos de senha padrão e permissões;
- **recusa** executar se já existir qualquer administrador;
- **não** roda em migrations nem no deploy automático;
- **não** usa senha padrão nem lê `BOOTSTRAP_ADMIN_PASSWORD` do `.env`.

Administradores adicionais devem ser criados pelo painel (Usuários).

### 7. Filas / WebSockets / Print Agent

- **Filas:** sem Jobs obrigatórios hoje — mantenha `QUEUE_CONNECTION=sync`. Não é necessário Supervisor/worker neste momento.
- **TV:** polling Livewire (sem Reverb/Pusher).
- **Print Agent:** aplicativo Windows nas estações do Totem (`tools/humana-print-agent`). Não instale na VPS Linux.

## Uploads de mídia

Imagens e vídeos da TV usam o disco `public` (via `storage:link`).  
Vídeos aceitam até **100 MB** no aplicativo. Na infraestrutura, configure PHP e Nginx para pelo menos **110 MB** (`upload_max_filesize`, `post_max_size`, `client_max_body_size`). Isso é feito na VPS, não neste repositório.

## Testes

```bash
php artisan test --compact
npm run build
```

## Segurança

- Não versionar `.env`, chaves, tokens ou backups.
- `APP_DEBUG=false` em produção.
- Document root = `public/` apenas.
- Revogue tokens/PATs se forem expostos em chats ou logs.
