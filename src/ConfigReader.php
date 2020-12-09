<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks;

use Symfony\Component\Console\Input\InputInterface;

class ConfigReader
{

    /**
     * @var string
     */
    protected $envVarNamePrefix = 'SGH_GIT_HOOKS';

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var array
     */
    protected $extra = [];

    /**
     * @var string
     */
    protected $defaultShell = 'bash';

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

    protected function getConfigResolvePlaceholders(array &$config)
    {
        $coreHooksPath = $config['core.hooksPath'] ?: '';
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

    protected function getConfigFromExtra(): array
    {
        return $this->extra;
    }

    protected function getConfigFromDefault(): array
    {
        $cwd = $this->getCwd();
        $root = $this->getSelfProjectRootDir();
        if (mb_strpos($root, "$cwd/") === 0) {
            $root = './' . mb_substr($root, mb_strlen($cwd) + 1);
        }

        return [
            'symlink' => false,
            'core.hooksPath' => "$root/git-hooks/{{ SHELL }}",
            'SHELL' => basename(getenv('SHELL')) ?: $this->defaultShell,
        ];
    }

    protected function getCwd(): string
    {
        return getcwd();
    }

    protected function getSelfProjectRootDir(): string
    {
        return dirname(__DIR__);
    }
}
