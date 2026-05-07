#!/usr/bin/env bash
# Inserta el DDL faltante para asignacion_t y asignar_video_a_t al inicio del cuerpo del dump
# (después de los SET iniciales de mysqldump), y escribe un nuevo archivo *_fixed.sql.
# Sustituye collations de MySQL 8 (utf8mb4_0900_*) por utf8mb4_unicode_ci para importar
# en MariaDB o MySQL < 8 sin error #1273.
set -euo pipefail

normalize_mysql8_collations() {
  sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' \
      -e 's/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' \
      -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g' \
      -e 's/utf8mb4_0900_bin/utf8mb4_bin/g'
}
SRC="${1:-}"
PATCH="${2:-$(dirname "$0")/../database/patches/videosegg_missing_asignacion_tables.sql}"
if [[ -z "$SRC" || ! -f "$SRC" ]]; then
  echo "Uso: $0 /ruta/a/dbvideosegg_2026_04_30.sql" >&2
  exit 1
fi
if [[ ! -f "$PATCH" ]]; then
  echo "No se encuentra el parche: $PATCH" >&2
  exit 1
fi
DIR=$(dirname "$SRC")
BASE=$(basename "$SRC" .sql)
OUT="${DIR}/${BASE}_fixed.sql"
OUT_TMP="${OUT}.tmp.$$"
echo "Entrada:  $SRC"
echo "Parche:   $PATCH"
echo "Salida:   $OUT"
# mysqldump sin USE; phpMyAdmin a veces no aplica la base seleccionada a todo el lote.
DUMP_DB=$(grep -m1 '^-- Host:' "$SRC" 2>/dev/null | sed -n 's/.*Database:[[:space:]]*//p' | tr -d '\r' | sed 's/[[:space:]]*$//' || true)
head -n 17 "$SRC" | normalize_mysql8_collations >"$OUT_TMP"
if [[ -n "${DUMP_DB}" ]]; then
  {
    echo ""
    printf 'USE `%s`;\n' "${DUMP_DB//\`/}"
    echo ""
  } >>"$OUT_TMP"
fi
{
  echo "-- --- Parche EDA_SOCIAL: DDL faltante asignacion_t / asignar_video_a_t ---"
  cat "$PATCH"
  echo "-- --- Fin parche ---"
  echo ""
} >>"$OUT_TMP"
tail -n +18 "$SRC" | normalize_mysql8_collations >>"$OUT_TMP"
mv "$OUT_TMP" "$OUT"
echo "Listo. Importa OUT (phpMyAdmin o mysql). Entrada debe ser dump mysqldump sin parche previo."
