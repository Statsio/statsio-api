#!/usr/bin/env bash
set -euo pipefail

# Provisionne dans OpenObserve deux règles d'alerte (santé critique, erreurs applicatives) et
# leurs destinations (email, et Slack si configuré) pour un environnement donné (production par
# défaut, staging via les variables ci-dessous). Idempotent : peut être relancé sans dupliquer la
# config (les destinations sont mises à jour par PUT si elles existent déjà, les alertes
# recherchées par nom avant création).
#
# Requiert dans l'environnement : ZO_ROOT_USER_EMAIL, ZO_ROOT_USER_PASSWORD (déjà utilisés par le
# service openobserve lui-même). OO_URL par défaut vise l'instance locale.
#
# Optionnel :
#   SLACK_WEBHOOK_URL  URL d'un Incoming Webhook Slack. Si absent, aucun canal Slack n'est
#                       provisionné — aucune erreur, il est simplement ignoré.
#   CONTAINER_NAME      Conteneur surveillé par l'alerte de santé (défaut : statsio-scheduler).
#   API_CONTAINER_NAME  Conteneur surveillé par l'alerte d'erreurs applicatives (défaut : statsio-api).
#   ALERT_SUFFIX         Suffixe ajouté aux noms des ressources OpenObserve, pour isoler les
#                       alertes de plusieurs environnements sur la même instance (défaut : "").
#   INCLUDE_EMAIL        "false" pour ne provisionner que le canal Slack, sans destination email
#                       (défaut : "true").
#
# Usage (production) :
#   ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... SLACK_WEBHOOK_URL=... ./provision-alerts.sh
#
# Usage (staging, canal Slack dédié "preview logs", sans email) :
#   ZO_ROOT_USER_EMAIL=... ZO_ROOT_USER_PASSWORD=... CONTAINER_NAME=statsio-scheduler-staging \
#   API_CONTAINER_NAME=statsio-api-staging ALERT_SUFFIX=_staging INCLUDE_EMAIL=false \
#   SLACK_WEBHOOK_URL=... ./provision-alerts.sh

OO_URL="${OO_URL:-http://localhost:5080}"
ORG="default"
AUTH="${ZO_ROOT_USER_EMAIL}:${ZO_ROOT_USER_PASSWORD}"
# health:monitor tourne dans le conteneur "scheduler" (schedule:work, cf. bootstrap/app.php),
# pas dans "api" : c'est bien ce conteneur qu'il faut surveiller pour cette alerte.
CONTAINER_NAME="${CONTAINER_NAME:-statsio-scheduler}"
# Les erreurs applicatives (500) se produisent dans le conteneur qui sert les requêtes HTTP,
# distinct du scheduler surveillé par l'alerte de santé ci-dessus.
API_CONTAINER_NAME="${API_CONTAINER_NAME:-statsio-api}"
ALERT_SUFFIX="${ALERT_SUFFIX:-}"
INCLUDE_EMAIL="${INCLUDE_EMAIL:-true}"

DEST_NAME="statsio_health_alerts${ALERT_SUFFIX}"
EMAIL_TEMPLATE_NAME="statsio_email_template${ALERT_SUFFIX}"
SLACK_TEMPLATE_NAME="statsio_slack_template_critical${ALERT_SUFFIX}"
SLACK_DEST_NAME="statsio_slack_alerts${ALERT_SUFFIX}"
SLACK_ERROR_TEMPLATE_NAME="statsio_slack_template_error${ALERT_SUFFIX}"
SLACK_ERROR_DEST_NAME="statsio_slack_alerts_error${ALERT_SUFFIX}"
SLACK_WARNING_TEMPLATE_NAME="statsio_slack_template_warning${ALERT_SUFFIX}"
SLACK_WARNING_DEST_NAME="statsio_slack_alerts_warning${ALERT_SUFFIX}"
ALERT_NAME="statsio_health_critical${ALERT_SUFFIX}"
APP_ERROR_ALERT_NAME="statsio_app_errors${ALERT_SUFFIX}"
DISK_ALERT_NAME="statsio_disk_saturation${ALERT_SUFFIX}"
MEMORY_ALERT_NAME="statsio_memory_saturation${ALERT_SUFFIX}"
CPU_ALERT_NAME="statsio_cpu_saturation${ALERT_SUFFIX}"

# Libellé humain de l'environnement, dérivé du suffixe : indispensable dans le corps de l'email/
# Slack, sinon rien ne permet de savoir sur quel environnement porte l'alerte à la lecture.
case "$ALERT_SUFFIX" in
  "") ENV_LABEL="Production" ;;
  "_staging") ENV_LABEL="Staging" ;;
  *) ENV_LABEL="Environnement${ALERT_SUFFIX}" ;;
