#!/usr/bin/env bash
set -euo pipefail

# Provisionne dans OpenObserve la règle d'alerte "supervision critique de statsio-api" et ses
# destinations (email, et Slack si configuré). Idempotent : peut être relancé sans dupliquer la
# config (les destinations sont mises à jour par PUT si elles existent déjà, les alertes
# recherchées par nom avant création).
#
# Requiert dans l'environnement : ZO_ROOT_USER_EMAIL, ZO_ROOT_USER_PASSWORD (déjà utilisés par le
# service openobserve lui-même). OO_URL par défaut vise l'instance locale.
#
# Optionnel : SLACK_WEBHOOK_URL (URL d'un Incoming Webhook Slack). Si absent, seule la destination
# email est provisionnée — aucune erreur, le canal Slack est simplement ignoré.
#
# Usage : ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... [SLACK_WEBHOOK_URL=...] ./provision-alerts.sh

OO_URL="${OO_URL:-http://localhost:5080}"
ORG="default"
AUTH="${ZO_ROOT_USER_EMAIL}:${ZO_ROOT_USER_PASSWORD}"
DEST_NAME="statsio_health_alerts"
SLACK_TEMPLATE_NAME="statsio_slack_template"
SLACK_DEST_NAME="statsio_slack_alerts"
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

# Destination Slack (optionnelle) : un template dédié formate le corps du webhook au format
# attendu par Slack ({"text": "..."}), puis une destination de type http l'associe à l'URL du
# webhook. Les deux ressources sont adressées par nom (comme la destination email ci-dessus) et
# donc provisionnées de la même façon idempotente : PUT si elles existent déjà, sinon POST.
SLACK_DESTINATIONS_JSON="[]"
if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
  TEMPLATE_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_TEMPLATE_NAME",
  "type": "http",
  "body": "{\\"text\\": \\":rotating_light: *[$ALERT_NAME]* {alert_name} est actif sur le flux {stream_name} (seuil : {alert_count}/{alert_threshold}) — {alert_url}\\"}"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/templates/$SLACK_TEMPLATE_NAME" \
    -H "Content-Type: application/json" -d "$TEMPLATE_PAYLOAD" > /dev/null 2>&1; then
    echo "Template '$SLACK_TEMPLATE_NAME' mis à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/templates" \
      -H "Content-Type: application/json" -d "$TEMPLATE_PAYLOAD" > /dev/null
    echo "Template '$SLACK_TEMPLATE_NAME' créé."
  fi

  SLACK_DEST_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_DEST_NAME",
  "type": "http",
  "url": "$SLACK_WEBHOOK_URL",
  "method": "POST",
  "headers": {"Content-Type": "application/json"},
  "template": "$SLACK_TEMPLATE_NAME"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/destinations/$SLACK_DEST_NAME" \
    -H "Content-Type: application/json" -d "$SLACK_DEST_PAYLOAD" > /dev/null 2>&1; then
    echo "Destination '$SLACK_DEST_NAME' mise à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/destinations" \
      -H "Content-Type: application/json" -d "$SLACK_DEST_PAYLOAD" > /dev/null
    echo "Destination '$SLACK_DEST_NAME' créée."
  fi

  SLACK_DESTINATIONS_JSON="\"$SLACK_DEST_NAME\""
else
  echo "SLACK_WEBHOOK_URL non défini : destination Slack ignorée."
fi

ALERT_ID=$(curl -s -u "$AUTH" "$OO_URL/api/v2/$ORG/alerts" \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a['alert_id'] for a in d['list'] if a['name']=='$ALERT_NAME'), ''))")

# Le stream docker_logs ne contient qu'un champ 'message' texte brut (Vector n'y applique aucun
# parsing) : on filtre sur la sous-chaîne stable produite par Monolog ("local.CRITICAL:") plutôt
# que sur un champ structuré 'level', qui n'existe pas dans ce stream.
ALERT_DESTINATIONS="\"$DEST_NAME\""
if [ "$SLACK_DESTINATIONS_JSON" != "[]" ]; then
  ALERT_DESTINATIONS="$ALERT_DESTINATIONS, $SLACK_DESTINATIONS_JSON"
fi

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
  "destinations": [$ALERT_DESTINATIONS],
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
