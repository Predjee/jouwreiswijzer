#!/usr/bin/env bash
#
# deploy/rollback.sh — wijst current terug naar een eerdere release.
#
# Gebruik:
#   rollback.sh <base-path> <release-name> <domain-path>
#
# Voorbeeld:
#   rollback.sh /home/derei1602/production 20260620120000 \
#     /home/derei1602/domains/jouwreiswijzer.nl
#
set -euo pipefail

BASE_PATH="$1"
RELEASE_NAME="$2"
DOMAIN_PATH="$3"

RELEASE_DIR="${BASE_PATH}/releases/${RELEASE_NAME}"

if [ ! -d "${RELEASE_DIR}" ]; then
    echo "FOUT: release ${RELEASE_NAME} bestaat niet in ${BASE_PATH}/releases" >&2
    exit 1
fi

ln -sfn "${RELEASE_DIR}" "${BASE_PATH}/current"
ln -sfn "${BASE_PATH}/current/public" "${DOMAIN_PATH}/public_html"

echo "==> Rollback naar ${RELEASE_NAME} voltooid"
