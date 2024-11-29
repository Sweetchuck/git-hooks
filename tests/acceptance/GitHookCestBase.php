<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Acceptance;

class GitHookCestBase
{
    protected string $defaultGitBranch = '1.x';

    protected string $gitVersion = '';

    protected function getGitVersion(): string
    {
        if (empty($this->gitVersion)) {
            $this->gitVersion = preg_replace(
                '/^git version /',
                '',
                trim(shell_exec('git --version')),
            );
        }

        return $this->gitVersion;
    }
}
