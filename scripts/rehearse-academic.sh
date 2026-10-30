#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
umask 077
for binary in mysqld mysqldump mysql mysqladmin php; do command -v "$binary" >/dev/null; done
mkdir -p storage/app/private/academic-rehearsals
archive=$(mktemp -d "$PWD/storage/app/private/academic-rehearsals/run.XXXXXX")
php scripts/rehearse-academic.php export "$archive"
php scripts/rehearse-academic.php files "$archive"
work=$(mktemp -d /tmp/sgpi-rehearsal.XXXXXX)
socket="$work/mysql.sock"
cleanup() {
 mysqladmin --no-defaults --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
 rm -rf -- "$work"
}
trap cleanup EXIT
mkdir "$work/data"
mysqld --no-defaults --initialize-insecure --datadir="$work/data" --log-error="$work/init.log" --user="$(id -un)"
mysqld --no-defaults --datadir="$work/data" --socket="$socket" --skip-networking --mysqlx=0 --event-scheduler=OFF --pid-file="$work/mysql.pid" --log-error="$work/server.log" --user="$(id -un)" &
for attempt in $(seq 1 100); do
 if mysqladmin --no-defaults --socket="$socket" -uroot ping >/dev/null 2>&1; then break; fi
 sleep .1
done
mysql --no-defaults --socket="$socket" -uroot -e 'CREATE DATABASE sgpi_rehearsal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
mysql --no-defaults --socket="$socket" -uroot sgpi_rehearsal < "$archive/source.sql" 2> "$archive/import-errors.log"
php scripts/rehearse-academic.php migrate-copy "$archive" "$socket"
printf 'Informe privado: %s/rehearsal-result.json\n' "$archive"
