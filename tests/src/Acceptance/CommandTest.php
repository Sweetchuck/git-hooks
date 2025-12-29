<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Tests\Acceptance;

use PHPUnit\Framework\Attributes\Test;

/**
 * @todo More test cases with symlink true|false
 */
class CommandTest extends TestBase
{
    #[Test]
    public function testGitHooksDeploy(): void
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doComposer(['-vvv', 'git-hooks:deploy']);
        $this->assertStdErrContains('Git hooks have been deployed by the core.hooksPath configuration.');
        $this->assertExitCodeEquals(0);
    }

    #[Test]
    public function testGitHooksRecall(): void
    {
        $this->doCreateProjectInstance('basic', 'p-01');
        $this->doComposer(['-vvv', 'git-hooks:recall']);
        $this->assertStdErrContains("cd '.' && git config --unset 'core.hooksPath'");
        $this->assertExitCodeEquals(0);
    }
}
