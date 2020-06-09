#!/usr/bin/env bash

OC_PATH=../../../../
OCC=${OC_PATH}occ
SCENARIO_TO_RUN=$1
HIDE_OC_LOGS=$2

# Nextcloud integration tests composer
(
    cd ${OC_PATH}build/integration
    composer install
)
INSTALLED=$($OCC status | grep installed: | cut -d " " -f 5)

if [ "$INSTALLED" == "true" ]; then
    $OCC app:enable deck
else
    if [ "$SCENARIO_TO_RUN" != "setup_features/setup.feature" ]; then
        echo "Nextcloud instance needs to be installed" >&2
        exit 1
    fi
fi

composer install
composer dump-autoload

# avoid port collision on jenkins - use $EXECUTOR_NUMBER
if [ -z "$EXECUTOR_NUMBER" ]; then
    EXECUTOR_NUMBER=0
fi
PORT=$((9090 + $EXECUTOR_NUMBER))
echo $PORT
php -S localhost:$PORT -t $OC_PATH &
PHPPID=$!
echo $PHPPID

export TEST_SERVER_URL="http://localhost:$PORT/ocs/"

vendor/bin/behat
RESULT=$?

kill $PHPPID

echo "runsh: Exit code: $RESULT"
exit $RESULT
