<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer;

use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Sweetchuck\GitHooks\Composer\Command\DeployCommand;

class CommandProvider implements ComposerCommandProvider
{
    /**
     * @inheritDoc
     */
    public function getCommands()
    {
        return [
            new DeployCommand(),
        ];
    }
}
