#!/usr/bin/env bash
#
# deploy/release.sh — voltooit een release op de server.
#
# Draait NIET vanuit GitHub Actions zelf, maar wordt daar alleen aangeroepen via SSH.
# Kan ook handmatig getest worden door dit bestand naar de server te kopiëren en met
# de juiste argumenten te draaien.
#
# Gebruik:
#   release.sh <base-path> <release-name> <domain-path> <app-env>
#
# Voorbeeld (productie):
#   release.sh /home/derei1602/production 20260622165700 \
#     /home/derei1602/domains/jouwreiswijzer.nl prod
#
set -euo pipefail

BASE_PATH="$1"
RELEASE_NAME="$2"
DOMAIN_PATH="$3"
APP_ENV="$4"

RELEASE_DIR="${BASE_PATH}/releases/${RELEASE_NAME}"
SHARED_DIR="${BASE_PATH}/shared"

echo "==> Releasing ${RELEASE_NAME} (env: ${APP_ENV})"

cd "${RELEASE_DIR}"
tar xzf deploy.tar.gz
rm deploy.tar.gz

echo "==> Voorbereiden shared directories"
mkdir -p \
    "${SHARED_DIR}/uploads" \
    "${SHARED_DIR}/var/log/website" \
    "${SHARED_DIR}/var/log/admin" \
    "${SHARED_DIR}/media/cache" \
    "${SHARED_DIR}/jwt"
chmod -R 775 "${SHARED_DIR}/var/log"

echo "==> JWT keypair koppelen (eenmalig handmatig aangemaakt, nooit door deploy overschreven)"
if [ ! -f "${SHARED_DIR}/jwt/private.pem" ]; then
    echo "    WAARSCHUWING: geen keypair in ${SHARED_DIR}/jwt — genereer met:"
    echo "    php ${RELEASE_DIR}/bin/console lexik:jwt:generate-keypair"
    echo "    en verplaats private.pem/public.pem naar ${SHARED_DIR}/jwt/"
fi
rm -rf "${RELEASE_DIR}/config/jwt"
ln -s "${SHARED_DIR}/jwt" "${RELEASE_DIR}/config/jwt"

echo "==> Persistente mappen symlinken"
rm -rf public/uploads
ln -s "${SHARED_DIR}/uploads" public/uploads

mkdir -p public/media
rm -rf public/media/cache
ln -s "${SHARED_DIR}/media/cache" public/media/cache

mkdir -p var
rm -rf var/log
ln -s "${SHARED_DIR}/var/log" var/log

chmod +x bin/console bin/websiteconsole bin/adminconsole

echo "==> Database migreren"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env="${APP_ENV}"

echo "==> Caches clearen en warmen (console, websiteconsole, adminconsole)"
for CONSOLE in console websiteconsole adminconsole; do
    php "bin/${CONSOLE}" cache:clear --no-interaction --env="${APP_ENV}"
    php "bin/${CONSOLE}" cache:warmup --no-interaction --env="${APP_ENV}"
done

echo "==> Symlinks omzetten naar nieuwe release"
ln -sfn "${RELEASE_DIR}" "${BASE_PATH}/current"
ln -sfn "${BASE_PATH}/current/public" "${DOMAIN_PATH}/public_html"

echo "==> Oude releases opruimen (laatste 4 behouden)"
cd "${BASE_PATH}/releases"
ls -1t | tail -n +5 | xargs -r rm -rf

echo "==> Release ${RELEASE_NAME} (${APP_ENV}) voltooid"
