## 7. Services: queue worker, scheduler and tuning

### 7.1 Queue worker as a systemd service

Imports, EIR calculations, revenue runs started from a screen, discounting and LGD payment tracking run on the database queue (`QUEUE_CONNECTION=database`, the `default` queue) and need a worker that is always up. The bootstrap and the Maintenance jobs (chapter 7.2) do not use the queue: the bootstrap runs everything synchronously, and the Maintenance screen starts each job as a detached console process. Create `/etc/systemd/system/maiic-queue.service`:

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

`--timeout=7200` matches the longest job (`ProcessLGDPayments`, 7,200 seconds; `CalculateDiscountingJob` allows 3,600, the EIR import, calculation and revenue jobs 600); `--max-time=3600` makes the worker exit and restart once it has run for an hour, after the job in hand, so it picks up new code. After every deployment run `php artisan queue:restart`, which `scripts/deploy.sh` already does.

Run one worker only. `config/queue.php` gives the `database` connection `retry_after` of 90 seconds, so with a second worker any job still running after 90 seconds would be taken again by the other worker and run twice. A second unit is safe only after `retry_after` has been raised above the longest job's timeout in a release.

### 7.2 Scheduler

Laravel's scheduler needs one cron entry for the service user:

```bash
sudo crontab -u maiic -e
```

```
* * * * * cd /var/www/maiic-ifrs9 && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

`app/Console/Kernel.php` schedules:

| When | Command | What it does |
|---|---|---|
| Daily at 06:30, never two at once | `eir:poll-feed-folder --also-route-3` | Lands every new E-Banker pack in `EBANKER_FEED_FOLDER` (route 2) and in the inbox the API fills (route 3, `storage/app/ebanker-inbox`) through the gates. With no folder set, or a folder that does not exist, it warns and lands nothing, not even the API inbox |
| The 2nd of each month at 07:00 | `macro:import-worldbank` | Fetches the World Bank series live; a series it cannot reach comes from the committed snapshot in `docs/bootstrap/macro` |
| Daily, every five minutes, every two minutes | `campaigns:process-recurring`, `campaigns:process-scheduled`, `reminders:loan-applications` | Legacy commands of the credit scoring platform (communication campaigns and loan application reminders), modules MAIIC does not use |

`php artisan schedule:list` shows the same list with the next run times.

The month-end chain is not scheduled. It is started from Administration & support, Support & Maintenance, Maintenance, Month-end tab ("Run the month-end calculations", which runs `maiic:task month-end`) or by hand (chapter 9.9 and Technical Manual chapter 9). The Maintenance screen runs one job at a time as a detached process of the web server user with `php -d memory_limit=4096M`, and keeps each job's output under `storage/app/command-runs`; that user must be able to write there and run `php`.

### 7.3 PHP-FPM and OPcache

Apply the values in chapter 5.3. With 32 GB of memory the default PHP-FPM pool (`pm = dynamic`, `pm.max_children = 20`) is adequate; raise `pm.max_children` only if the access log shows queued requests. Bear in mind that the screens which read the whole E-Banker ledger raise their own request to 4 GB (chapter 6.1), so a few of them open at once, beside a running month-end job, can use a large share of the server's memory.

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

Use a quarter to a half of available memory for the buffer pool on a dedicated database host; 2 GB is ample for the current database (about 1 GB after the bootstrap). `max_allowed_packet` must be raised before importing a dump. Restart MariaDB after the change.

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
