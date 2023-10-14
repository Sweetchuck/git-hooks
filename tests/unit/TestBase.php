<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Unit;

use Codeception\Test\Unit;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class TestBase extends Unit
{

    /**
     * @var \Sweetchuck\GitHooks\Tests\UnitTester
     */
    protected $tester;

    protected function selfProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function getInput(array $values): InputInterface
    {
        $values += ['command' => 'foo'];

        $definition = new InputDefinition();
        $definition->addArgument(new InputArgument(
            'command',
            InputArgument::REQUIRED
        ));
        $definition->addOption(new InputOption(
            'symlink',
            's',
            InputOption::VALUE_NONE
        ));
        $definition->addOption(new InputOption(
            'no-symlink',
            'S',
            InputOption::VALUE_NONE
        ));
        $definition->addOption(new InputOption(
            'core-hooks-path',
            'p',
            InputOption::VALUE_REQUIRED
        ));

        return new ArrayInput($values, $definition);
    }

    protected function createTempDir(): string
    {
        $dir = $this->randomTempDirName();
        mkdir($dir, 0777 - umask(), true);

        return $dir;
    }

    protected function randomTempDirName(): string
    {
        return implode('/', [
            sys_get_temp_dir(),
            'sweetchuck',
            'git-hooks',
            'test-' . $this->randomId(),
        ]);
    }

    protected function randomId(): string
    {
        return md5((string) (microtime(true) * rand(0, 10000)));
    }
}
