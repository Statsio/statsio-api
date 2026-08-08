#!/usr/bin/env bash
set -euo pipefail

# Provisionne dans OpenObserve la règle d'alerte "supervision critique de statsio-api" et sa
# destination email. Idempotent : peut être relancé sans dupliquer la config (les destinations
# sont mises à jour par PUT si elles existent déjà, les alertes recherchées par nom avant création).
#
# Requiert dans l'environnement : ZO_ROOT_USER_EMAIL, ZO_ROOT_USER_PASSWORD (déjà utilisés par le
# service openobserve lui-même). OO_URL par défaut vise l'instance locale.
#
# Usage : ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... ./provision-alerts.sh

OO_URL="${OO_URL:-http://localhost:5080}"
ORG="default"
AUTH="${ZO_ROOT_USER_EMAIL}:${ZO_ROOT_USER_PASSWORD}"
DEST_NAME="statsio_health_alerts"
ALERT_NAME="statsio_health_critical"

curl -sf -u "$AUTH" "$OO_URL/healthz" > /dev/null || {
  echo "OpenObserve injoignable sur $OO_URL" >&2
  exit 1
}

DEST_PAYLOAD=$(cat <<JSON
{"name":"$DEST_NAME","type":"email","emails":["${ZO_ROOT_USER_EMAIL}"],"template":"prebuilt_email"}
JSON
)

if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/destinations/$DEST_NAME" \
  -H "Content-Type: application/json" -d "$DEST_PAYLOAD" > /dev/null 2>&1; then
  echo "Destination '$DEST_NAME' mise à jour."
else
  curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/destinations" \
    -H "Content-Type: application/json" -d "$DEST_PAYLOAD" > /dev/null
  echo "Destination '$DEST_NAME' créée."
fi

ALERT_ID=$(curl -s -u "$AUTH" "$OO_URL/api/v2/$ORG/alerts" \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a['alert_id'] for a in d['list'] if a['name']=='$ALERT_NAME'), ''))")

# Le stream docker_logs ne contient qu'un champ 'message' texte brut (Vector n'y applique aucun
# parsing) : on filtre sur la sous-chaîne stable produite par Monolog ("local.CRITICAL:") plutôt
# que sur un champ structuré 'level', qui n'existe pas dans ce stream.
ALERT_PAYLOAD=$(cat <<JSON
{
  "name": "$ALERT_NAME",
  "stream_type": "logs",
  "stream_name": "docker_logs",
  "is_real_time": false,
  "query_condition": {
    "type": "sql",
    "sql": "SELECT _timestamp, container_name, message FROM docker_logs WHERE container_name = 'statsio-api' AND message LIKE '%local.CRITICAL%'"
  },
  "trigger_condition": {
    "period": 5,
    "operator": ">=",
    "threshold": 1,
    "frequency": 1,
    "frequency_type": "minutes",
    "silence": 10
  },
  "destinations": ["$DEST_NAME"],
  "enabled": true
}
JSON
)

if [ -n "$ALERT_ID" ]; then
  curl -sf -u "$AUTH" -X PUT "$OO_URL/api/v2/$ORG/alerts/$ALERT_ID" \
    -H "Content-Type: application/json" -d "$ALERT_PAYLOAD" > /dev/null
  echo "Alerte '$ALERT_NAME' mise à jour ($ALERT_ID)."
else
  curl -sf -u "$AUTH" -X POST "$OO_URL/api/v2/$ORG/alerts" \
    -H "Content-Type: application/json" -d "$ALERT_PAYLOAD" > /dev/null
  echo "Alerte '$ALERT_NAME' créée."
fi
