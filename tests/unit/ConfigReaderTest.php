<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Unit;

use Sweetchuck\GitHooks\ConfigReader;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @covers \Sweetchuck\GitHooks\ConfigReader
 */
class ConfigReaderTest extends TestBase
{
    public function casesGetConfig(): array
    {
        $selfRootDir = $this->selfProjectRoot();
        $shell = basename(getenv('SHELL'));
        $defaultCoreHooksPath = "$selfRootDir/git-hooks/$shell";

        InputInterface::class;

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
                $this->getInput([
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
                $this->getInput([
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
                $this->getInput([
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
                $this->getInput([
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
     * @dataProvider casesGetConfig
     */
    public function testGetConfig(array $expected, ?InputInterface $input, array $extra)
    {
        $subject = new ConfigReader();
        $this->tester->assertSame($expected, $subject->getConfig($input, $extra));
    }
}