esac

curl -sf -u "$AUTH" "$OO_URL/healthz" > /dev/null || {
  echo "OpenObserve injoignable sur $OO_URL" >&2
  exit 1
}

DEST_LIST=()

# Destination email (optionnelle : désactivée pour un environnement comme staging où seule une
# visibilité Slack "légère" est souhaitée, sans réveiller personne par email).
if [ "$INCLUDE_EMAIL" = "true" ]; then
  # Le template par défaut d'OpenObserve ("prebuilt_email") n'affiche qu'un objet générique sans
  # corps exploitable (ni environnement, ni horodatage, ni lien) : un template dédié est
  # nécessaire pour que l'email seul suffise à comprendre ce qui se passe et où.
  EMAIL_TEMPLATE_PAYLOAD=$(cat <<JSON
{
  "name": "$EMAIL_TEMPLATE_NAME",
  "type": "email",
  "title": "🚨 [$ENV_LABEL] Alerte critique — {alert_name}",
  "body": "<h3>🚨 Alerte critique — {alert_name}</h3><p><strong>Environnement :</strong> $ENV_LABEL</p><p><strong>Flux surveillé :</strong> {stream_name}</p><p><strong>Condition :</strong> {alert_count} occurrence(s) sur la fenêtre, seuil {alert_threshold}</p><p><strong>Déclenché à :</strong> {alert_start_time}</p><p><a href=\\"{alert_url}\\">Voir le détail dans OpenObserve</a></p>"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/templates/$EMAIL_TEMPLATE_NAME" \
    -H "Content-Type: application/json" -d "$EMAIL_TEMPLATE_PAYLOAD" > /dev/null 2>&1; then
    echo "Template '$EMAIL_TEMPLATE_NAME' mis à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/templates" \
      -H "Content-Type: application/json" -d "$EMAIL_TEMPLATE_PAYLOAD" > /dev/null
    echo "Template '$EMAIL_TEMPLATE_NAME' créé."
  fi

  DEST_PAYLOAD=$(cat <<JSON
{"name":"$DEST_NAME","type":"email","emails":["${ZO_ROOT_USER_EMAIL}"],"template":"$EMAIL_TEMPLATE_NAME"}
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

# Destinations Slack (optionnelles) : deux templates distincts — un par gravité — plutôt qu'un
# seul générique. Slack rend le champ "attachments[].color" en liseré coloré sur le côté gauche du
# message ; combiné à un emoji différent, un message critique et une simple erreur applicative se
# distinguent visuellement sans avoir à ouvrir OpenObserve pour connaître la gravité. "{rows}" est
# remplacé par le contenu réel des lignes de log matchées, mises en forme par "row_template" (défini
# au niveau de l'alerte, cf. plus bas) : sans lui, seules les métadonnées de l'alerte (nom, flux,
# seuil) seraient visibles, jamais le contenu du log qui a déclenché l'alerte.
if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
  # Gravité critique (santé) : liseré rouge.
  TEMPLATE_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_TEMPLATE_NAME",
  "type": "http",
  "body": "{\\"attachments\\": [{\\"color\\": \\"#dc3545\\", \\"title\\": \\":rotating_light: [$ENV_LABEL] CRITIQUE — {alert_name}\\", \\"title_link\\": \\"{alert_url}\\", \\"text\\": \\"*Flux :* {stream_name}\\n*Seuil :* {alert_count}/{alert_threshold} sur la fenêtre\\n*Déclenché à :* {alert_start_time}\\n\\n{rows}\\", \\"footer\\": \\"OpenObserve · Statsio\\"}]}"
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

  # Gravité erreur applicative : liseré orange, même canal Slack mais template et destination
  # séparés (une destination OpenObserve ne référence qu'un seul template).
  ERROR_TEMPLATE_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_ERROR_TEMPLATE_NAME",
  "type": "http",
  "body": "{\\"attachments\\": [{\\"color\\": \\"#fd7e14\\", \\"title\\": \\":beetle: [$ENV_LABEL] Erreur applicative — {alert_name}\\", \\"title_link\\": \\"{alert_url}\\", \\"text\\": \\"*Seuil :* {alert_count}/{alert_threshold} sur 5 min\\n*Déclenché à :* {alert_start_time}\\n\\n{rows}\\", \\"footer\\": \\"OpenObserve · Statsio\\"}]}"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/templates/$SLACK_ERROR_TEMPLATE_NAME" \
    -H "Content-Type: application/json" -d "$ERROR_TEMPLATE_PAYLOAD" > /dev/null 2>&1; then
    echo "Template '$SLACK_ERROR_TEMPLATE_NAME' mis à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/templates" \
      -H "Content-Type: application/json" -d "$ERROR_TEMPLATE_PAYLOAD" > /dev/null
    echo "Template '$SLACK_ERROR_TEMPLATE_NAME' créé."
  fi

  SLACK_ERROR_DEST_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_ERROR_DEST_NAME",
  "type": "http",
  "url": "$SLACK_WEBHOOK_URL",
  "method": "post",
  "headers": {"Content-Type": "application/json"},
  "template": "$SLACK_ERROR_TEMPLATE_NAME"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/destinations/$SLACK_ERROR_DEST_NAME" \
    -H "Content-Type: application/json" -d "$SLACK_ERROR_DEST_PAYLOAD" > /dev/null 2>&1; then
    echo "Destination '$SLACK_ERROR_DEST_NAME' mise à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/destinations" \
      -H "Content-Type: application/json" -d "$SLACK_ERROR_DEST_PAYLOAD" > /dev/null
    echo "Destination '$SLACK_ERROR_DEST_NAME' créée."
  fi

  # Gravité avertissement (saturation ressources) : liseré ambre. Distincte du rouge (panne
  # confirmée) et de l'orange (erreur déjà survenue) — une saturation à 85% est un signal précoce,
  # pas encore une panne. Pas de "{rows}" ici : les alertes sur métriques n'ont pas de lignes de
  # log associées, {alert_count} porte la valeur mesurée (à confirmer empiriquement, cf. tests).
  WARNING_TEMPLATE_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_WARNING_TEMPLATE_NAME",
  "type": "http",
  "body": "{\\"attachments\\": [{\\"color\\": \\"#ffc107\\", \\"title\\": \\":warning: [$ENV_LABEL] Ressources sous tension — {alert_name}\\", \\"title_link\\": \\"{alert_url}\\", \\"text\\": \\"*Métrique :* {stream_name}\\n*Valeur mesurée :* {alert_count} (seuil : {alert_threshold})\\n*Déclenché à :* {alert_start_time}\\", \\"footer\\": \\"OpenObserve · Statsio\\"}]}"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/templates/$SLACK_WARNING_TEMPLATE_NAME" \
    -H "Content-Type: application/json" -d "$WARNING_TEMPLATE_PAYLOAD" > /dev/null 2>&1; then
    echo "Template '$SLACK_WARNING_TEMPLATE_NAME' mis à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/templates" \
      -H "Content-Type: application/json" -d "$WARNING_TEMPLATE_PAYLOAD" > /dev/null
    echo "Template '$SLACK_WARNING_TEMPLATE_NAME' créé."
  fi

  SLACK_WARNING_DEST_PAYLOAD=$(cat <<JSON
{
  "name": "$SLACK_WARNING_DEST_NAME",
  "type": "http",
  "url": "$SLACK_WEBHOOK_URL",
  "method": "post",
  "headers": {"Content-Type": "application/json"},
  "template": "$SLACK_WARNING_TEMPLATE_NAME"
}
JSON
)

  if curl -sf -u "$AUTH" -X PUT "$OO_URL/api/$ORG/alerts/destinations/$SLACK_WARNING_DEST_NAME" \
    -H "Content-Type: application/json" -d "$SLACK_WARNING_DEST_PAYLOAD" > /dev/null 2>&1; then
    echo "Destination '$SLACK_WARNING_DEST_NAME' mise à jour."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/$ORG/alerts/destinations" \
      -H "Content-Type: application/json" -d "$SLACK_WARNING_DEST_PAYLOAD" > /dev/null
    echo "Destination '$SLACK_WARNING_DEST_NAME' créée."
  fi
else
  echo "SLACK_WEBHOOK_URL non défini : destinations Slack ignorées."
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
  "row_template": "• {message}",
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

# Alerte erreurs applicatives (500) — Slack uniquement, jamais email : contrairement à une
# indisponibilité totale (alerte ci-dessus), une exception isolée n'a pas le poids d'une astreinte
# (cf. BLOC4_1, 1.3.A). Ne se provisionne que si un canal Slack est configuré. Le contenu détaillé
# (URL, méthode, utilisateur, type d'exception) provient de l'enrichissement du log applicatif
# (cf. bootstrap/app.php, $exceptions->report()) : sans lui, {message} ne contiendrait que le
# message brut de l'exception, sans contexte exploitable.
if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
  APP_ERROR_ALERT_ID=$(curl -s -u "$AUTH" "$OO_URL/api/v2/$ORG/alerts" \
    | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a['alert_id'] for a in d['list'] if a['name']=='$APP_ERROR_ALERT_NAME'), ''))")

  # Même logique de filtrage que pour ".CRITICAL:" (cf. commentaire plus haut) : Laravel préfixe
  # ses logs par "<env>.ERROR:", niveau utilisé par notre report() personnalisé pour toute
  # exception serveur (5xx) non attrapée.
  APP_ERROR_PAYLOAD=$(cat <<JSON
{
  "name": "$APP_ERROR_ALERT_NAME",
  "stream_type": "logs",
  "stream_name": "docker_logs",
  "is_real_time": false,
  "query_condition": {
    "type": "sql",
    "sql": "SELECT _timestamp, container_name, message FROM docker_logs WHERE container_name = '$API_CONTAINER_NAME' AND message LIKE '%.ERROR:%'"
  },
  "trigger_condition": {
    "period": 5,
    "operator": ">=",
    "threshold": 1,
    "frequency": 1,
    "frequency_type": "minutes",
    "silence": 10
  },
  "row_template": "• {message}",
  "destinations": ["$SLACK_ERROR_DEST_NAME"],
  "enabled": true
}
JSON
)

  if [ -n "$APP_ERROR_ALERT_ID" ]; then
    curl -sf -u "$AUTH" -X PUT "$OO_URL/api/v2/$ORG/alerts/$APP_ERROR_ALERT_ID" \
      -H "Content-Type: application/json" -d "$APP_ERROR_PAYLOAD" > /dev/null
    echo "Alerte '$APP_ERROR_ALERT_NAME' mise à jour ($APP_ERROR_ALERT_ID)."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/v2/$ORG/alerts" \
      -H "Content-Type: application/json" -d "$APP_ERROR_PAYLOAD" > /dev/null
    echo "Alerte '$APP_ERROR_ALERT_NAME' créée."
  fi
else
  echo "SLACK_WEBHOOK_URL non défini : alerte erreurs applicatives ignorée."
fi

# Alertes de saturation ressources (disque, mémoire, CPU) — Slack "avertissement" uniquement.
# Expressions PromQL vérifiées manuellement contre l'instance réelle avant intégration ici (cf.
# BLOC4_1, 1.2 — les noms de métriques suivent la convention native de Vector host_metrics).
# Seuil unique à 85%, fréquence d'évaluation plus espacée (5 min) et silence plus long (30 min)
# qu'une panne confirmée : une saturation est un signal précoce, pas une urgence seconde par
# seconde, et resterait bruyante à réévaluer aussi souvent que l'alerte de santé.
provision_metric_alert() {
  local alert_name="$1" metric_stream="$2" promql="$3" threshold="$4"

  local alert_id
  alert_id=$(curl -s -u "$AUTH" "$OO_URL/api/v2/$ORG/alerts" \
    | python3 -c "import json,sys; d=json.load(sys.stdin); print(next((a['alert_id'] for a in d['list'] if a['name']=='$alert_name'), ''))")

  local payload
  payload=$(cat <<JSON
{
  "name": "$alert_name",
  "stream_type": "metrics",
  "stream_name": "$metric_stream",
  "is_real_time": false,
  "query_condition": {
    "type": "promql",
    "promql": "$promql",
    "promql_condition": {"column": "value", "operator": ">=", "value": $threshold}
  },
  "trigger_condition": {
    "period": 10,
    "operator": ">=",
    "threshold": 1,
    "frequency": 5,
    "frequency_type": "minutes",
    "silence": 30
  },
  "destinations": ["$SLACK_WARNING_DEST_NAME"],
  "enabled": true
}
JSON
)

  if [ -n "$alert_id" ]; then
    curl -sf -u "$AUTH" -X PUT "$OO_URL/api/v2/$ORG/alerts/$alert_id" \
      -H "Content-Type: application/json" -d "$payload" > /dev/null
    echo "Alerte '$alert_name' mise à jour ($alert_id)."
  else
    curl -sf -u "$AUTH" -X POST "$OO_URL/api/v2/$ORG/alerts" \
      -H "Content-Type: application/json" -d "$payload" > /dev/null
    echo "Alerte '$alert_name' créée."
  fi
}

if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
  provision_metric_alert "$DISK_ALERT_NAME" "host_filesystem_used_ratio" \
    "max(host_filesystem_used_ratio) * 100" 85

  provision_metric_alert "$MEMORY_ALERT_NAME" "host_memory_used_bytes" \
    "(host_memory_used_bytes / host_memory_total_bytes) * 100" 85

  provision_metric_alert "$CPU_ALERT_NAME" "host_cpu_seconds_total" \
    "(1 - avg(rate(host_cpu_seconds_total{mode=\\\"idle\\\"}[5m]))) * 100" 85
else
  echo "SLACK_WEBHOOK_URL non défini : alertes de saturation ressources ignorées."
fi
