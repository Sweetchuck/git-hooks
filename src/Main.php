<?php

namespace Cheppers\GitHooks;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Deploy.
 *
 * @package Cheppers\GitHooks
 */
class Main
{

    /**
     * @var int
     */
    const EXIT_CODE_NO_GIT = 1;

    /**
     * @var Event
     */
    protected static $event = null;

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

    /**
     * @param Event $event
     *
     * @return bool
     */
    public static function deploy(Event $event)
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
        } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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
            ->write('END Git hooks deploy', true, $io_class::VERBOSE);

        return $is_success;
    }

    protected static function initDeploy()
    {
        static::initSelfPackage();
        static::initGitVersion();
    }

    /**
     * @throws \Exception
     */
    protected static function initGitVersion()
    {
        $command = sprintf('%s --version', escapeshellcmd(static::$gitExecutable));
        $output = null;
        $exit_code = null;
        exec($command, $output, $exit_code);
        if ($exit_code) {
            throw new \Exception('Failed to detect the version of Git.', static::EXIT_CODE_NO_GIT);
        }

        // @todo Better regex.
        $matches = null;
        preg_match('/^git version (?P<version>.+)$/', trim(reset($output)), $matches);

        static::$gitVersion = $matches ? $matches['version'] : null;
    }

    protected static function initSelfPackage()
    {
        static::$selfPackage = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
    }

    /**
     * @return bool
     */
    protected static function isSymlinkPrefered()
    {
        $args = static::$event->getArguments();
        for ($i = count($args) - 1; $i > -1; $i--) {
            if ($args[$i] === '--no-symlink') {
                return false;
            } elseif ($args[$i] === '--symlink') {
                return true;
            }
        }

        /** @var \Composer\Package\Package $package */
        $package = static::$event
            ->getComposer()
            ->getPackage();
        $extra = $package->getExtra();

        return !empty($extra[static::$selfPackage['name']]['symlink']);
    }

    /**
     * Checks that the core.hooksPath configuration is supported by the current git executable.
     *
     * @return bool
     */
    protected static function coreHooksPathSupported()
    {
        // @todo There is a strange thing with the pre-rebase hooks.
        return false;
        return version_compare(static::$gitVersion, '2.9', '>=');
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return null|string
     *
     * @throws \Exception
     */
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
            throw new \Exception("Failed to execute: '$command'", 1);
        }


        $io_class = get_class(static::$event->getIO());
        static::$event
            ->getIO()
            ->write($command, true, $io_class::VERBOSE);
    }

    /**
     * @param string $src_dir
     * @param string $dst_dir
     *
     * @throws \Exception
     */
    protected static function symlinkHooksDir($src_dir, $dst_dir)
    {
        $fs = new Filesystem();
        $fs->remove($dst_dir);
        $fs->symlink(realpath($src_dir), $dst_dir, true);

        return;
    }

    /**
     * @param string $src_dir
     * @param string $dst_dir
     *
     * @throws \Exception
     */
    protected static function copyHooksDir($src_dir, $dst_dir)
    {
        $fs = new Filesystem();
        $fs->mirror($src_dir, $dst_dir, null, ['override' => true]);
        $file = new \DirectoryIterator($src_dir);
        $mask = umask();
        while ($file->valid()) {
            if ($file->isFile() && is_executable($file->getPathname())) {
                $fs->chmod("$dst_dir/" . $file->getBasename(), 0777, $mask);
            }

            $file->next();
        }
    }

    /**
     * @return null|string
     *
     * @throws \Exception
     */
    protected static function getGitDir()
    {
        $command = sprintf(
            '%s rev-parse --git-dir',
            escapeshellcmd(static::$gitExecutable)
        );

        $output = null;
        $exit_code = null;
        exec($command, $output, $exit_code);
        if ($exit_code !== 0) {
            // @todo Error code.
            throw new \Exception('The $GIT_DIR cannot be detected', 3);
        }

        return realpath(rtrim(reset($output), "\n"));
    }

    /**
     * @return string
     */
    protected static function getCoreHooksPath()
    {
        foreach (static::$event->getArguments() as $arg) {
            if (strpos($arg, '--') !== 0) {
                return $arg;
            }
        }

        /** @var \Composer\Package\Package $root_package */
        $root_package = static::$event
            ->getComposer()
            ->getPackage();
        $extra = $root_package->getExtra();
        if (!empty($extra[static::$selfPackage['name']]['core.hooksPath'])) {
            return $extra[static::$selfPackage['name']]['core.hooksPath'];
        }

        if (is_dir(static::$defaultCoreHooksPath)) {
            return static::$defaultCoreHooksPath;
        }

        /** @var \Composer\Config $config */
        $config = static::$event
            ->getComposer()
            ->getConfig();

        $chg_path_abs = $config->get('vendor-dir') . '/' . static::$selfPackage['name'];
        $cwd = getcwd();
        $cgh_path_rel = preg_replace('@^' . preg_quote("$cwd/", '@') . '@', '', "$chg_path_abs/");
        $cgh_path_rel = rtrim($cgh_path_rel, '/');
        if (!$cgh_path_rel) {
            $cgh_path_rel = '.';
        }

        if (is_dir("$cgh_path_rel/" . static::$defaultCoreHooksPath)) {
            return "$cgh_path_rel/" . static::$defaultCoreHooksPath;
        }

        if ($root_package->getName() === static::$selfPackage['name']) {
            return static::$defaultCoreHooksPath;
        }

        return realpath(__DIR__ . '/..') . '/' . static::$defaultCoreHooksPath;
    }
}
