<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks;

use Symfony\Component\Console\Input\InputInterface;

class ConfigReader
{

    protected string $envVarNamePrefix = 'SGH_GIT_HOOKS';

    protected ?InputInterface $input;

    /**
     * @var array<string, mixed>
     */
    protected array $extra = [];

    protected string $defaultShell = 'bash';

    /**
     * @param null|\Symfony\Component\Console\Input\InputInterface $input
     * @param array<string, mixed> $extra
     *
     * @return array<string, string|bool>
     */
    public function getConfig(?InputInterface $input = null, array $extra = []): array
    {
        $this->input = $input;
        $this->extra = $extra;

        $config = array_replace_recursive(
            $this->getConfigFromDefault(),
            $this->getConfigFromExtra(),
            $this->getConfigFromEnvVars(),
            $this->getConfigFromCli()
        );
        $this->getConfigResolvePlaceholders($config);

        return $config;
    }

    /**
     * @param array<string, string|bool> $config
     */
    protected function getConfigResolvePlaceholders(array &$config): static
    {
        $coreHooksPath = (string) ($config['core.hooksPath'] ?: '');
        foreach ([$config['SHELL'], $this->defaultShell] as $shell) {
            $replacementPairs = [
                '{{ SHELL }}' => $shell,
            ];
            $config['core.hooksPath'] = strtr($coreHooksPath, $replacementPairs);
            if (file_exists($config['core.hooksPath'])) {
                break;
            }

            $config['core.hooksPath'] = $coreHooksPath;
        }

        return $this;
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getConfigFromCli(): array
    {
        if (!$this->input) {
            return [];
        }

        $config = [];

        if ($this->input->hasOption('symlink')
            && $this->input->getOption('symlink') === true
        ) {
            $config['symlink'] = true;
        }

        if ($this->input->hasOption('no-symlink')
            && $this->input->getOption('no-symlink') === true
        ) {
            $config['symlink'] = false;
        }

        if ($this->input->hasOption('core-hooks-path')) {
            $coreHooksPath = $this->input->getOption('core-hooks-path');
            if ($coreHooksPath) {
                $config['core.hooksPath'] = $coreHooksPath;
            }
        }

        return $config;
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getConfigFromEnvVars(): array
    {
        $config = [];

        $names = [
            'symlink' => 'SYMLINK',
            'core.hooksPath' => 'CORE_HOOKS_PATH',
        ];

        foreach ($names as $key => $envVar) {
            $value = getenv("{$this->envVarNamePrefix}_{$envVar}");
            if ($value === false) {
                continue;
            }

            switch ($key) {
                case 'symlink':
                    $config[$key] = $value === 'true';
                    break;

                default:
                    $config[$key] = $value;
                    break;
            }
        }

        return $config;
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getConfigFromExtra(): array
    {
        return $this->extra;
    }

    /**
     * @return array<string, string|bool>
     */
    protected function getConfigFromDefault(): array
    {
        $cwd = $this->getCwd();
        $root = $this->getSelfProjectRootDir();
        if (mb_strpos($root, "$cwd/") === 0) {
            $root = './' . mb_substr($root, mb_strlen($cwd) + 1);
        }

        $shell = getenv('SHELL');

        return [
            'symlink' => false,
            'core.hooksPath' => "$root/git-hooks/{{ SHELL }}",
            'SHELL' => $shell ? basename($shell) : $this->defaultShell,
        ];
    }

    protected function getCwd(): string
    {
        return (string) getcwd();
    }

    protected function getSelfProjectRootDir(): string
    {
        return dirname(__DIR__);
    }
}
