<?php

namespace Sweetchuck\GitHooks;

use DirectoryIterator;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class Deployer
{

    /**
     * @var int
     */
    const EXIT_CODE_NO_GIT = 1;

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

    public function __construct(?LoggerInterface $logger = null, ?Filesystem $fs = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->fs = $fs ?: new Filesystem();
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
            $this->logger->warning('Git hooks haven\'t been deployed because of lack of $GIT_DIR');

            return $this;
        }

        try {
            if ($this->coreHooksPathSupported()) {
                $this->gitConfigSet('core.hooksPath', $this->config['core.hooksPath']);
                $this->logger->debug('Git hooks have been deployed by the core.hooksPath configuration.');

                return $this;
            }

            if ($this->config['symlink']) {
                $this->symlinkHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
                $this->logger->debug('Git hooks have been symbolically linked.');

                return $this;
            }

            $this->copyHooksDir($this->config['core.hooksPath'], "$gitDir/hooks");
            $this->logger->debug('Git hooks have been deployed by coping the script files.');
        } catch (Exception $e) {
            $this->result['exitCode'] = 1;
            $this->logger->error($e->getMessage());
        }

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

    protected function gitConfigSet($name, $value)
    {
        $command = sprintf(
            '%s config %s %s',
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
            '%s rev-parse --git-dir',
            escapeshellcmd($this->gitExecutable)
        );

        $output = null;
        $exitCode = null;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            // @todo Error code.
            throw new Exception('The $GIT_DIR cannot be detected', 3);
        }

        return realpath(rtrim(reset($output), "\n"));
    }
}
