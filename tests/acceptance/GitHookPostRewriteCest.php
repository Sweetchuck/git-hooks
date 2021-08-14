<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPostRewriteCest extends GitHookCestBase
{
    protected function background(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $I->doGitCheckoutNewBranch('production');
        $I->doGitBranchCreate('feature-01');
        $I->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', 'foo');
        $I->doRunGitCheckout('feature-01');
        $I->doGitCommitNewFileWithMessageAndContent('bar.txt', 'Add bar.txt', 'bar');
    }

    public function triggerCurrentBranch(AcceptanceTester $I)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostRewrite is called',
            '>  Trigger: "rebase"',
            '>  stdInput line 1: "OLD_REV" "NEW_REV" ""',
            '>  Lines in stdInput: "1"',
        ]);

        $this->background($I);
        $I->doRunGitRebase('production');
        $I->assertExitCodeEquals('0');
        $I->assertStdErrContains($expectedStdError);
    }

    public function triggerOtherBranch(AcceptanceTester $I)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostRewrite is called',
            '>  Trigger: "rebase"',
            '>  stdInput line 1: "OLD_REV" "NEW_REV" ""',
            '>  Lines in stdInput: "1"',
        ]);

        $this->background($I);
        $I->doRunGitCheckout($this->defaultGitBranch);
        $I->doRunGitRebase('production', 'feature-01');
        $I->assertExitCodeEquals('0');
        $I->assertStdErrContains($expectedStdError);
    }
}
