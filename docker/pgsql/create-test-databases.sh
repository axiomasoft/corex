#!/bin/sh
# Creates the extra PostgreSQL databases the pg-lane suites use, matching the
# CI "Prepare PostgreSQL test databases" step. The postgres image runs this
# once, when it initialises an empty data volume; POSTGRES_DB is created by
# the image itself.
set -eu

for database in \
    corex_search_a_test \
    corex_search_b_test \
    corex_budget_test \
    corex_control_test \
    corex_search_control_test \
    corex_sink_test
do
    if [ "$database" = "$POSTGRES_DB" ]; then
        continue
    fi
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
        -c "CREATE DATABASE \"$database\""
done
