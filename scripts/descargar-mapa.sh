#!/usr/bin/env bash
# Recorta el mapa de Paraná del build diario de Protomaps (OpenStreetMap, licencia ODbL) y lo deja en
# resources/mapa/parana.pmtiles. Hace falta la herramienta `pmtiles`: https://github.com/protomaps/go-pmtiles/releases
#
# Uso: scripts/descargar-mapa.sh [AAAAMMDD]    (sin fecha, usa la de ayer)
# El archivo pesa unos 2 MB. Protomaps pide no enlazar sus builds: por eso se recorta y se aloja acá.

set -euo pipefail

FECHA="${1:-$(date -d yesterday +%Y%m%d)}"
CAJA="-60.62,-31.82,-60.42,-31.66"   # oeste,sur,este,norte: mismo rectángulo que config/ramal.php
DESTINO="resources/mapa/parana.pmtiles"

mkdir -p "$(dirname "$DESTINO")"
pmtiles extract "https://build.protomaps.com/${FECHA}.pmtiles" "$DESTINO" --bbox="$CAJA" --maxzoom=15
ls -lh "$DESTINO"
