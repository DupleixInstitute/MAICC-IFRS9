## 4. Installation on Windows with XAMPP

This topology is for evaluation, training and support workstations. It uses the PHP, Apache and MariaDB shipped with XAMPP.

### 4.1 XAMPP and PHP

1. Install XAMPP with PHP 8.2 to `C:\xampp`.
2. Open `C:\xampp\php\php.ini` and make sure these lines are present and not commented out:

```ini
extension=gd
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

5. Install Node.js 22 (22.12 or later) from nodejs.org.

### 4.2 Database

Start MySQL from the XAMPP Control Panel (or run `C:\xampp\mysql_start.bat`). Raise the buffer pool and packet size in `C:\xampp\mysql\bin\my.ini` (`innodb_buffer_pool_size=1G`, `max_allowed_packet=256M`; a bootstrapped database is about 1 GB) and restart MySQL. Create the database:

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

Edit `.env`: `APP_NAME="MAIIC IFRS 9"`, `DB_DATABASE=maiic_ifrs9`, `DB_USERNAME=root`, `DB_PASSWORD=` (empty), `BROADCAST_DRIVER=log`, `QUEUE_CONNECTION=database`. The repository also carries `.env.bootstrap.example` and `.env.demo.example`, two complete workstation files (databases `maiic_ifrs9_bootstrap` and `maiic_ifrs9_demo`) that can be copied instead. Then, once, on the empty database:

```powershell
php artisan migrate
php artisan db:seed
php artisan db:seed --class=MaiicCurrencySeeder
php artisan storage:link
```

If a database dump from MAIIC is being loaded instead, see chapter 10.3; on MariaDB raise the packet size and disable foreign key checks for the import as shown there. To load the committed E-Banker data, run the bootstrap of chapter 9.8 from this folder, with the memory it needs: `php -d memory_limit=4096M artisan eir:bootstrap ...`.

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

To refresh manual screenshots, set `MANUAL_SHOT_EMAIL`, `MANUAL_SHOT_PASSWORD` and `MANUAL_SHOT_CAPTCHA` in the local `.env` (the CAPTCHA bypass works only with `APP_ENV=local`) and run `php artisan config:clear`: the command reads the email, the password and `MAIIC_BROWSER_PATH` directly from the environment, which a cached configuration hides. Keep `php artisan serve` running and run `php artisan manual:screenshots`. It drives `scripts/manual-screenshots.cjs` with headless Chromium and writes the images to `public/manual/screenshots`. Useful options: `--only=<file keys>` for a few screens, `--theme=light|dark|system`, `--out=<folder>`, `--base-url=<url>`. The bundled Chromium is downloaded by `npm install`; if that was blocked, point `MAIIC_BROWSER_PATH` at `msedge.exe`, or pass `--edge=<path to msedge.exe or chrome.exe>`.

### 4.6 Known Windows issues

- Composer may fail once with "Permission denied" writing a temporary zip because of antivirus scanning; rerunning `composer install` resumes and completes.
- npm 11 rewrites `package-lock.json`; discard that change before committing (`git checkout -- package-lock.json`).
- Line endings: the repository uses LF and Git for Windows converts on checkout, which is harmless, except under `docs/bootstrap`: there `.gitattributes` turns conversion off for the CSV, JSON and SQL files so that their bytes match the SHA-256 in the manifests (chapter 3.3). Do not open and re-save those files in Excel.
