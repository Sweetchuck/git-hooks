<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPostCheckoutCest extends GitHookCestBase
{
    protected function background(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $I->doGitBranchCreate('feature-1');
    }

    public function triggerBranchCheckout(AcceptanceTester $I)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostCheckout is called',
            '>  Old ref: "OLD_REF"',
            '>  New ref: "NEW_REF"',
            '>  Branch checkout: "yes"',
        ]);

        $this->background($I);
        $I->doRunGitCheckout('feature-1');
        $I->assertStdErrContains($expectedStdError);
    }

    public function triggerFileCheckout(AcceptanceTester $I)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostCheckout is called',
            '>  Old ref: "OLD_REF"',
            '>  New ref: "NEW_REF"',
            '>  Branch checkout: "no"',
        ]);

        $this->background($I);
        $I->doGitCommitNewFileWithMessageAndContent('CONTRIBUTE.md', 'WIP', '@todo');
        $I->doRunGitCheckout('feature-1');
        $I->doGitCheckoutFile($this->defaultGitBranch, 'CONTRIBUTE.md');
        $I->assertStdErrContains($expectedStdError);
    }
}
