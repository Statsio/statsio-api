#!/usr/bin/env bash
set -euo pipefail

# Provisionne les dashboards OpenObserve de Statsio à partir des fichiers JSON versionnés dans
# docker/openobserve/dashboards/. Idempotent : recherche un dashboard existant par titre (l'API
# OpenObserve n'a pas de contrainte d'unicité sur le titre — un POST répété duplique silencieusement
# le dashboard) et bascule sur un PUT avec le hash courant s'il existe déjà, sinon un POST.
#
# Requiert dans l'environnement : ZO_ROOT_USER_EMAIL, ZO_ROOT_USER_PASSWORD.
#
# Usage : ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... ./provision-dashboards.sh

OO_URL="${OO_URL:-http://localhost:5080}"
ORG="default"
AUTH="${ZO_ROOT_USER_EMAIL}:${ZO_ROOT_USER_PASSWORD}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

curl -sf -u "$AUTH" "$OO_URL/healthz" > /dev/null || {
  echo "OpenObserve injoignable sur $OO_URL" >&2
  exit 1
}

for FILE in "$SCRIPT_DIR"/dashboards/*.json; do
  TITLE=$(python3 -c "import json; print(json.load(open('$FILE'))['title'])")

  EXISTING=$(curl -s -u "$AUTH" "$OO_URL/api/$ORG/dashboards" \
    | python3 -c "
import json, sys
d = json.load(sys.stdin)
for dash in d['dashboards']:
    if dash['title'] == '$TITLE':
        print(dash['dashboard_id'] + ' ' + dash['hash'])
        break
")

  if [ -n "$EXISTING" ]; then
    ID=$(echo "$EXISTING" | cut -d' ' -f1)
    HASH=$(echo "$EXISTING" | cut -d' ' -f2)
    curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/dashboards/$ID?hash=$HASH" \
      -H "Content-Type: application/json" --data-binary "@$FILE" > /dev/null
    echo "Dashboard '$TITLE' mis à jour ($ID)."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/dashboards" \
      -H "Content-Type: application/json" --data-binary "@$FILE" > /dev/null
    echo "Dashboard '$TITLE' créé."
  fi
done
