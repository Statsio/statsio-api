#!/usr/bin/env bash
set -euo pipefail

# Provisionne dans OpenObserve une règle d'alerte "supervision critique" et ses destinations
# (email, et Slack si configuré) pour un environnement donné (production par défaut, staging via
# les variables ci-dessous). Idempotent : peut être relancé sans dupliquer la config (les
# destinations sont mises à jour par PUT si elles existent déjà, les alertes recherchées par nom
# avant création).
#
# Requiert dans l'environnement : ZO_ROOT_USER_EMAIL, ZO_ROOT_USER_PASSWORD (déjà utilisés par le
# service openobserve lui-même). OO_URL par défaut vise l'instance locale.
#
# Optionnel :
#   SLACK_WEBHOOK_URL  URL d'un Incoming Webhook Slack. Si absent, aucun canal Slack n'est
#                       provisionné — aucune erreur, il est simplement ignoré.
#   CONTAINER_NAME      Conteneur surveillé par la requête SQL de l'alerte (défaut : statsio-api).
#   ALERT_SUFFIX         Suffixe ajouté aux noms des ressources OpenObserve, pour isoler les
#                       alertes de plusieurs environnements sur la même instance (défaut : "").
#   INCLUDE_EMAIL        "false" pour ne provisionner que le canal Slack, sans destination email
#                       (défaut : "true").
#
# Usage (production) :
#   ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... SLACK_WEBHOOK_URL=... ./provision-alerts.sh
#
# Usage (staging, canal Slack dédié "preview logs", sans email) :
#   ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... CONTAINER_NAME=statsio-api-staging \
#   ALERT_SUFFIX=_staging INCLUDE_EMAIL=false SLACK_WEBHOOK_URL=... ./provision-alerts.sh

OO_URL="${OO_URL:-http://localhost:5080}"
ORG="default"
AUTH="${ZO_ROOT_USER_EMAIL}:${ZO_ROOT_USER_PASSWORD}"
CONTAINER_NAME="${CONTAINER_NAME:-statsio-api}"
ALERT_SUFFIX="${ALERT_SUFFIX:-}"
INCLUDE_EMAIL="${INCLUDE_EMAIL:-true}"

DEST_NAME="statsio_health_alerts${ALERT_SUFFIX}"
SLACK_TEMPLATE_NAME="statsio_slack_template${ALERT_SUFFIX}"
SLACK_DEST_NAME="statsio_slack_alerts${ALERT_SUFFIX}"
ALERT_NAME="statsio_health_critical${ALERT_SUFFIX}"

curl -sf -u "$AUTH" "$OO_URL/healthz" > /dev/null || {
  echo "OpenObserve injoignable sur $OO_URL" >&2
  exit 1
}

DEST_LIST=()

# Destination email (optionnelle : désactivée pour un environnement comme staging où seule une
# visibilité Slack "légère" est souhaitée, sans réveiller personne par email).
if [ "$INCLUDE_EMAIL" = "true" ]; then
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

  DEST_LIST+=("$DEST_NAME")
else
  echo "INCLUDE_EMAIL=false : destination email ignorée."
fi

# Destination Slack (optionnelle) : un template dédié formate le corps du webhook au format
# attendu par Slack ({"text": "..."}), puis une destination de type http l'associe à l'URL du
# webhook. Les deux ressources sont adressées par nom (comme la destination email ci-dessus) et
# donc provisionnées de la même façon idempotente : PUT si elles existent déjà, sinon POST.
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
  "method": "post",
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

  DEST_LIST+=("$SLACK_DEST_NAME")
else
  echo "SLACK_WEBHOOK_URL non défini : destination Slack ignorée."
fi

if [ "${#DEST_LIST[@]}" -eq 0 ]; then
  echo "Aucune destination active (INCLUDE_EMAIL=false et SLACK_WEBHOOK_URL absent) : abandon." >&2
  exit 1
fi

ALERT_ID=$(curl -s -u "$AUTH" "$OO_URL/api/v2/$ORG/alerts" \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a['alert_id'] for a in d['list'] if a['name']=='$ALERT_NAME'), ''))")

# Le stream docker_logs ne contient qu'un champ 'message' texte brut (Vector n'y applique aucun
# parsing) : on filtre sur la sous-chaîne stable produite par Monolog ("<env>.CRITICAL:") plutôt
# que sur un champ structuré 'level', qui n'existe pas dans ce stream. Le préfixe d'environnement
# (APP_ENV, ex. "production", "staging") varie selon la cible : on filtre sur ".CRITICAL:" seul,
# qui matche quel que soit l'environnement plutôt que sur "local.CRITICAL", qui ne serait jamais
# produit en dehors d'un poste de développement.
ALERT_DESTINATIONS=$(printf '"%s",' "${DEST_LIST[@]}")
ALERT_DESTINATIONS="${ALERT_DESTINATIONS%,}"

ALERT_PAYLOAD=$(cat <<JSON
{
  "name": "$ALERT_NAME",
  "stream_type": "logs",
  "stream_name": "docker_logs",
  "is_real_time": false,
  "query_condition": {
    "type": "sql",
    "sql": "SELECT _timestamp, container_name, message FROM docker_logs WHERE container_name = '$CONTAINER_NAME' AND message LIKE '%.CRITICAL:%'"
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
