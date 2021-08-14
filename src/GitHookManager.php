<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks;

use DirectoryIterator;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class GitHookManager implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    /**
     * @var int
     */
    const EXIT_CODE_NO_GIT = 1;

    /**
     * @var string
     */
    protected string $projectRoot = '.';

    /**
     * @var string
     */
    protected string $gitExecutable = 'git';

    /**
     * @var string
     */
    protected string $minGitVersionForCoreHookPaths = '2.9';

    /**
     * @var string
     */
    protected string $gitVersion = '';

    /**
     * Self composer.json, not the root one.
     */
    protected ?array $selfPackage = null;

    protected array $config = [];

    protected array $result = [];

    protected Filesystem $fs;

    public function __construct(?LoggerInterface $logger = null, ?Filesystem $fs = null, string $projectRoot = '.')
    {
        $this->logger = $logger;
        $this->fs = $fs ?: new Filesystem();
        $this->projectRoot = $projectRoot;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

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

    /**
     * @return $this
     */
    protected function init()
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

    /**
     * @return $this
     */
    protected function initLogger()
    {
        if ($this->getLogger() === null) {
            $this->setLogger(new NullLogger());
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function initSelfPackage()
    {
        $this->selfPackage = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        return $this;
    }

    /**
     * @return $this
     */
    protected function initGitVersion()
    {
        $command = sprintf('%s --version', escapeshellcmd($this->gitExecutable));
        $output = null;
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode) {
            throw new Exception('Failed to detect the version of Git.', static::EXIT_CODE_NO_GIT);
        }

        // @todo Better regex.
        $matches = null;
        preg_match('/^git version (?P<version>.+)$/', trim(reset($output)), $matches);

        $this->gitVersion = $matches['version'] ?? null;

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployPre()
    {
        $this->logger->debug('BEGIN Git hooks deploy');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployMain()
    {
        try {
            $gitDir = $this->getGitDir();
        } catch (Exception $e) {
            // @todo Add exception message to the log entry.
            $this->logger->warning('Git hooks deployment skipped because of the absence of $GIT_DIR');

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
        } catch (Exception $e) {
            $this->result['exitCode'] = 1;
            $this->logger->error($e->getMessage());
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployMainConfig()
    {
        $this->gitConfigSet('core.hooksPath', $this->config['core.hooksPath']);
        $this->logger->debug('Git hooks have been deployed by the core.hooksPath configuration.');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployMainSymlink(string $gitDir)
    {
        $this->symlinkHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been symbolically linked.');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployMainCopy(string $gitDir)
    {
        $this->copyHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been deployed by coping the script files.');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doDeployPost()
    {
        $this->logger->debug('END   Git hooks deploy');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doRecallPre()
    {
        $this->logger->debug('BEGIN Git hooks recall');

        return $this;
    }

    /**
     * @return $this
     */
    protected function doRecallMain()
    {
        try {
            $gitDir = $this->getGitDir();
        } catch (Exception $e) {
            $this->logger->warning(
                'Recall the deployed Git hooks scripts skipped because of the absence of $GIT_DIR - {message}',
                [
                    'message' => $e->getMessage(),
                ]
            );

            return $this;
        }

        try {
            $currentCoreHooksPath = $this->gitConfigGet('core.hooksPath');
            if ($currentCoreHooksPath === $this->config['core.hooksPath']) {
                $this->gitConfigDelete('core.hooksPath');
            }
        } catch (\Exception $e) {
            //Nothing to do.
        }

        if ($this->fs->exists("$gitDir/hooks-original")) {
            $this->fs->remove("$gitDir/hooks");
            $this->fs->rename("$gitDir/hooks-original", "$gitDir/hooks");
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function doRecallPost()
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
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode === 1) {
            // The given config name $name not exists.
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

            throw new Exception("Failed to execute: '$command'", $exitCode);
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * @return $this
     */
    protected function gitConfigSet(string $name, string $value)
    {
        $command = sprintf(
            'cd %s && %s config %s %s',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable),
            escapeshellarg($name),
            escapeshellarg($value)
        );
        $output = null;
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Exit code.
            throw new Exception("Failed to execute: '$command'", $exitCode);
        }

        $this->logger->debug($command);

        return $this;
    }

    /**
     * @return $this
     */
    protected function gitConfigDelete(string $name)
    {
        $command = sprintf(
            'cd %s && %s config --unset %s ',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable),
            escapeshellarg($name)
        );
        $output = null;
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Exit code.
            throw new Exception("Failed to execute: '$command'", $exitCode);
        }

        $this->logger->debug($command);

        return $this;
    }

    /**
     * @return $this
     */
    protected function symlinkHooksDir($srcDir, $dstDir)
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

    /**
     * @return $this
     */
    protected function copyHooksDir($srcDir, $dstDir)
    {
        $this->fs->mirror($srcDir, $dstDir, null, ['override' => true]);
        $file = new DirectoryIterator($srcDir);
        $mask = umask();
        while ($file->valid()) {
            if ($file->isFile() && is_executable($file->getPathname())) {
                $this->fs->chmod("$dstDir/" . $file->getBasename(), 0777, $mask);
            }

            $file->next();
        }

        return $this;
    }

    /**
     * @return bool|string
     */
    protected function getGitDir(): ?string
    {
        $command = sprintf(
            'cd %s && %s rev-parse --git-dir',
            escapeshellarg($this->projectRoot),
            escapeshellcmd($this->gitExecutable)
        );

        $output = null;
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Error code.
            throw new Exception('The $GIT_DIR cannot be detected', 3);
        }

        $gitDir = realpath($this->projectRoot . '/' . rtrim(reset($output), "\n"));

        return $gitDir !== false ? $gitDir : null;
    }
}
