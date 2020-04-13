<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPrepareCommitMsgCest
{
    protected function background(AcceptanceTester $I)
    {
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doCreateFile('README.md');
        $I->doGitAdd('README.md');
    }

    public function triggerWithCommitMessage(AcceptanceTester $I)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPrepareCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
            ">  Description: 'message'",
        ]);

        $this->background($I);
        $I->doGitCommit('Initial commit');
        $I->assertExitCodeEquals(0);
        $I->assertStdErrContains($expectedStdError);
    }

    protected function triggerWithoutCommitMessageCases(): array
    {
        return [
            'positive' => [
                'editor' => 'true',
                'exitCode' => 0,
            ],
            'negative' => [
                'editor' => 'false',
                'exitCode' => 1,
            ],
        ];
    }

    /**
     * @dataProvider triggerWithoutCommitMessageCases
     */
    public function triggerWithoutCommitMessage(AcceptanceTester $I, Example $example)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPrepareCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
            ">  Description: ''",
        ]);

        $this->background($I);
        $I->doGitConfigSetCoreEditor($example['editor']);
        $I->doGitCommit();
        $I->assertStdErrContains($expectedStdError);
        //$I->assertExitCodeEquals($example['exitCode']);
    }
}
