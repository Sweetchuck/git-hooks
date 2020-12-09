<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookCestBase
{

    /**
     * @var string
     */
    protected $defaultGitBranch = '1.x';
}
