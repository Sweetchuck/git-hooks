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

    protected ConfigReader $configReader;

    protected InputInterface $input;

    /**
     * @var array<string, mixed>
     */
    protected array $result = [];

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function setInput(InputInterface $input): static
    {
        $this->input = $input;

        return $this;
    }

    protected OutputInterface $output;

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function setOutput(OutputInterface $output): static
    {
        $this->output = $output;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(?string $name = null, ?ConfigReader $configReader = null)
    {
        $this->configReader = $configReader ?: new ConfigReader();
        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this
            ->setInput($input)
            ->setOutput($output)
            ->doIt();

        return $this->result['exitCode'];
    }

    abstract protected function doIt(): static;

    protected function getGitHookManager(): GitHookManager
    {
        return new GitHookManager($this->getIO());
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getConfig(): array
    {
        $namespace = $this->getSelfName();
        $composer = $this->tryComposer();
        $extra = $composer
            ? $composer->getPackage()->getExtra()
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
