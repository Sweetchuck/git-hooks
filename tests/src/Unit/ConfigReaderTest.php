<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\GitHooks\ConfigReader;
use Symfony\Component\Console\Input\InputInterface;

#[CoversClass(ConfigReader::class)]
class ConfigReaderTest extends TestBase
{
    /**
     * @return array<string, mixed>
     */
    public static function casesGetConfig(): array
    {
        $selfRootDir = static::selfProjectRoot();
        $shell = basename(getenv('SHELL') ?: '/bin/bash');
        $defaultCoreHooksPath = "$selfRootDir/git-hooks/$shell";

        return [
            'basic' => [
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                    'SHELL' => $shell,
                ],
                null,
                [],
            ],
            '--symlink' => [
                [
                    'symlink' => true,
                    'core.hooksPath' => $defaultCoreHooksPath,
                    'SHELL' => $shell,
                ],
                static::getInput([
                    '--symlink' => null,
                ]),
                [],
            ],
            '--no-symlink' => [
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                    'SHELL' => $shell,
                ],
                static::getInput([
                    '--no-symlink' => null,
                ]),
                [],
            ],
            '--core-hooks-path' => [
                [
                    'symlink' => false,
                    'core.hooksPath' => 'my-dir',
                    'SHELL' => $shell,
                ],
                static::getInput([
                    '--core-hooks-path' => 'my-dir',
                ]),
                [],
            ],
            '--symlink --core-hooks-path' => [
                [
                    'symlink' => true,
                    'core.hooksPath' => 'my-dir',
                    'SHELL' => $shell,
                ],
                static::getInput([
                    '--symlink' => null,
                    '--core-hooks-path' => 'my-dir',
                ]),
                [],
            ],
            'extra:symlink:true' => [
                [
                    'symlink' => true,
                    'core.hooksPath' => $defaultCoreHooksPath,
                    'SHELL' => $shell,
                ],
                null,
                [
                    'symlink' => true,
                ],
            ],
            // @todo More cases with envVars.
        ];
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $extra
     * */
    #[Test]
    #[DataProvider('casesGetConfig')]
    public function testGetConfig(array $expected, ?InputInterface $input, array $extra): void
    {
        $subject = new ConfigReader();
        static::assertSame($expected, $subject->getConfig($input, $extra));
    }
}
