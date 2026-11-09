#!/usr/bin/env bash
# Checks that the release ZIP carries the expected version in every place the plugin reads it (TX-450).
#   scripts/check-zip.sh release/terax-ai-assistant-1.0.1.zip 1.0.1
set -euo pipefail

zip="${1:?usage: check-zip.sh <zip> <version>}"
v="${2:?usage: check-zip.sh <zip> <version>}"
[ -f "$zip" ] || { echo "ZIP not found: $zip"; exit 1; }

# unzip on Linux (CI); bsdtar (tar) reads ZIPs on Windows.
show() {
  if command -v unzip >/dev/null 2>&1; then unzip -p "$zip" "terax-ai-assistant/$1" | tr -d '\r'; else tar -xOf "$zip" "terax-ai-assistant/$1" | tr -d '\r'; fi
}

fail=0
expect() { # file, fixed line text, label
  if show "$1" | grep -Fxq -- "$2"; then echo "ok    $3"; else echo "FAIL  $3 (expected: $2)"; fail=1; fi
}
expect_in() { # file, fixed substring, label
  if show "$1" | grep -Fq -- "$2"; then echo "ok    $3"; else echo "FAIL  $3 (expected: $2)"; fail=1; fi
}

expect terax-ai-assistant.php " * Version: $v" "plugin header Version: $v"
expect_in plugin.php "define( 'TERAX_AI_VERSION', '$v' )" "TERAX_AI_VERSION $v"
expect readme.txt "Stable tag: $v" "readme.txt Stable tag: $v"
expect readme.txt "= $v =" "readme.txt changelog entry for $v"

# The plugin must not ship development files.
if { if command -v unzip >/dev/null 2>&1; then unzip -Z1 "$zip"; else tar -tf "$zip"; fi; } | grep -Eq '(node_modules/|\.map$|/tests/|/src/|CHANGELOG\.md$)'; then
  echo "FAIL  the ZIP contains development files"; fail=1
else
  echo "ok    no development files in the ZIP"
fi

exit $fail
