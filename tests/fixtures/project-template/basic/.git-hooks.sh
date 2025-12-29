#!/usr/bin/env bash

: "${sghHookName:?'argument is required'}"
: "${sghHasInput:?'argument is required'}"

# @todo Better detection for executables: php, composer.phar and robo.
robo="$(composer config 'bin-dir')/robo"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "$robo" || exit 0
"${robo}" help "githook:${sghHookName}" 1> /dev/null 2>&1 || exit 0

if [ "${sghHasInput}" = 'true' ]; then
	"${robo}" "githook:${sghHookName}" "${@}" <&0 || exit $?
else
	"${robo}" "githook:${sghHookName}" "${@}" || exit $?
fi

exit 0
