#!/usr/bin/env bash
# usage: check.sh <url-path> <outfile>
CK=$(cat C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/ck_reports.txt)
D=C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl
r=$(curl -s -m 300 -o "$D/$2" -w '%{http_code} %{content_type} %{size_download} %{time_total}' -b "$CK" "http://127.0.0.1:8070/$1")
echo "$1 -> $r"
