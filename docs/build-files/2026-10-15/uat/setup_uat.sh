#!/usr/bin/env bash
set -e
M=C:/xampp/mysql/bin
$M/mysql -uroot -e "DROP DATABASE IF EXISTS maiic_ifrs9_uat; CREATE DATABASE maiic_ifrs9_uat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
$M/mysqldump -uroot --single-transaction --quick --routines maiic_ifrs9_results2 | $M/mysql -uroot maiic_ifrs9_uat
cd /c/xampp/htdocs/MAICC-IFRS9 && DB_DATABASE=maiic_ifrs9_uat php artisan tinker --execute="$(cat C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/uat/mkusers.php)" < /dev/null
