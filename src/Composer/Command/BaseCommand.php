<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer\Command;

use Composer\Command\BaseCommand as UpstreamBaseCommand;
use Sweetchuck\GitHooks\ConfigReader;
use Sweetchuck\GitHooks\GitHookManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends UpstreamBaseCommand
{

    /**
     * @var \Sweetchuck\GitHooks\ConfigReader
     */
    protected $configReader;

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    /**
     * @return $this
     */
    public function setInput(InputInterface $input)
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * @return $this
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function __construct(string $name = null, ?ConfigReader $configReader = null)
    {
        $this->configReader = $configReader ?: new ConfigReader();
        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this
            ->setInput($input)
            ->setOutput($output)
            ->doIt();

        return $this->result['exitCode'];
    }

    /**
     * @return $this
     */
    abstract protected function doIt();

    protected function getGitHookManager(): GitHookManager
    {
        return new GitHookManager($this->getIO());
    }

    protected function getConfig(): array
    {
        $namespace = $this->getSelfName();
        $composer = $this->getComposer();
        $extra = $composer ?
            $composer->getPackage()->getExtra()
            : [];

        return $this
            ->configReader
            ->getConfig(
                $this->getInput(),
                $extra[$namespace] ?? []
            );
    }

    protected function getSelfName(): string
    {
        return 'sweetchuck/git-hooks';
    }
}
