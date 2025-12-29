<?php

declare(strict_types=1);

namespace Sweetchuck\GitHooks\Tests\Acceptance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GitHookTest extends TestBase
{
    // region commit-msg
    /**
     * @return array<string, mixed>
     */
    public static function triggerCommitMsgHookCases(): array
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

    #[Test]
    #[DataProvider('triggerCommitMsgHookCases')]
    public function triggerCommitMsgHook(string $message, int $exitCode): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
        ]);

        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doCreateFile('README.md');
        $this->doGitAdd(['README.md']);
        $this->doGitCommit($message);
        $this->assertStdErrContains($expectedStdError);
        $this->assertExitCodeEquals($exitCode);
    }
    // endregion

    // region post-checkout
    protected function doPostCheckoutBackground(): static
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $this->doGitBranchCreate('feature-1');

        return $this;
    }

    #[Test]
    public function triggerPostCheckoutBranch(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostCheckout is called',
            '>  Old ref: "OLD_REF"',
            '>  New ref: "NEW_REF"',
            '>  Branch checkout: "yes"',
        ]);

        $this->doPostCheckoutBackground();
        $this->doRunGitCheckout('feature-1');
        $this->assertStdErrContains($expectedStdError);
    }

    #[Test]
    public function triggerPostCheckoutFile(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostCheckout is called',
            '>  Old ref: "OLD_REF"',
            '>  New ref: "NEW_REF"',
            '>  Branch checkout: "no"',
        ]);

        $this->doPostCheckoutBackground();
        $this->doGitCommitNewFileWithMessageAndContent('CONTRIBUTE.md', 'WIP', '@todo');
        $this->doRunGitCheckout('feature-1');
        $this->doGitCheckoutFile(static::$defaultGitBranch, 'CONTRIBUTE.md');
        $this->assertStdErrContains($expectedStdError);
    }
    // endregion

    // region post-commit
    #[Test]
    public function triggerPostCommitHook(): void
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $this->assertStdErrContains('>  RoboFile::githookPostCommit is called');
    }
    // endregion

    // region post-merge
    protected function doPostMergeBackground(): static
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $this->doGitCheckoutNewBranch('feature-01');
        $this->doGitCommitNewFileWithMessageAndContent('robots.txt', 'Add robots.txt', 'foo');
        $this->doRunGitCheckout(static::$defaultGitBranch);

        return $this;
    }

    #[Test]
    public function triggerPostMergeNormal(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostMerge is called',
            '>  Squash: 0',
        ]);

        $this->doPostMergeBackground();
        $this->doGitMerge('feature-01', sprintf('Merge feature-01 into %s', static::$defaultGitBranch));
        $this->assertStdErrContains($expectedStdError);
    }

    #[Test]
    public function triggerPostMergeSquash(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostMerge is called',
            '>  Squash: 1',
        ]);

        $this->doPostMergeBackground();
        $this->doGitMergeSquash('feature-01', sprintf('Merge feature-01 into %s', static::$defaultGitBranch));
        $this->assertStdErrContains($expectedStdError);
    }
    // endregion

    // region post-rewrite
    protected function doPostRewriteBackground(): static
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitCommitNewFileWithMessageAndContent('README.md', 'Initial commit', '@todo');
        $this->doGitCheckoutNewBranch('production');
        $this->doGitBranchCreate('feature-01');
        $this->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', 'foo');
        $this->doRunGitCheckout('feature-01');
        $this->doGitCommitNewFileWithMessageAndContent('bar.txt', 'Add bar.txt', 'bar');

        return $this;
    }

    #[Test]
    public function triggerPostRewriteCurrentBranch(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostRewrite is called',
            '>  Trigger: "rebase"',
            '>  stdInput line 1: "OLD_REV" "NEW_REV" ""',
            '>  Lines in stdInput: "1"',
        ]);

        $this->doPostRewriteBackground();
        $this->doRunGitRebase('production');
        $this->assertExitCodeEquals(0);
        $this->assertStdErrContains($expectedStdError);
    }

    public function triggerPostRewriteOtherBranch(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPostRewrite is called',
            '>  Trigger: "rebase"',
            '>  stdInput line 1: "OLD_REV" "NEW_REV" ""',
            '>  Lines in stdInput: "1"',
        ]);

        $this->doPostRewriteBackground();
        $this->doRunGitCheckout(static::$defaultGitBranch);
        $this->doRunGitRebase('production', 'feature-01');
        $this->assertExitCodeEquals(0);
        $this->assertStdErrContains($expectedStdError);
    }
    // endregion

    // region pre-commit
    /**
     * @return array<string, mixed>
     */
    public static function triggerPreCommitCases(): array
    {
        return [
            'positive' => [
                'fileName' => 'true.txt',
                'numOfCommits' => 1,
            ],
            'negative' => [
                'fileName' => 'false.txt',
                'numOfCommits' => 0,
            ],
        ];
    }

    #[Test]
    #[DataProvider('triggerPreCommitCases')]
    public function triggerPreCommit(string $fileName, int $numOfCommits): void
    {
        $expectedStdError = implode("\n", [
            ">  RoboFile::githookPreCommit is called",
        ]);

        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doCreateFile($fileName);
        $this->doGitAdd([$fileName]);
        $this->doGitCommit('Initial commit');
        $this->assertStdErrContains($expectedStdError);
        $this->assertGitLogLength($numOfCommits);
    }
    // endregion

    // region prepare-commit-msg
    protected function doPrepareCommitMsgBackground(): static
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doCreateFile('README.md');
        $this->doGitAdd(['README.md']);

        return $this;
    }

    #[Test]
    public function triggerPrepareCommitMsgWithMessage(): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPrepareCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
            ">  Description: 'message'",
        ]);

        $this->doPrepareCommitMsgBackground();
        $this->doGitCommit('Initial commit');
        $this->assertExitCodeEquals(0);
        $this->assertStdErrContains($expectedStdError);
    }

    /**
     * @return array<string, mixed>
     */
    public static function triggerPrepareCommitMsgWithoutMessageCases(): array
    {
        return [
            'positive' => [
                'editor' => 'true',
            ],
            'negative' => [
                'editor' => 'false',
            ],
        ];
    }

    #[Test]
    #[DataProvider('triggerPrepareCommitMsgWithoutMessageCases')]
    public function triggerPrepareCommitMsgWithoutMessage(string $editor): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPrepareCommitMsg is called',
            ">  File name: '.git/COMMIT_EDITMSG'",
            ">  Description: ''",
        ]);

        $this->doPrepareCommitMsgBackground();
        $this->doGitConfigSetCoreEditor($editor);
        $this->doGitCommit();
        $this->assertStdErrContains($expectedStdError);
        // @todo Check exit code.
    }
    // endregion

    // region pre-push
    /**
     * @return array<string, mixed>
     */
    public static function triggerPrePushCases():array
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

    #[Test]
    #[DataProvider('triggerPrePushCases')]
    public function triggerPrePush(string $commitMsg, int $exitCode): void
    {
        $expectedStdOutput = implode("\n", [
            '>  RoboFile::githookPrePush is called',
            '>  Remote name: origin',
            '>  Remote URI: ../b-01',
            '>  Lines in stdInput: 1',
        ]);

        $this->doGitInitBare('b-01');
        $this->doChangeWorkingDirectory('..');
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitRemoteAdd('origin', '../b-01');
        $this->doCreateFile('README.md');
        $this->doGitAdd(['README.md']);
        $this->doGitCommit($commitMsg);
        $this->doGitPush('origin', static::$defaultGitBranch);
        $this->assertExitCodeEquals($exitCode);
        $this->assertStdOutContains($expectedStdOutput);
    }
    // endregion

    // region pre-rebase
    /**
     * @var array<string, int>
     */
    protected static array $gitRebaseExitCodes = [
        'old' => 128,
        'new' => 1,
    ];

    protected function doPreRebaseBackground(): static
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doGitCommitNewFileWithMessageAndContent(
            'README.md',
            'Initial commit',
            '@todo',
        );

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public static function triggerPreRebaseCurrentBranchCases(): array
    {
        $gitAge = version_compare(static::getGitVersion(), '2.47.0', '<')
            ? 'old'
            : 'new';

        return [
            'positive' => [
                'currentBranch' => 'feature-01',
                'upstream' => 'protected',
                'exitCode' => 0,
            ],
            'negative' => [
                'currentBranch' => 'protected',
                'upstream' => 'feature-01',
                'exitCode' => static::$gitRebaseExitCodes[$gitAge],
            ],
        ];
    }

    #[Test]
    #[DataProvider('triggerPreRebaseCurrentBranchCases')]
    public function triggerPreRebaseCurrentBranch(
        string $currentBranch,
        string $upstream,
        int $exitCode,
    ): void {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPreRebase is called',
            ">  Current branch: \"{$currentBranch}\"",
            ">  Upstream: \"{$upstream}\"",
            '>  Subject branch: ""',
        ]);

        $this->doPreRebaseBackground();
        $this->doGitCheckoutNewBranch($upstream);
        $this->doGitBranchCreate($currentBranch);
        $this->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', '@todo');
        $this->doRunGitCheckout($currentBranch);
        $this->doRunGitRebase($upstream);
        $this->assertStdErrContains($expectedStdError);
        $this->assertExitCodeEquals($exitCode);
    }

    /**
     * @return array<string, mixed>
     */
    public static function triggerPreRebaseOtherBranchCases(): array
    {
        $gitAge = version_compare(static::getGitVersion(), '2.47.0', '<')
            ? 'old'
            : 'new';

        return [
            'positive' => [
                'subjectBranch' => 'feature-01',
                'upstream' => 'protected',
                'exitCode' => 0,
            ],
            'negative' => [
                'subjectBranch' => 'protected',
                'upstream' => 'feature-01',
                'exitCode' => static::$gitRebaseExitCodes[$gitAge],
            ],
        ];
    }

    #[Test]
    #[DataProvider('triggerPreRebaseOtherBranchCases')]
    public function triggerPreRebaseOtherBranch(string $subjectBranch, string $upstream, int $exitCode): void
    {
        $expectedStdError = implode("\n", [
            '>  RoboFile::githookPreRebase is called',
            '>  Current branch: "' . static::$defaultGitBranch . '"',
            ">  Upstream: \"{$upstream}\"",
            ">  Subject branch: \"{$subjectBranch}\"",
        ]);

        $this->doPreRebaseBackground();
        $this->doGitCheckoutNewBranch($upstream);
        $this->doGitBranchCreate($subjectBranch);
        $this->doGitCommitNewFileWithMessageAndContent('foo.txt', 'Add foo.txt', '@todo');
        $this->doRunGitCheckout(static::$defaultGitBranch);
        $this->doRunGitRebase($upstream, $subjectBranch);
        $this->assertStdErrContains($expectedStdError);
        $this->assertExitCodeEquals($exitCode);
    }
    // endregion
}
