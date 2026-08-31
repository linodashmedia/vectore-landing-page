#!/usr/bin/env bash
#
# Boot shim, run before the stock WordPress entrypoint.
#
# Two jobs, both of which have to happen before Apache starts and neither of
# which Apache can do for itself.
set -euo pipefail

# 1. Railway assigns the port at runtime and Apache cannot read an environment
#    variable inside a Listen directive, so the port is written into the config
#    on the way up.
PORT="${PORT:-80}"
{
	echo "Listen ${PORT}"
} > /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!g" /etc/apache2/sites-available/*.conf

# 2. Railway's MySQL plugin publishes MYSQLHOST / MYSQLUSER / ... while the
#    WordPress image reads WORDPRESS_DB_*. Mapping them here means the service
#    can be wired up with Railway's own reference variables and nothing has to
#    be copied by hand (copied-by-hand credentials are how a rotated password
#    takes a site down three months later).
if [ -z "${WORDPRESS_DB_HOST:-}" ] && [ -n "${MYSQLHOST:-}" ]; then
	export WORDPRESS_DB_HOST="${MYSQLHOST}:${MYSQLPORT:-3306}"
	export WORDPRESS_DB_USER="${MYSQLUSER:-root}"
	export WORDPRESS_DB_PASSWORD="${MYSQLPASSWORD:-}"
	export WORDPRESS_DB_NAME="${MYSQLDATABASE:-railway}"
	echo "[vectore] mapped Railway MySQL vars -> WORDPRESS_DB_* (host ${MYSQLHOST})"
fi

# The uploads volume is mounted after the image is built, so it arrives owned by
# root and PHP cannot write to it. Fixed on every boot rather than once, because
# a re-mounted volume comes back root-owned again.
if [ -d /var/www/html/wp-content/uploads ]; then
	chown -R www-data:www-data /var/www/html/wp-content/uploads || true
fi

exec docker-entrypoint.sh "$@"
