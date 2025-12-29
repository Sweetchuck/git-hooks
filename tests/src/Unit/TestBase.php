<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class TestBase extends TestCase
{

    protected static function selfProjectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string, null|string|array<string>> $values
     */
    protected static function getInput(array $values): InputInterface
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

    protected static function createTempDir(): string
    {
        $dir = static::randomTempDirName();
        mkdir($dir, 0777 - umask(), true);

        return $dir;
    }

    protected static function randomTempDirName(): string
    {
        return implode('/', [
            sys_get_temp_dir(),
            'sweetchuck',
            'git-hooks',
            'test-' . static::randomId(),
        ]);
    }

    protected static function randomId(): string
    {
        return md5((string) (microtime(true) * rand(0, 10000)));
    }

    public static function assertSymlink(string $expected, string $file): void
    {
        static::assertFileExists($file);

        static::assertSame(
            'link',
            filetype($file),
            "$file is a symbolic link"
        );

        static::assertSame(
            $expected,
            readlink($file),
            "symbolic link $file points to $expected"
        );
    }

    public static function assertDirContainsAllTheFiles(string $expected, string $actual): void
    {
        $file = new \DirectoryIterator($expected);
        while ($file->valid()) {
            if ($file->isDot() || $file->isDir()) {
                $file->next();
                continue;
            }

            $actualFile = "$actual/" . $file->getFilename();
            static::assertFileExists($actualFile);
            static::assertSame(
                $file->getPerms(),
                fileperms($actualFile),
                "file permissions of $actualFile"
            );

            static::assertSame(
                md5_file($file->getPathname()),
                md5_file($actualFile),
                "content of $actualFile file"
            );

            $file->next();
        }
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     * @param array<string, string> $replacementPairs
     */
    public static function assertLogEntries(
        array $expected,
        array $actual,
        array $replacementPairs = [],
        string $message = '',
    ): void {
        if ($message === '') {
            $message = 'log entries';
        }

        static::assertCount(
            count($expected),
            $actual,
            "$message - number of log entries"
        );

        foreach ($expected as $i => $expectedLogEntry) {
            $expectedLogEntry[1] = strtr($expectedLogEntry[1], $replacementPairs);
            static::assertSame(
                $expectedLogEntry,
                $actual[$i],
                "$message - log entry $i"
            );
        }
    }
}
