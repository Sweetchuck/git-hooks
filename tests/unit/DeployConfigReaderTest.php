<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Unit;

use Sweetchuck\GitHooks\DeployConfigReader;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @covers \Sweetchuck\GitHooks\DeployConfigReader
 */
class DeployConfigReaderTest extends TestBase
{
    public function casesGetConfig(): array
    {
        $selfRootDir = $this->selfProjectRoot();
        $defaultCoreHooksPath = "$selfRootDir/git-hooks";

        InputInterface::class;

        return [
            'basic' => [
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                null,
                [],
            ],
            '--symlink' => [
                [
                    'symlink' => true,
                    'core.hooksPath' => $defaultCoreHooksPath,
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
        $subject = new DeployConfigReader();
        $this->tester->assertSame($expected, $subject->getConfig($input, $extra));
    }
}
