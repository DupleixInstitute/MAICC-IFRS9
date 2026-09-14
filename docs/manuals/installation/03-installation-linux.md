## 3. Installation on Ubuntu 22.04

All commands assume the application will live in `/var/www/maiic-ifrs9` and be served by Nginx with PHP-FPM. Run as a user with sudo.

### 3.1 Packages

```bash
sudo apt update
sudo apt install -y software-properties-common ca-certificates curl git unzip
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath \
  php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-opcache
sudo apt install -y nginx mariadb-server
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

### 3.2 Database

```bash
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE maiic_ifrs9 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'maiic'@'localhost' IDENTIFIED BY 'choose-a-strong-password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON maiic_ifrs9.* TO 'maiic'@'localhost'; FLUSH PRIVILEGES;"
```

Then apply the tuning in chapter 7.4 (buffer pool and packet size) before importing any data.

### 3.3 Application files

```bash
sudo mkdir -p /var/www/maiic-ifrs9
sudo chown $USER:www-data /var/www/maiic-ifrs9
git clone --branch master https://github.com/DupleixInstitute/MAICC-IFRS9.git /var/www/maiic-ifrs9
cd /var/www/maiic-ifrs9
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Production deploys from `master`. Feature work happens on branches (for example `eir_revenue_recognition`) and reaches master through pull requests, so a production clone should never check out a feature branch.

### 3.4 Environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` following chapter 5. At minimum set `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, the `DB_*` values, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` and the mail settings. Leave `FORCE_HTTPS` and `HSTS_ENABLED` at `false` until the certificate is installed (chapter 6).

### 3.5 Permissions and storage

```bash
sudo chown -R $USER:www-data /var/www/maiic-ifrs9
sudo chmod -R 775 storage bootstrap/cache
php artisan storage:link
```

`storage/app/public` holds uploaded manual figures and failed-import files; `storage:link` exposes it as `public/storage`.

### 3.6 Schema and seed data

```bash
php artisan migrate --force
php artisan db:seed --force
```

`DatabaseSeeder` runs, in order: countries, permissions, roles, ticket permissions, admin module permissions (financial periods, currencies), the legacy permission cleanup, the User Manual and Administrator Manual content, the administrator user, currencies, timezones, settings, branches, legal types, sector types, chart of accounts, credit loss definitions and scenario sets. Every seeder used here is idempotent: rerunning `db:seed` does not duplicate rows and never overwrites manual content that has been edited in the application. After seeding, set MWK as the organisation currency:

```bash
php artisan db:seed --class=MaiicCurrencySeeder --force
```

If MAIIC's existing database is being migrated rather than starting empty, follow chapter 10.3 (restore) instead of seeding a fresh database, then run `migrate` and the seeders listed there.

### 3.7 Caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Repeat these after any change to `.env` or a deployment. `scripts/deploy.sh` does this automatically.

### 3.8 Verify

```bash
php artisan --version
php artisan migrate:status | tail -5
php artisan route:list --name=help.index
```

Then configure the web server (chapter 6) and the services (chapter 7), and continue with the first-run checklist (chapter 8).
