<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Tests\AcceptanceTester;

class GitHookPrePushCest extends GitHookCestBase
{

    protected function triggerCases():array
    {
        return [
            'positive' => [
                'commitMsg' => 'Valid',
                'exitCode' => 0,
            ],
            'negative' => [
                'commitMsg' => 'Invalid pre-push',
                'exitCode' => 1,
            ],
        ];
    }

    /**
     * @dataProvider triggerCases
     */
    public function trigger(AcceptanceTester $I, Example $example)
    {
        $expectedStdOutput = implode("\n", [
            '>  RoboFile::githookPrePush is called',
            '>  Remote name: origin',
            '>  Remote URI: ../b-01',
            '>  Lines in stdInput: 1',
        ]);

        $I->doGitInitBare('b-01');
        $I->doChangeWorkingDirectory('..');
        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doGitRemoteAdd('origin', '../b-01');
        $I->doCreateFile('README.md');
        $I->doGitAdd('README.md');
        $I->doGitCommit($example['commitMsg']);
        $I->doGitPush('origin', $this->defaultGitBranch);
        $I->assertExitCodeEquals((string) $example['exitCode']);
        $I->assertStdOutContains($expectedStdOutput);
    }
}
