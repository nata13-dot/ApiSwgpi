#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for binary in mysqld mysql mysqladmin php; do command -v "$binary" >/dev/null || { echo "Missing $binary" >&2; exit 1; }; done
work=$(mktemp -d /tmp/sgpi-academic-test.XXXXXX)
socket="$work/mysql.sock"
cleanup() {
  mysqladmin --no-defaults --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true
  rm -rf -- "$work"
}
trap cleanup EXIT
mkdir "$work/data"
mysqld --no-defaults --initialize-insecure --datadir="$work/data" --log-error="$work/init.log" --user="$(id -un)"
mysqld --no-defaults --datadir="$work/data" --socket="$socket" --skip-networking --mysqlx=0 --pid-file="$work/mysql.pid" --log-error="$work/server.log" --user="$(id -un)" &
for attempt in $(seq 1 100); do
  if mysqladmin --no-defaults --socket="$socket" -uroot ping >/dev/null 2>&1; then break; fi
  sleep .1
done
mysql --no-defaults --socket="$socket" -uroot -e 'CREATE DATABASE sgpi_test_academic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
SGPI_TEST_MYSQL_SOCKET="$socket" APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL='' CACHE_STORE=array \
  php vendor/bin/phpunit --stop-on-error tests/Integration/AcademicWorkflowsMySqlTest.php "$@"
