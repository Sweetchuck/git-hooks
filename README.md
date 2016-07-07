
# Trigger Robo tasks from Git hooks

This package provides a bridge between the un-versioned `.git/hooks/*` scripts
and scripts in your Git repository.

[![Build Status](https://travis-ci.org/Cheppers/git-hooks-robo.svg?branch=master)](https://travis-ci.org/Cheppers/git-hooks-robo)
[![Total Downloads](https://poser.pugx.org/cheppers/git-hooks-robo/downloads.png)](https://packagist.org/packages/cheppers/git-hooks-robo)


## When to use

If you want to put your Git hook scripts under VCS to share them with your
teammates then this is the tool you are looking for.


## How to use

1. Step into you existing package's directory (or create a new one with `git init && composer init`)
1. See the example `composer.json` below and copy the `repositories` part to 
   your `composer.json`.
1. Run <pre><code>composer require --dev \
  'bernardosilva/git-hooks-installer-plugin' \
  'codegyre/robo'</code></pre>
1. Run <pre><code>composer require --dev 'cheppers/git-hooks'</code></pre>
1. Create a `.git-hooks` file. See the example bellow.
1. Create a `RoboFile.php`. See the example bellow.


## Example composer.json

```JSON
{
    "name": "my/package-01",
    "description": "My description.",
    "type": "library",
    "license": "GPL-2.0",
    "minimum-stability": "dev",
    "prefer-stable": true,
    "require": {},
    "require-dev": {},
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "cheppers/git-hooks-robo",
                "version": "0.0.5",
                "type": "git-hook",
                "dist": {
                    "type": "tar",
                    "url": "https://github.com/Cheppers/git-hooks-robo/releases/download/v0.0.5/v0.0.5.tar.gz"
                },
                "source": {
                    "type": "git",
                    "url": "https://github.com/Cheppers/git-hooks-robo.git",
                    "reference": "v0.0.5"
                }
            }
        }
    ]
}
```


# Example .git-hooks

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
