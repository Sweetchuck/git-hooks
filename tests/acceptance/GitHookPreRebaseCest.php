<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPreRebaseCest extends GitHookCestBase
{
    protected function background(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doGitCommitNewFileWithMessageAndContent('REDME.md', 'Initial commit', '@todo');
    }

    protected function triggerCurrentBranchCases():array
    {
        return [
            'positive' => [
                'currentBranch' => 'feature-01',
                'upstream' => 'protected',
                'exitCode' => 0,
            ],
            'negative' => [
                'currentBranch' => 'protected',
                'upstream' => 'feature-01',
                'exitCode' => 128,
            ],
        ];
    }

    /**
     * @dataProvider triggerCurrentBranchCases
     */
    public function triggerCurrentBranch(AcceptanceTester $I, Example $example)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPreRebase is called',
            ">  Current branch: \"{$example['currentBranch']}\"",
            ">  Upstream: \"{$example['upstream']}\"",
            '>  Subject branch: ""',
        ]);

        $this->background($I);
        $I->doGitCheckoutNewBranch($example['upstream']);
        $I->doGitBranchCreate($example['currentBranch']);
        $I->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', '@todo');
        $I->doRunGitCheckout($example['currentBranch']);
        $I->doRunGitRebase($example['upstream']);
        $I->assertStdErrContains($expectedStdError);
        $I->assertExitCodeEquals((string) $example['exitCode']);
    }

    protected function triggerOtherBranchCases(): array
    {
        return [
            'positive' => [
                'subjectBranch' => 'feature-01',
                'upstream' => 'protected',
                'exitCode' => 0,
            ],
            'negative' => [
                'subjectBranch' => 'protected',
                'upstream' => 'feature-01',
                'exitCode' => 128,
            ],
        ];
    }

    /**
     * @dataProvider triggerOtherBranchCases
     */
    public function triggerOtherBranch(AcceptanceTester $I, Example $example)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPreRebase is called',
            ">  Current branch: \"{$this->defaultGitBranch}\"",
            ">  Upstream: \"{$example['upstream']}\"",
            ">  Subject branch: \"{$example['subjectBranch']}\"",
        ]);

        $this->background($I);
        $I->doGitCheckoutNewBranch($example['upstream']);
        $I->doGitBranchCreate($example['subjectBranch']);
        $I->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', '@todo');
        $I->doRunGitCheckout($this->defaultGitBranch);
        $I->doRunGitRebase($example['upstream'], $example['subjectBranch']);
        $I->assertStdErrContains($expectedStdError);
        $I->assertExitCodeEquals((string) $example['exitCode']);
    }
}
