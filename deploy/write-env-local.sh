#!/usr/bin/env bash
#
# deploy/write-env-local.sh — schrijft .env.local uit environment variables.
#
# Draait op de GitHub Actions runner, vóór het bouwen van de release-archive.
# Verwacht APP_ENV en de onderstaande variabelen al in de omgeving (via
# GitHub Secrets, zie docs/DEPLOY.md).
#
set -euo pipefail

: "${APP_ENV:?APP_ENV is verplicht}"
: "${APP_SECRET:?APP_SECRET is verplicht}"
: "${DATABASE_URL:?DATABASE_URL is verplicht}"

cat > .env.local <<ENV
APP_ENV=${APP_ENV}
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
MAILER_DSN=${MAILER_DSN:-}
SULU_ADMIN_EMAIL=${SULU_ADMIN_EMAIL:-}
DEFAULT_URI=${DEFAULT_URI:-}
NOTIFICATION_EMAIL=${NOTIFICATION_EMAIL:-}
FROM_EMAIL=${FROM_EMAIL:-}
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=${JWT_PASSPHRASE:-}
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
LOCK_DSN=flock
# SEAL/Loupe is geïnstalleerd maar nog niet in gebruik (geen schema's, geen
# reindex-providers). Zodra dat wel zo is: var/indexes moet als shared
# directory in deploy/release.sh worden opgenomen, anders verdwijnt de index
# bij elke deploy. Zie docs/ARCHITECTURE.md.
SEAL_DSN=loupe://%kernel.project_dir%/var/indexes
ENV

echo "==> .env.local geschreven voor APP_ENV=${APP_ENV}"
