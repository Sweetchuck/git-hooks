# sweetchuck/git-hooks

Triggers custom scripts from Git hooks.

This package provides a bridge between the un-versioned `./.git/hooks/*` scripts
and scripts in your Git repository.

[![CircleCI](https://dl.circleci.com/status-badge/img/gh/Sweetchuck/git-hooks/tree/3.x.svg?style=svg)](https://dl.circleci.com/status-badge/redirect/gh/Sweetchuck/git-hooks/tree/3.x)


## When to use

If you want to put your Git hook scripts under VCS to share them with your teammates,
then this is the tool you are looking for.


## How to use

1. Step into you existing package's directory (or create a new one with `git init && composer init`)
2. Run `composer require --dev 'sweetchuck/git-hooks'`
3. Then you have two options
   1. Rely on Git hook scripts which are shipped with this package and implement
      the logic in your `./.git-hooks.sh` file.
   2. Or create a `./git-hooks` directory and create Git hook files in it. (eg: `./git-hooks/pre-commit`)
4. The deployment script will be automatically triggered by the
   `post-install-cmd` Composer event.


## Configuration

Example **composer.json** file:
```json
{
    "extra": {
        "sweetchuck/git-hooks": {
            "core.hooksPath": "./git-hooks",
            "symlink": true
        }
    }
}
```


### Configuration - core.hooksPath

Type: `string`
Default value: `vendor/sweetchuck/git-hooks/git-hooks` (dynamically detected)

If the Git version is >= `v2.9` (2016-06), then this value will be used to set
`git config core.hooksPath <PATH>`. If Git is older than `v2.9`, then the content of this
directory will be symbolically linked or copied into `./.git/hooks/` directory.


### Configuration - symlink

Type: `boolean`
Default value: `false`

This configuration option will be used only if a Git version is older than `v2.9`.
Copy or symlink Git hook files from the original location (provided by the
`core.hooksPath` configuration) to the `./.git/hooks/`.


## Example ./.git-hooks.sh file

If you use the Git hooks script from this package
(`vendor/sweetchuck/git-hooks/git-hooks`) you will need custom script which
catches Git hooks add triggers something really useful.

Copy the content below into `./.git-hooks.sh`
```bash
#!/usr/bin/env bash

: "${sghHookName:?'argument is required'}"
: "${sghHasInput:?'argument is required'}"

echo 1>&2 "BEGIN Git hook: ${sghHookName}"

function sghExit ()
{
	if [[ "${2}" != '' ]]; then
		echo 1>&2 "${2}"
	fi

	echo 1>&2 "END   Git hook: ${sghHookName}"

	exit "${1}"
}

# @todo Better detection for executables: php, composer.phar and robo.
robo="$(composer config 'bin-dir')/robo"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "${robo}" || sghExit 0 'robo executable not found'
"${robo}" help "githook:${sghHookName}" 1> /dev/null 2>&1 || sghExit 0 'robo task does not exist.'

if [ "${sghHasInput}" = 'true' ]; then
	"${robo}" "githook:${sghHookName}" "${@}" <&0 || sghExit $?
else
	"${robo}" "githook:${sghHookName}" "${@}" || sghExit $?
fi

sghExit 0
```


## Example ./RoboFile.php

```php
<?php

class RoboFile extends \Robo\Tasks
{

    #[\Consolidation\AnnotatedCommand\Attributes\Command(
        name: 'githook:pre-commit',
    )]
    #[\Consolidation\AnnotatedCommand\Attributes\Help(
        description: 'Git "pre-commit" hook callback.',
        hidden: true,
    )]
    public function cmdGitHookPreCommitExecute(): int
    {
        $this->say('The Git pre-commit hook is running');

        return 0;
    }
}
```
