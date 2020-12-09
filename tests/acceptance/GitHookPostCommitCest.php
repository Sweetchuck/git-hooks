<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPostCommitCest extends GitHookCestBase
{

    public function triggerPostCommitHook(AcceptanceTester $I)
    {
        $I->wantTo('Trigger the "post-commit" hook and check the StdError output');
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $I->assertStdErrContains('>  RoboFile::githookPostCommit is called');
    }
}
