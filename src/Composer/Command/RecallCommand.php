<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer\Command;

use Sweetchuck\GitHooks\ConfigReader;
use Sweetchuck\GitHooks\GitHookManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecallCommand extends BaseCommand
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
            $this->setName('git-hooks:recall');
        }

        $this->setDescription('Recall the deployed Git hooks scripts');
    }

    /**
     * {@inheritdoc}
     */
    protected function doIt()
    {
        $this->result = $this
            ->getGitHookManager()
            ->recall($this->getConfig());

        return $this;
    }
}
