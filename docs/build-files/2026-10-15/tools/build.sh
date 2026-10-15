#!/usr/bin/env bash
# Serialised asset build for MAICC-IFRS9: only one `npm run build` at a time.
LOCK="C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/build.lock"
for i in $(seq 1 120); do
  if mkdir "$LOCK" 2>/dev/null; then
    trap 'rmdir "$LOCK" 2>/dev/null' EXIT
    cd /c/xampp/htdocs/MAICC-IFRS9 && npm run build 2>&1 | grep -E "built in|error|Error|file:" | head -20
    exit 0
  fi
  node -e "setTimeout(()=>{},5000)"
done
echo "build lock held too long" >&2; exit 1
