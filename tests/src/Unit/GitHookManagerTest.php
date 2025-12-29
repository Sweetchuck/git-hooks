<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Sweetchuck\GitHooks\GitHookManager;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[CoversClass(GitHookManager::class)]
class GitHookManagerTest extends TestBase
{

    protected Filesystem $fs;

    protected string $projectRoot;

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new FileSystem();
        $this->projectRoot = $this->createTempDir();
        exec(
            sprintf(
                'cd %s && git init',
                escapeshellarg($this->projectRoot)
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->fs->remove($this->projectRoot);
        parent::tearDown();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function casesDeploySuccess(): array
    {
        $selfRootDir = static::selfProjectRoot();
        $defaultCoreHooksPath = "$selfRootDir/git-hooks";

        $logEntryBegin = [
            'debug',
            'BEGIN Git hooks deploy',
            [],
        ];

        $logEntryEnd= [
            'debug',
            'END   Git hooks deploy',
            [],
        ];

        $logEntryGitConfigCmd = [
            'debug',
            "cd '{{ projectRoot }}' && git config 'core.hooksPath' '{{ selfProjectRoot }}/git-hooks'",
            [],
        ];

        $logEntryGitConfigSuccess = [
            'debug',
            'Git hooks have been deployed by the core.hooksPath configuration.',
            [],
        ];

        $logEntryGitSymlinkSuccess = [
            'debug',
            'Git hooks have been symbolically linked.',
            [],
        ];

        $logEntryCopySuccess = [
            'debug',
            'Git hooks have been deployed by coping the script files.',
            [],
        ];

        return [
            'core.hooksPath' => [
                'expected' => [
                    'result' => [
                        'exitCode' => 0,
                    ],
                    'logEntries' => [
                        $logEntryBegin,
                        $logEntryGitConfigCmd,
                        $logEntryGitConfigSuccess,
                        $logEntryEnd,
                    ],
                    'deployType' => 'core.hooksPath',
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                'config' => [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
            ],
            'symlink' => [
                'expected' => [
                    'result' => [
                        'exitCode' => 0,
                    ],
                    'logEntries' => [
                        $logEntryBegin,
                        $logEntryGitSymlinkSuccess,
                        $logEntryEnd,
                    ],
                    'deployType' => 'symlink',
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                'config' => [
                    'symlink' => true,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                'mock' => [
                    'coreHooksPathSupported' => false,
                ],
            ],
            'copy' => [
                'expected' => [
                    'result' => [
                        'exitCode' => 0,
                    ],
                    'logEntries' => [
                        $logEntryBegin,
                        $logEntryCopySuccess,
                        $logEntryEnd,
                    ],
                    'deployType' => 'copy',
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                'config' => [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                'mock' => [
                    'coreHooksPathSupported' => false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $config
     * @param array<non-empty-string, mixed> $mock
     */
    #[Test]
    #[DataProvider('casesDeploySuccess')]
    public function testDeploySuccess(array $expected, array $config, array $mock = []): void
    {
        $logger = new BufferingLogger();
        $result = $this
            ->createDeployer($logger, $mock)
            ->deploy($config);

        if (array_key_exists('result', $expected)) {
            static::assertSame($expected['result'], $result);
        }

        if (array_key_exists('logEntries', $expected)) {
            static::assertLogEntries(
                $expected['logEntries'],
                $logger->cleanLogs(),
                [
                    '{{ projectRoot }}' => $this->projectRoot,
                    '{{ selfProjectRoot }}' => $this->selfProjectRoot(),
                ]
            );
        }

        if (array_key_exists('deployType', $expected)) {
            switch ($expected['deployType']) {
                case 'core.hooksPath':
                    static::assertGitHooksGitConfig($expected['core.hooksPath'], $this->projectRoot);
                    break;

                case 'symlink':
                    static::assertSymlink(
                        Path::makeRelative($expected['core.hooksPath'], "{$this->projectRoot}/.git/hooks"),
                        "{$this->projectRoot}/.git/hooks"
                    );
                    break;

                case 'copy':
                    static::assertDirContainsAllTheFiles(
                        $expected['core.hooksPath'],
                        "{$this->projectRoot}/.git/hooks"
                    );
                    break;
            }
        }
    }

    protected static function assertGitHooksGitConfig(string $expected, string $projectRootDir): void
    {
        static::assertFileExists("$projectRootDir/.git/config");
        $gitConfig = parse_ini_file("$projectRootDir/.git/config", true);
        // @phpstan-ignore-next-line
        static::assertArrayHasKey('core', $gitConfig);
        static::assertArrayHasKey('hooksPath', $gitConfig['core']);
        static::assertSame(
            $expected,
            $gitConfig['core']['hooksPath'],
            'git config core.hooksPath',
        );
    }

    /**
     * @param array<non-empty-string, mixed> $mock
     */
    protected function createDeployer(LoggerInterface $logger, array $mock): GitHookManager
    {
        $mock += [
            'getGitDir' => "{$this->projectRoot}/.git",
        ];

        /** @var \PHPUnit\Framework\MockObject\MockObject|\Sweetchuck\GitHooks\GitHookManager $deployer */
        $deployer = $this
            ->getMockBuilder(GitHookManager::class)
            ->setConstructorArgs([$logger, null, $this->projectRoot])
            ->onlyMethods(array_keys($mock))
            ->getMock();
        foreach ($mock as $mockMethod => $mockReturn) {
            $deployer
                ->expects(static::once())
                ->method($mockMethod)
                ->willReturn($mockReturn);
        }

        return $deployer;
    }
}
