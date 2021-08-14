<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Sweetchuck\GitHooks\Test\AcceptanceTester;

/**
 * @todo More test cases with symlink true|false
 */
class CommandCest
{
    public function gitHooksDeploy(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doComposer(['-vvv', 'git-hooks:deploy']);
        $I->assertStdErrContains('Git hooks have been deployed by the core.hooksPath configuration.');
        $I->assertExitCodeEquals('0');
    }

    public function gitHooksRecall(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doComposer(['-vvv', 'git-hooks:recall']);
        $I->assertStdErrContains("cd '.' && git config --unset 'core.hooksPath'");
        $I->assertExitCodeEquals('0');
    }
}
