<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer\Command;

use Symfony\Component\Console\Input\InputOption;

class DeployCommand extends BaseCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        if (!$this->getName()) {
            $this->setName('git-hooks:deploy');
        }

        $this
            ->setDescription('Deploys Git hooks scripts based on the configuration')
            ->addOption(
                'symlink',
                's',
                InputOption::VALUE_NONE,
                'Symlink or copy'
            )
            ->addOption(
                'no-symlink',
                'S',
                InputOption::VALUE_NONE,
                'Symlink or copy'
            )
            ->addOption(
                'core-hooks-path',
                'p',
                InputOption::VALUE_REQUIRED,
                'Value for core.hooksPath Git config'
            );
    }

    protected function doIt(): static
    {
        $this->result = $this
            ->getGitHookManager()
            ->deploy($this->getConfig());

        return $this;
    }
}
