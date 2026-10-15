#!/usr/bin/env bash
# usage: shoot.sh prefix path1 path2 ...
TW="C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools"
P=$1; shift
for u in "$@"; do n=$(echo "$u" | tr '/?=&' '____'); echo -n "$n "; node $TW/shot.cjs $TW/c_model.json $TW/shots/model/${P}_$n.png "$u" 1440 2>&1 | cut -c1-400; done
