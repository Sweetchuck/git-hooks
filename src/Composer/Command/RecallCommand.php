<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer\Command;

class RecallCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        if (!$this->getName()) {
            $this->setName('git-hooks:recall');
        }

        $this->setDescription('Recall the deployed Git hooks scripts');
    }

    protected function doIt(): static
    {
        $this->result = $this
            ->getGitHookManager()
            ->recall($this->getConfig());

        return $this;
    }
}
