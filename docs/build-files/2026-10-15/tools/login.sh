#!/usr/bin/env bash
# Usage: login.sh <cookie.json path>  -> creates a logged-in session (admin) for http://127.0.0.1:8070
T="C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools"
cd /c/xampp/htdocs/MAICC-IFRS9 && OUT="$1" timeout 60 php artisan tinker --execute="$(cat $T/mkcookie.php)" < /dev/null 2>&1 | tail -1
