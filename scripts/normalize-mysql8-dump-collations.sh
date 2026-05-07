#!/usr/bin/env bash
# Convierte collations exclusivas de MySQL 8 (utf8mb4_0900_*) a utf8mb4_unicode_ci
# para importar en MariaDB o MySQL < 8 sin error #1273 (collation desconocida).
set -euo pipefail
SRC="${1:-}"
DST="${2:-}"
if [[ -z "$SRC" || ! -f "$SRC" ]]; then
  echo "Uso: $0 /ruta/dump.sql [/ruta/salida.sql]" >&2
  echo "  Si omites salida: mismo directorio, nombre base_mariadb.sql" >&2
  exit 1
fi
if [[ -z "$DST" ]]; then
  DIR=$(dirname "$SRC")
  BASE=$(basename "$SRC" .sql)
  DST="${DIR}/${BASE}_mariadb.sql"
fi
sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
    -e 's/utf8mb4_0900_bin/utf8mb4_bin/g' \
    "$SRC" >"$DST"
echo "Escrito: $DST"
