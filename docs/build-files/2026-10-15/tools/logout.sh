#!/usr/bin/env bash
# Usage: logout.sh <cookie.json path> -> deletes the session row created by login.sh
ID=$(node -e "console.log(require(process.argv[1]).id)" "$1")
"C:/xampp/mysql/bin/mysql.exe" -uroot maiic_ifrs9_demo -e "DELETE FROM sessions WHERE id='$ID'" && rm -f "$1" && echo "session removed"
