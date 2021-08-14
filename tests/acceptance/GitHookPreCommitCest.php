<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

use Codeception\Example;
use Sweetchuck\GitHooks\Test\AcceptanceTester;

class GitHookPreCommitCest extends GitHookCestBase
{

    protected function triggerCases(): array
    {
        return [
            'positive' => [
                'fileName' => 'true.txt',
                'commits' => 1,
            ],
            'negative' => [
                'fileName' => 'false.txt',
                'commits' => 0,
            ],
        ];
    }

    /**
     * @dataProvider triggerCases
     */
    public function trigger(AcceptanceTester $I, Example $example)
    {
        $expectedStdError = implode("\n", [
            ">  RoboFile::githookPreCommit is called",
        ]);

        $I->doCreateProjectInstance('basic', 'p-01');
        $I->doCreateFile($example['fileName']);
        $I->doGitAdd($example['fileName']);
        $I->doGitCommit('Initial commit');
        $I->assertStdErrContains($expectedStdError);
        $I->assertGitLogLength((string) $example['commits']);
    }
}
