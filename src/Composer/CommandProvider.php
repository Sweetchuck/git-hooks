<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer;

use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Sweetchuck\GitHooks\Composer\Command\DeployCommand;
use Sweetchuck\GitHooks\Composer\Command\RecallCommand;

class CommandProvider implements ComposerCommandProvider
{
    /**
     * {@inheritdoc}
     */
    public function getCommands(): array
    {
        return [
            new DeployCommand(),
            new RecallCommand(),
        ];
    }
}
