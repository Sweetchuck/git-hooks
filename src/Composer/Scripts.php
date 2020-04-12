<?php

namespace Sweetchuck\GitHooks\Composer;

use Composer\Script\Event;
use DirectoryIterator;
use Exception;
use Symfony\Component\Filesystem\Filesystem;

class Scripts
{

    /**
     * @var int
     */
    const EXIT_CODE_NO_GIT = 1;

    /**
     * @var \Composer\Script\Event
     */
    protected static $event;

    /**
     * @var string
     */
    protected static $gitExecutable = 'git';

    /**
     * @var string
     */
    protected static $gitVersion = '';

    /**
     * @var string
     */
    protected static $defaultCoreHooksPath = 'git-hooks';

    /**
     * Self composer.json, not the root one.
     *
     * @var array|null
     */
    protected static $selfPackage = null;

    public static function postInstallCmd(Event $event): bool
    {
        return static::deploy($event);
    }

    public static function deploy(Event $event): bool
    {
        static::$event = $event;
        $io_class = get_class(static::$event->getIO());
        static::$event
            ->getIO()
            ->write('BEGIN Git hooks deploy', true, $io_class::VERBOSE);

        static::initDeploy();

        $is_success = true;
        try {
            $git_dir = static::getGitDir();
        } catch (Exception $e) {
            $git_dir = false;
            static::$event
                ->getIO()
                ->write('Git hooks haven\'t been deployed because of lack of $GIT_DIR', true);
        }

        if ($git_dir) {
            $message = '';
            try {
                $core_hooks_path = static::getCoreHooksPath();
                if (static::coreHooksPathSupported()) {
                    static::gitConfigSet('core.hooksPath', $core_hooks_path);
                    $message = 'Git hooks have been deployed by the core.hooksPath configuration.';
                } elseif (static::isSymlinkPrefered()) {
                    static::symlinkHooksDir($core_hooks_path, static::getGitDir() . '/hooks');
                    $message = 'Git hooks have been symbolically linked.';
                } else {
                    static::copyHooksDir($core_hooks_path, static::getGitDir() . '/hooks');
                    $message = 'Git hooks have been deployed by coping the script files.';
                }
            } catch (Exception $e) {
                $is_success = false;
                static::$event
                    ->getIO()
                    ->writeError($e->getMessage(), true);
            }

            static::$event
                ->getIO()
                ->write($message, true);
        }

        static::$event
            ->getIO()
            ->write('END   Git hooks deploy', true, $io_class::VERBOSE);

        return $is_success;
    }

    protected static function initDeploy()
    {
        static::initSelfPackage();
        static::initGitVersion();
    }

    protected static function initGitVersion()
    {
        $command = sprintf('%s --version', escapeshellcmd(static::$gitExecutable));
        $output = null;
        $exit_code = null;
        exec($command, $output, $exit_code);
        if ($exit_code) {
            throw new Exception('Failed to detect the version of Git.', static::EXIT_CODE_NO_GIT);
        }

        // @todo Better regex.
        $matches = null;
        preg_match('/^git version (?P<version>.+)$/', trim(reset($output)), $matches);

        static::$gitVersion = $matches ? $matches['version'] : null;
    }

    protected static function initSelfPackage()
    {
        static::$selfPackage = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);
    }

    protected static function isSymlinkPrefered(): bool
    {
        $args = static::$event->getArguments();
        $isSymlink = null;
        for ($i = count($args) - 1; $i > -1; $i--) {
            if ($args[$i] === '--no-symlink') {
                $isSymlink = false;
            } elseif ($args[$i] === '--symlink') {
                $isSymlink = true;
            }
        }

        if ($isSymlink !== null) {
            return $isSymlink;
        }

        $extra = static::$event
            ->getComposer()
            ->getPackage()
            ->getExtra();

        return !empty($extra[static::$selfPackage['name']]['symlink']);
    }

    /**
     * Checks that the core.hooksPath configuration is supported by the current git executable.
     */
    protected static function coreHooksPathSupported(): bool
    {
        return version_compare(static::$gitVersion, '2.9', '>=');
    }

    protected static function gitConfigSet($name, $value)
    {
        $command = sprintf(
            '%s config %s %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($name),
            escapeshellarg($value)
        );
        $output = null;
        $exit_code = null;
        exec($command, $output, $exit_code);
        if ($exit_code !== 0) {
            // @todo Exit code.
            throw new Exception("Failed to execute: '$command'", 1);
        }


        $io_class = get_class(static::$event->getIO());
        static::$event
            ->getIO()
            ->write($command, true, $io_class::VERBOSE);
    }

    protected static function symlinkHooksDir($srcDir, $dstDir)
    {
        $fs = new Filesystem();
        $fs->remove($dstDir);
        $fs->symlink(realpath($srcDir), $dstDir, true);

        return;
    }

    protected static function copyHooksDir($srcDir, $dstDir)
    {
        $fs = new Filesystem();
        $fs->mirror($srcDir, $dstDir, null, ['override' => true]);
        $file = new DirectoryIterator($srcDir);
        $mask = umask();
        while ($file->valid()) {
            if ($file->isFile() && is_executable($file->getPathname())) {
                $fs->chmod("$dstDir/" . $file->getBasename(), 0777, $mask);
            }

            $file->next();
        }
    }

    /**
     * @return bool|string
     */
    protected static function getGitDir()
    {
        $command = sprintf(
            '%s rev-parse --git-dir',
            escapeshellcmd(static::$gitExecutable)
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

    protected static function getCoreHooksPath(): string
    {
        foreach (static::$event->getArguments() as $arg) {
            if (strpos($arg, '--') !== 0) {
                return $arg;
            }
        }

        $rootPackage = static::$event
            ->getComposer()
            ->getPackage();

        $extra = $rootPackage->getExtra();
        if (!empty($extra[static::$selfPackage['name']]['core.hooksPath'])) {
            return $extra[static::$selfPackage['name']]['core.hooksPath'];
        }

        if (is_dir(static::$defaultCoreHooksPath)) {
            return static::$defaultCoreHooksPath;
        }

        $config = static::$event
            ->getComposer()
            ->getConfig();

        $chg_path_abs = $config->get('vendor-dir') . '/' . static::$selfPackage['name'];
        $cwd = getcwd();
        $sghPathRel = preg_replace('@^' . preg_quote("$cwd/", '@') . '@', '', "$chg_path_abs/");
        $sghPathRel = rtrim($sghPathRel, '/');
        if (!$sghPathRel) {
            $sghPathRel = '.';
        }

        if (is_dir("$sghPathRel/" . static::$defaultCoreHooksPath)) {
            return "$sghPathRel/" . static::$defaultCoreHooksPath;
        }

        if ($rootPackage->getName() === static::$selfPackage['name']) {
            return static::$defaultCoreHooksPath;
        }

        return realpath(__DIR__ . '/..') . '/' . static::$defaultCoreHooksPath;
    }
}
