<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookCommitMsgCest extends GitHookCestBase
{

    protected function triggerCommitMsgHookCases():array
    {
        return [
            'positive' => [
                'message' => 'Valid',
                'exitCode' => 0,
            ],
            'negative' => [
                'message' => 'Invalid commit-msg',
                'exitCode' => 1,
            ],
        ];
    }

    /**
     * @dataProvider triggerCommitMsgHookCases
     */
    public function triggerCommitMsgHook(AcceptanceTester $I, Example $example)
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
        ]);

        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doCreateFile('README.md');
        $I->doGitAdd('README.md');
        $I->doGitCommit($example['message']);
        $I->assertStdErrContains($expectedStdError);
        $I->assertExitCodeEquals($example['exitCode']);
    }
}
