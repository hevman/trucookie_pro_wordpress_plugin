#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_MAIN="$ROOT_DIR/trucookie-cmp.php"
PLUGIN_MAIN_FOR_PHP="$PLUGIN_MAIN"

echo "== TruCookie plugin smoke =="
PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" ]]; then
  if command -v php >/dev/null 2>&1; then
    PHP_BIN="php"
  elif command -v php.exe >/dev/null 2>&1; then
    PHP_BIN="php.exe"
  else
    echo "php binary not found. Set PHP_BIN to your php executable path."
    exit 1
  fi
fi

if [[ "$PHP_BIN" =~ php\.exe$ ]]; then
  if command -v wslpath >/dev/null 2>&1; then
    PLUGIN_MAIN_FOR_PHP="$(wslpath -w "$PLUGIN_MAIN")"
  elif command -v cygpath >/dev/null 2>&1; then
    PLUGIN_MAIN_FOR_PHP="$(cygpath -w "$PLUGIN_MAIN")"
  fi
fi

"$PHP_BIN" -l "$PLUGIN_MAIN_FOR_PHP"

if grep -RIn --exclude-dir=assets "https://cmp.markets\\|https://www.cmp.markets" "$ROOT_DIR" >/dev/null 2>&1; then
  echo "Found hardcoded legacy cmp.markets URL in plugin source."
  exit 1
fi

if ! grep -q "DEFAULT_SERVICE_URL = 'https://trucookie.pro'" "$PLUGIN_MAIN"; then
  echo "Expected DEFAULT_SERVICE_URL to point to trucookie.pro"
  exit 1
fi

if find "$ROOT_DIR" -type f -name "*.svg" | grep -q .; then
  echo "Found SVG files in plugin package (WordPress.org review risk)."
  exit 1
fi

echo "Smoke checks passed."
