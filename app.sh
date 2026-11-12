#!/bin/bash

# https://stackoverflow.com/a/1482133
SCRIPT_DIR="$(dirname -- "$(readlink -f -- "$0";)";)"
cd "${SCRIPT_DIR}"

# Allow port setting via first argument:
PORT=8000
if [[ ! "$1" == "" ]]
then
    PORT="$1"
fi

# Handle multiple simultaneous requests in Linux only:
export PHP_CLI_SERVER_WORKERS=4

FILTER=".*"

if [[ "$@" =~ "--quiet" ]]
then
    FILTER="INFO"
fi

php -S 0.0.0.0:$PORT index.php --enable-mbstring 2>&1 | grep -E "${FILTER}"
