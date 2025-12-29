<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class GitHookManager implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    public const int EXIT_CODE_NO_GIT = 1;

    protected string $projectRoot = '.';

    protected string $gitExecutable = 'git';

    protected string $minGitVersionForCoreHookPaths = '2.9';

    protected string $gitVersion = '';

    /**
     * Self composer.json, not the root one.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $selfPackage = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @var array<string, mixed>
     */
    protected array $result = [];

    protected Filesystem $fs;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?Filesystem $fs = null,
        string $projectRoot = '.',
    ) {
        $this->logger = $logger;
        $this->fs = $fs ?: new Filesystem();
        $this->projectRoot = $projectRoot;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function deploy(array $config): array
    {
        $this->config = $config;

        $this
            ->init()
            ->doDeployPre()
            ->doDeployMain()
            ->doDeployPost();

        return $this->result;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function recall(array $config): array
    {
        $this->config = $config;

        $this
            ->init()
            ->doRecallPre()
            ->doRecallMain()
            ->doRecallPost();

        return $this->result;
    }

    protected function init(): static
    {
        $this->result = [
            'exitCode' => 0,
        ];

        $this
            ->initSelfPackage()
            ->initLogger()
            ->initGitVersion();

        return $this;
    }

    protected function initLogger(): static
    {
        if ($this->getLogger() === null) {
            $this->setLogger(new NullLogger());
        }

        return $this;
    }

    protected function initSelfPackage(): static
    {
        $this->selfPackage = json_decode(
            file_get_contents(__DIR__ . '/../composer.json') ?: '{}',
            true,
        );

        return $this;
    }

    protected function initGitVersion(): static
    {
        $command = sprintf('%s --version', escapeshellcmd($this->gitExecutable));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode) {
            throw new \Exception('Failed to detect the version of Git.', static::EXIT_CODE_NO_GIT);
        }

        // @todo Better regex.
        $matches = null;
        preg_match('/^git version (?P<version>.+)$/', trim((string) reset($output)), $matches);

        $this->gitVersion = $matches['version'] ?? '1.0.0';

        return $this;
    }

    protected function doDeployPre(): static
    {
        $this->logger->debug('BEGIN Git hooks deploy');

        return $this;
    }

    protected function doDeployMain(): static
    {
        try {
            $gitDir = $this->getGitDir();
        } catch (\Exception $exception) {
            $this->logger->warning(
                'Git hooks deployment skipped because of the absence of $GIT_DIR; {message}',
                [
                    'message' => $exception->getMessage(),
                ],
            );

            return $this;
        }

        try {
            if ($this->coreHooksPathSupported()) {
                $this->doDeployMainConfig();
            } elseif ($this->config['symlink']) {
                $this->doDeployMainSymlink($gitDir);
            } else {
                $this->doDeployMainCopy($gitDir);
            }
        } catch (\Exception $exception) {
            $this->result['exitCode'] = 1;
            $this->logger->error($exception->getMessage());
        }

        return $this;
    }

    protected function doDeployMainConfig(): static
    {
        $this->gitConfigSet('core.hooksPath', $this->config['core.hooksPath']);
        $this->logger->debug('Git hooks have been deployed by the core.hooksPath configuration.');

        return $this;
    }

    protected function doDeployMainSymlink(string $gitDir): static
    {
        $this->symlinkHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been symbolically linked.');

        return $this;
    }

    protected function doDeployMainCopy(string $gitDir): static
    {
        $this->copyHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been deployed by coping the script files.');

        return $this;
    }

    protected function doDeployPost(): static
    {
        $this->logger->debug('END   Git hooks deploy');

        return $this;
    }

    protected function doRecallPre(): static
    {
        $this->logger->debug('BEGIN Git hooks recall');

        return $this;
    }

    protected function doRecallMain(): static
    {
        try {
            $gitDir = $this->getGitDir();
        } catch (\Exception $exception) {
            $this->logger->warning(
                'Recall the deployed Git hooks scripts skipped because of the absence of $GIT_DIR - {message}',
                [
                    'message' => $exception->getMessage(),
                ],
            );

            return $this;
        }

        try {
            $currentCoreHooksPath = $this->gitConfigGet('core.hooksPath');
            if ($currentCoreHooksPath === $this->config['core.hooksPath']) {
                $this->gitConfigDelete('core.hooksPath');
            }
        } catch (\Exception) {
            //Nothing to do.
        }

        if ($this->fs->exists("$gitDir/hooks-original")) {
            $this->fs->remove("$gitDir/hooks");
            $this->fs->rename("$gitDir/hooks-original", "$gitDir/hooks");
        }

        return $this;
    }

    protected function doRecallPost(): static
    {
        $this->logger->debug('END   Git hooks recall');

        return $this;
    }

    /**
     * Checks that the core.hooksPath configuration is supported by the current git executable.
     */
    protected function coreHooksPathSupported(): bool
    {
        return version_compare(
            $this->gitVersion,
            $this->minGitVersionForCoreHookPaths,
            '>='
        );
    }

    protected function gitConfigGet(string $name): ?string
    {
        $command = sprintf(
            'cd %s && %s config %s',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable),
            escapeshellarg($name)
        );

        $this->logger->debug($command);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode === 1) {
            // The given config name $name doesn't exist.
            return null;
        }

        if ($exitCode !== 0) {
            $this->getLogger()->error(
                'Failed to execute: "{command}" {output}',
                [
                    'command' => $command,
                    'output' => implode(PHP_EOL, $output),
                ]
            );

            throw new \Exception("Failed to execute: '$command'", $exitCode);
        }

        return implode(PHP_EOL, $output);
    }

    protected function gitConfigSet(string $name, string $value): static
    {
        $command = sprintf(
            'cd %s && %s config %s %s',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable),
            escapeshellarg($name),
            escapeshellarg($value)
        );
        $output = null;
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Exit code.
            throw new \Exception("Failed to execute: '$command'", $exitCode);
        }

        $this->logger->debug($command);

        return $this;
    }

    protected function gitConfigDelete(string $name): static
    {
        $command = sprintf(
            'cd %s && %s config --unset %s ',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable),
            escapeshellarg($name)
        );
        $output = null;
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Exit code.
            throw new \Exception("Failed to execute: '$command'", $exitCode);
        }

        $this->logger->debug($command);

        return $this;
    }

    protected function symlinkHooksDir(string $srcDir, string $dstDir): static
    {
        if (is_link($dstDir)) {
            $this->fs->remove($dstDir);
        } else {
            $this->fs->rename($dstDir, "{$dstDir}-original");
        }

        $this->fs->symlink(
            Path::makeRelative($srcDir, $dstDir),
            $dstDir,
            true
        );

        return $this;
    }

    protected function copyHooksDir(string $srcDir, string $dstDir): static
    {
        $this->fs->mirror($srcDir, $dstDir, null, ['override' => true]);
        $file = new \DirectoryIterator($srcDir);
        $mask = umask();
        while ($file->valid()) {
            if ($file->isFile() && is_executable($file->getPathname())) {
                $this->fs->chmod("$dstDir/" . $file->getBasename(), 0777, $mask);
            }

            $file->next();
        }

        return $this;
    }

    protected function getGitDir(): ?string
    {
        $command = sprintf(
            'cd %s && %s rev-parse --git-dir',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable)
        );

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Error code.
            throw new \Exception('The $GIT_DIR cannot be detected', 3);
        }

        $gitDir = realpath($this->projectRoot . '/' . rtrim((string) reset($output), "\n"));

        return $gitDir !== false
            ? $gitDir
            : null;
    }
}
