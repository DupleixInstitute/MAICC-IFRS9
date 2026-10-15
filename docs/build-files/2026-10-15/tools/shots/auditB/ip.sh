#!/bin/bash
# ip.sh <path>  -> Inertia JSON of a page (via curl with my session cookie)
D="$(dirname "$0")"
N=$(node -e "const c=require('$D/../../c_auditB.json');process.stdout.write(c.name)")
V=$(node -e "const c=require('$D/../../c_auditB.json');process.stdout.write(c.value)")
VER=$(curl -s -b "$N=$V" http://127.0.0.1:8072/user/role | grep -o "&quot;version&quot;:&quot;[^&]*" | sed "s/.*;//")
for i in 1 2 3 4; do
  out=$(curl -s --max-time 300 -b "$N=$V" -H "X-Inertia: true" -H "X-Inertia-Version: $VER" -H "X-Requested-With: XMLHttpRequest" -H "Accept: text/html, application/xhtml+xml" -w "\n%{http_code}" "http://127.0.0.1:8072$1") && break
  sleep 1
done
echo "$out"
