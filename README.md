
# Trigger Robo tasks from Git hooks


# When to use

If you want to put your Git hook script under VCS to share them with your
teammates then this is the tool you are looking for.

# How to use

1. Run `composer require 'bernardosilva/git-hooks-installer-plugin:~1.0'`
1. Run `composer require 'cheppers/git-hooks-robo'`
1. Run `composer require 'codegyre/robo:~0.7'`
1. Create a `RoboFile.php` with the following content:
```php
<?php

class RoboFile extends Robo\Tasks {

    /**
     * The method name format is: githook<GitHookNameInCamelCaseformat>
     */
    public function githookPreCommit() {
        $this->say('The Git pre-commit hook is running');
    }

}
```

**Links**
* https://github.com/BernardoSilva/git-hooks-installer-plugin
* https://github.com/Codegyre/Robo/blob/0.7.2/docs/getting-started.md
