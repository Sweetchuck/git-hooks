
# sweetchuck/git-hooks

Triggers custom scripts from Git hooks.

This package provides a bridge between the un-versioned `./.git/hooks/*` scripts
and scripts in your Git repository.

[![CircleCI](https://circleci.com/gh/Sweetchuck/git-hooks.svg?style=svg)](https://circleci.com/gh/Sweetchuck/git-hooks)


## When to use

If you want to put your Git hook scripts under VCS to share them with your
teammates then this is the tool you are looking for.


## How to use

1. Step into you existing package's directory (or create a new one with `git
   init && composer init`)
2. Run `composer require --dev 'sweetchuck/git-hooks'`
3. Then you have two option
   1. Relay on Git hooks scripts which are shipped with this package and
      implement the logic in your `./.git-hooks` file.
   2. Or create a `./git-hooks` directory and create Git hook files in it. (eg:
      `./git-hooks/pre-commit`)
4. The deployment script will be automatically triggered by the
   `post-install-cmd` Composer event.


## Configuration

Example composer.json file:
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

Type: string

Default value: `vendor/sweetchuck/git-hooks/git-hooks` (dynamically detected)

If the Git version is >= v2.9 then this value will be used to set `git config
core.hooksPath <FOO>`. If Git is older than 2.9 then the content of this
directory will be symbolically linked or copied to `./.git/hooks` directory.


### Configuration - symlink

Type: boolean

Default value: `false`

This configuration option will be used only if Git version is older than v2.9.
Copy or symlink Git hook files from the original location (provided by the
`core.hooksPath` configuration) to the `./.git/hooks`.


## Example ./.git-hooks file

If you use the Git hooks script from this package
(`vendor/sweetchuck/git-hooks/git-hooks`) you will need custom script which
catches Git hooks add triggers something really useful.

Copy the content below into `./.git-hooks`
```bash
#!/usr/bin/env bash

echo "BEGIN Git hook: ${sghHookName}"

function sghExit ()
{
    echo "END   Git hook: ${sghHookName}"

    exit $1
}

# @todo Better detection for executables: php, composer.phar.
sghRobo="$(composer config 'bin-dir')/robo"

test -s "${sghBridge}.local" && . "${sghBridge}.local"

sghTask="githook:${sghHookName}"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "$sghRobo" || sghExit 0
"${sghRobo}" help "${sghTask}" 1> /dev/null 2>&1 || sghExit 0

if [ "$sghHasInput" = 'true' ]; then
    "$sghRobo" "${sghTask}" $@ <<< $(</dev/stdin) || sghExit $?
else
    "$sghRobo" "${sghTask}" $@ || sghExit $?
fi

sghExit 0
```


## Example ./RoboFile.php

```php
<?php

/**
 * Git hook tasks have to be started with 'githook' prefix.
 * So the method name format is: githook<GitHookNameInCamelCaseFormat>
 */
class RoboFile extends \Robo\Tasks
{

    /**
     * Demo pre-commit callback.
     */
    public function githookPreCommit()
    {
        $this->say('The Git pre-commit hook is running');
    }
}
```
