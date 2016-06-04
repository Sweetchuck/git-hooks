
# Trigger Robo tasks from Git hooks

This package provides a bridge between the un-versioned `.git/hooks/*` scripts
and the [Robo](http://robo.li) tasks in your Git repository.

[![Build Status](https://travis-ci.org/Cheppers/git-hooks-robo.svg?branch=master)](https://travis-ci.org/Cheppers/git-hooks-robo)
[![Total Downloads](https://poser.pugx.org/cheppers/git-hooks-robo/downloads.png)](https://packagist.org/packages/cheppers/git-hooks-robo)


# When to use

If you want to put your Git hook scripts under VCS to share them with your
teammates then this is the tool you are looking for.


# How to use

1. Step into you existing package's directory (or create a new one with `git init && composer init`)
1. Run <pre><code>composer require --dev \
  'bernardosilva/git-hooks-installer-plugin' \
  'cheppers/git-hooks-robo' \
  'codegyre/robo'</code></pre>
1. Create a `RoboFile.php`. See the example bellow.


# Example RoboFile.php

```php
<?php

/**
 * Git hook tasks have to be started with 'githook' prefix.
 * So the method name format is: githook<GitHookNameInCamelCaseFormat>
 */
class RoboFile extends Robo\Tasks {

    /**
     * Demo pre-commit callback.
     */
    public function githookPreCommit() {
        $this->say('The Git pre-commit hook is running');
    }

}
```

**Links**
* https://github.com/BernardoSilva/git-hooks-installer-plugin
* https://github.com/Codegyre/Robo/blob/master/docs/index.md
