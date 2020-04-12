<?php

namespace Sweetchuck\GitHooks;

use DirectoryIterator;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class Deployer implements LoggerAwareInterface
{

    use LoggerAwareTrait;

    /**
     * @var int
     */
    const EXIT_CODE_NO_GIT = 1;

    /**
     * @var string
     */
    protected $projectRoot = '.';

    /**
     * @var string
     */
    protected $gitExecutable = 'git';

    /**
     * @var string
     */
    protected $gitVersion = '';

    /**
     * Self composer.json, not the root one.
     *
     * @var array|null
     */
    protected $selfPackage = null;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var array
     */
    protected $result = [];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

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
            ->doPre()
            ->doMain()
            ->doPost();

        return $this->result;
    }

    protected function init()
    {
        $this->result = [
            'exitCode' => 0,
        ];

        $this
            ->initSelfPackage()
            ->initGitVersion();

        return $this;
    }

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

    protected function initGitVersion()
    {
        $command = sprintf('%s --version', escapeshellcmd($this->gitExecutable));
        $output = null;
        $exit_code = null;
        exec($command, $output, $exit_code);
        if ($exit_code) {
            throw new Exception('Failed to detect the version of Git.', static::EXIT_CODE_NO_GIT);
        }

        // @todo Better regex.
        $matches = null;
        preg_match('/^git version (?P<version>.+)$/', trim(reset($output)), $matches);

        $this->gitVersion = $matches['version'] ?? null;

        return $this;
    }

    protected function doPre()
    {
        $this->logger->debug('BEGIN Git hooks deploy');

        return $this;
    }

    protected function doMain()
    {
        try {
            $gitDir = $this->getGitDir();
        } catch (Exception $e) {
            $this->logger->warning('Git hooks deployment skipped because of the absence of $GIT_DIR');

            return $this;
        }

        try {
            if ($this->coreHooksPathSupported()) {
                $this->doMainConfig();
            } elseif ($this->config['symlink']) {
                $this->doMainSymlink($gitDir);
            } else {
                $this->doMainCopy($gitDir);
            }

            return $this;
        } catch (Exception $e) {
            $this->result['exitCode'] = 1;
            $this->logger->error($e->getMessage());
        }

        return $this;
    }

    protected function doMainConfig()
    {
        $this->gitConfigSet('core.hooksPath', $this->config['core.hooksPath']);
        $this->logger->debug('Git hooks have been deployed by the core.hooksPath configuration.');

        return $this;
    }

    protected function doMainSymlink(string $gitDir)
    {
        $this->symlinkHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been symbolically linked.');

        return $this;
    }

    protected function doMainCopy(string $gitDir)
    {
        $this->copyHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
        $this->logger->debug('Git hooks have been deployed by coping the script files.');

        return $this;
    }

    protected function doPost()
    {
        $this->logger->debug('END   Git hooks deploy');

        return $this;
    }

    /**
     * Checks that the core.hooksPath configuration is supported by the current git executable.
     */
    protected function coreHooksPathSupported(): bool
    {
        return version_compare($this->gitVersion, '2.9', '>=');
    }

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
    }

    protected function symlinkHooksDir($srcDir, $dstDir)
    {
        $this->fs->remove($dstDir);
        $this->fs->symlink(realpath($srcDir), $dstDir, true);

        return;
    }

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
    }

    /**
     * @return bool|string
     */
    protected function getGitDir()
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

        return realpath($this->projectRoot . '/' . rtrim(reset($output), "\n"));
    }
}
