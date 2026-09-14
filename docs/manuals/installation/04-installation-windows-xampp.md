## 4. Installation on Windows with XAMPP

This topology is for evaluation, training and support workstations. It uses the PHP, Apache and MariaDB shipped with XAMPP.

### 4.1 XAMPP and PHP

1. Install XAMPP with PHP 8.2 to `C:\xampp`.
2. Open `C:\xampp\php\php.ini` and make sure these lines are present and not commented out:

```ini
extension=gd
extension=intl
extension=zip
extension=mbstring
extension=pdo_mysql
extension=fileinfo
extension=curl
extension=openssl
zend_extension=opcache
memory_limit=1G
```

3. Add `C:\xampp\php` to the user or system `Path` environment variable so `php` works in any terminal, then open a new terminal.
4. Install Composer into the PHP folder:

```powershell
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=C:\xampp\php --filename=composer.phar
```

and create `C:\xampp\php\composer.bat` containing:

```bat
@ECHO OFF
"%~dp0php.exe" "%~dp0composer.phar" %*
```

5. Install Node.js 20 or later from nodejs.org.

### 4.2 Database

Start MySQL from the XAMPP Control Panel (or run `C:\xampp\mysql_start.bat`). Raise the buffer pool in `C:\xampp\mysql\bin\my.ini` (`innodb_buffer_pool_size=512M`) and restart MySQL. Create the database:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE maiic_ifrs9 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

XAMPP's root user has no password by default; that is acceptable only on a private workstation.

### 4.3 Application

```powershell
cd C:\xampp\htdocs
git clone https://github.com/DupleixInstitute/MAICC-IFRS9.git
cd MAICC-IFRS9
composer install
npm install
npm run build
copy .env.example .env
php artisan key:generate
```

Edit `.env`: `APP_NAME="MAIIC IFRS 9"`, `DB_DATABASE=maiic_ifrs9`, `DB_USERNAME=root`, `DB_PASSWORD=` (empty), `QUEUE_CONNECTION=database`. Then:

```powershell
php artisan migrate
php artisan db:seed
php artisan db:seed --class=MaiicCurrencySeeder
php artisan storage:link
```

If a database dump from MAIIC is being loaded instead, see chapter 10.3; on MariaDB raise the packet size and disable foreign key checks for the import as shown there.

### 4.4 Running

The quickest way is the built-in server:

```powershell
php artisan serve
```

and browse to `http://127.0.0.1:8000`. For Apache instead, add a virtual host in `C:\xampp\apache\conf\extra\httpd-vhosts.conf` whose `DocumentRoot` is `C:/xampp/htdocs/MAICC-IFRS9/public` with `AllowOverride All`, add the hostname to the hosts file, and restart Apache.

Imports and EIR calculations need a queue worker even on a workstation. Open a second terminal:

```powershell
php artisan queue:work --sleep=3 --tries=3 --timeout=7200
```

### 4.5 Screenshot tooling

To refresh manual screenshots, set `MANUAL_SHOT_EMAIL`, `MANUAL_SHOT_PASSWORD` and `MANUAL_SHOT_CAPTCHA` in the local `.env` (the CAPTCHA bypass works only with `APP_ENV=local`), keep `php artisan serve` running, and run `php artisan manual:screenshots`. The bundled Chromium is downloaded by `npm install`; if that was blocked, point `MAIIC_BROWSER_PATH` at `msedge.exe`.

### 4.6 Known Windows issues

- Composer may fail once with "Permission denied" writing a temporary zip because of antivirus scanning; rerunning `composer install` resumes and completes.
- npm 11 rewrites `package-lock.json`; discard that change before committing (`git checkout -- package-lock.json`).
- Line endings: the repository uses LF; Git for Windows converts on checkout, which is harmless.
