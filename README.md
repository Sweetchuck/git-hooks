
# Trigger custom scripts from Git hooks

This package provides a bridge between the un-versioned `.git/hooks/*` scripts
and scripts in your Git repository.

[![Build Status](https://travis-ci.org/Cheppers/git-hooks.svg?branch=master)](https://travis-ci.org/Cheppers/git-hooks)
[![Total Downloads](https://poser.pugx.org/cheppers/git-hooks/downloads.png)](https://packagist.org/packages/cheppers/git-hooks)


## When to use

If you want to put your Git hook scripts under VCS to share them with your
teammates then this is the tool you are looking for.


## How to use

1. Step into you existing package's directory (or create a new one with `git init && composer init`)
1. Run <pre><code>composer require 'cheppers/git-hooks'</code></pre>
1. Then you have two option
    1. Relay on the git hooks scripts which are shipped with this package 
       and implement the logic in your `.git-hooks` file.
    1. Or create a `git-hooks` directory and create git hook files (`git-hooks/pre-commit`) in it.
1. And trigger the deployment script on the `post-install-cmd` event.


## Example composer.json

```JSON
{
    "name": "my/package-01",
    "description": "My description.",
    "type": "library",
    "license": "GPL-2.0",
    "minimum-stability": "dev",
    "prefer-stable": true,
    "require": {
        "cheppers/git-hooks": "dev-master"
    },
    "scripts": {
        "post-install-cmd": [
            "@deploy-git-hooks"
        ],
        "deploy-git-hooks": "\\Cheppers\\GitHooks\\Main::deploy"
    },
    "extra": {
        "cheppers/git-hooks": {
            "core.hooksPath": "git-hooks",
            "symlink": false
        }
    }
}
```


# Configuration

In the example `composer.json` above you can see two configurable option 
under the `"extra": {"cheppers/git-hooks": {}}`.


## Configuration symlink

This option will be used when you have a `git-hooks` directory.


## Configuration core.hooksPath

When this option is `true` then it allows to use the new feature of the Git v2.9

Actually if you and all of your development team use Git v2.9 then you don't need
this package at all.


# Example .git-hooks for Robo task runner

```bash
#!/usr/bin/env bash

hook=$(basename "${0}")

# @todo Better detection for executables: php, composer.phar and robo.
robo="$(composer config 'bin-dir')/robo"

# Exit without error if "robo" doesn't exists or it has no corresponding task.
test -x "$robo" || exit 0
"$robo" help "githook:$hook" 1> /dev/null 2>&1 || exit 0

if [ "$hasInput" = 'true' ]; then
    "$robo" "githook:$hook" $@ <<< $(</dev/stdin) || exit $?
else
    "$robo" "githook:$hook" $@ || exit $?
fi

exit 0
```

## Example RoboFile.php

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

* https://github.com/BernardoSilva/git-hooks-installer-plugin
* https://github.com/Codegyre/Robo/blob/master/docs/index.md
