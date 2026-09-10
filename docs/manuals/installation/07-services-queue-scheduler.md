## 7. Services: Queue Worker, Scheduler and Tuning

### 7.1 Queue worker as a systemd service

Imports, EIR calculations, revenue runs and LGD payment tracking run on the database queue and need a worker that is always up. Create `/etc/systemd/system/maiic-queue.service`:

```ini
[Unit]
Description=MAIIC IFRS 9 queue worker
After=network.target mariadb.service

[Service]
User=maiic
Group=www-data
WorkingDirectory=/var/www/maiic-ifrs9
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=7200 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/www/maiic-ifrs9/storage/logs/queue-worker.log
StandardError=append:/var/www/maiic-ifrs9/storage/logs/queue-worker.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now maiic-queue
sudo systemctl status maiic-queue
```

`--timeout=7200` matches the longest job (`ProcessLGDPayments`); `--max-time=3600` makes the worker exit and restart hourly so it always runs current code. After every deployment run `php artisan queue:restart`, which `scripts/deploy.sh` already does. One worker is enough for MAIIC's volumes; add a second unit if two long imports must overlap.

### 7.2 Scheduler

Laravel's scheduler needs one cron entry for the service user:

```bash
sudo crontab -u maiic -e
```

```
* * * * * cd /var/www/maiic-ifrs9 && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Today the schedule only carries legacy campaign and reminder commands; the monthly EIR revenue run is triggered by hand until the extract cadence is fixed (Technical Manual chapter 9).

### 7.3 PHP-FPM and OPcache

Apply the values in chapter 5.3. With 32 GB of memory the default PHP-FPM pool (`pm = dynamic`, `pm.max_children = 20`) is adequate; raise `pm.max_children` only if the access log shows queued requests.

### 7.4 MariaDB tuning

In `/etc/mysql/mariadb.conf.d/50-server.cnf` (or `my.ini` on Windows):

```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_log_file_size    = 256M
max_allowed_packet      = 256M
max_connections         = 150
character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci
```

Use a quarter to a half of available memory for the buffer pool on a dedicated database host; 2 GB is ample for the current database. `max_allowed_packet` must be raised before importing a dump. Restart MariaDB after the change.

### 7.5 Log rotation

Laravel's `daily` channel rotates its own log (`LOG_CHANNEL=daily`, 14 days by default). Rotate the worker log with logrotate, `/etc/logrotate.d/maiic-ifrs9`:

```
/var/www/maiic-ifrs9/storage/logs/queue-worker.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
```

### 7.6 Time synchronisation

Reporting periods and audit timestamps rely on the server clock. Keep `systemd-timesyncd` or `chrony` enabled and the timezone set to `Africa/Blantyre` in both the operating system and PHP.
