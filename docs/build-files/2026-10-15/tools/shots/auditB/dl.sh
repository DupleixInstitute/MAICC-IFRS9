#!/bin/bash
D="C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/shots/auditB"
CK=$(cat "$D/ck.txt")
out="$D/dl/$1"
for i in 1 2 3; do
 r=$(curl -s --max-time 400 -b "$CK" -D "$out.hdr" -o "$out" -w "%{http_code}|%{content_type}|%{size_download}|%{time_total}s" "http://127.0.0.1:8072$2") && break; sleep 2; done
magic=$(head -c 6 "$out" | tr -c '[:print:]' '.')
fn=$(grep -i -o 'filename=[^;]*' "$out.hdr" | head -1 | tr -d '\r')
echo "$1 | $r | $magic | $fn"
