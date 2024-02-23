<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer\Command;

use Sweetchuck\GitHooks\ConfigReader;
use Sweetchuck\GitHooks\GitHookManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends BaseCommand
{

    /**
     * @var array
     */
    protected $result = [];

    /**
     * {@inheritDoc}
     */
    protected function configure()
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

    /**
     * {@inheritdoc}
     */
    protected function doIt()
    {
        $this->result = $this
            ->getGitHookManager()
            ->deploy($this->getConfig());

        return $this;
    }
}
