
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
2. Run `composer require 'sweetchuck/git-hooks'`
3. Then you have two option
   1. Relay on the git hooks scripts which are shipped with this packag and
      implement the logic in your `./.git-hooks` file.
   2. Or create a `./git-hooks` directory and create Git hook files in it. (eg:
      `./git-hooks/pre-commit`)
4. The deployment script will be automatically triggered by the
   `post-install-cmd` Composer event.


## Example composer.json

```JSON
{
    "require": {
        "sweetchuck/git-hooks": "dev-master"
    },
    "scripts": {
        "post-install-cmd": [
            "\\Sweetchuck\\GitHooks\\Composer\\Scripts::postInstallCmd"
        ]
    }
}
```


## Configuration

```json
{
    "extra": {
        "sweetchuck/git-hooks": {
            "core.hooksPath": "git-hooks",
            "symlink": false
        }
    }
}
```


### Configuration - symlink

Type: boolean

Default value: false

Copy or symlink Git hook files from the original location to the `./.git/hooks`.


### Configuration - core.hooksPath

Type: string

Default value: git-hooks

When this option is not empty then it allows to use the new feature of the Git
v2.9


## Example ./.git-hooks file

The file below runs a Robo command corresponding the name of the current Git
hook.

```bash
#!/usr/bin/env bash

# @todo Better detection for executables: php, composer.phar and robo.
robo="$(composer config 'bin-dir')/robo"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "$robo" || exit 0
"$robo" help "githook:$sghHookName" 1> /dev/null 2>&1 || exit 0

if [ "$sghHasInput" = 'true' ]; then
    "$robo" "githook:$sghHookName" $@ <<< $(</dev/stdin) || exit $?
else
    "$robo" "githook:$sghHookName" $@ || exit $?
fi

exit 0
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


## Links

* https://robo.li/
