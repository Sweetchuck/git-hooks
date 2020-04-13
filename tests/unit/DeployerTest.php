<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Unit;

use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use Sweetchuck\GitHooks\Deployer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @covers \Sweetchuck\GitHooks\Deployer
 */
class DeployerTest extends TestBase
{

    /**
     * @var \Sweetchuck\GitHooks\Test\UnitTester
     */
    protected $tester;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $projectRoot;

    protected function _before()
    {
        parent::_before();
        $this->fs = new FileSystem();
        $this->projectRoot = $this->createTempDir();
        exec(
            sprintf(
                'cd %s && git init',
                escapeshellarg($this->projectRoot)
            )
        );
    }

    protected function _after()
    {
        //$this->fs->remove($this->projectRoot);
        parent::_after();
    }

    public function casesDeploySuccess(): array
    {
        $selfRootDir = $this->selfProjectRoot();
        $defaultCoreHooksPath = "$selfRootDir/git-hooks";

        $logEntryBegin = [
            'level' => 'debug',
            'message' => 'BEGIN Git hooks deploy',
            'context' => [],
        ];

        $logEntryEnd= [
            'level' => 'debug',
            'message' => 'END   Git hooks deploy',
            'context' => [],
        ];

        $logEntryGitConfigCmd = [
            'level' => 'debug',
            'message' => "cd '{{ projectRoot }}' && git config 'core.hooksPath' '{{ selfProjectRoot }}/git-hooks'",
            'context' => [],
        ];

        $logEntryGitConfigSuccess = [
            'level' => 'debug',
            'message' => 'Git hooks have been deployed by the core.hooksPath configuration.',
            'context' => [],
        ];

        $logEntryGitSymlinkSuccess = [
            'level' => 'debug',
            'message' => 'Git hooks have been symbolically linked.',
            'context' => [],
        ];

        $logEntryCopySuccess = [
            'level' => 'debug',
            'message' => 'Git hooks have been deployed by coping the script files.',
            'context' => [],
        ];

        return [
            'core.hooksPath' => [
                [
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
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
            ],
            'symlink' => [
                [
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
                [
                    'symlink' => true,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                [
                    'coreHooksPathSupported' => false,
                ],
            ],
            'copy' => [
                [
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
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
                [
                    'coreHooksPathSupported' => false,
                ],
            ],
        ];
    }

    /**
     * @dataProvider casesDeploySuccess
     */
    public function testDeploySuccess(array $expected, array $config, array $mock = [])
    {
        $logger = new TestLogger();
        $result = $this
            ->createDeployer($logger, $mock)
            ->deploy($config);

        if (array_key_exists('result', $expected)) {
            $this->tester->assertSame($expected['result'], $result);
        }

        if (array_key_exists('logEntries', $expected)) {
            $this->tester->assertLogEntries(
                $expected['logEntries'],
                $logger->records,
                [
                    '{{ projectRoot }}' => $this->projectRoot,
                    '{{ selfProjectRoot }}' => $this->selfProjectRoot(),
                ]
            );
        }

        if (array_key_exists('deployType', $expected)) {
            switch ($expected['deployType']) {
                case 'core.hooksPath':
                    $this->assertGitHooksGitConfig($expected['core.hooksPath'], $this->projectRoot);
                    break;

                case 'symlink':
                    $this->tester->assertSymlink(
                        $expected['core.hooksPath'],
                        "{$this->projectRoot}/.git/hooks"
                    );
                    break;

                case 'copy':
                    $this->tester->assertDirContainsAllTheFiles(
                        $expected['core.hooksPath'],
                        "{$this->projectRoot}/.git/hooks"
                    );
                    break;
            }
        }
    }

    protected function assertGitHooksGitConfig(string $expected, string $projectRootDir)
    {
        $this->tester->assertFileExists("$projectRootDir/.git/config");
        $gitConfig = parse_ini_file("$projectRootDir/.git/config", true);
        $this->tester->assertArrayHasKey('core', $gitConfig);
        $this->tester->assertArrayHasKey('hooksPath', $gitConfig['core']);
        $this->tester->assertSame(
            $expected,
            $gitConfig['core']['hooksPath'],
            'git config core.hooksPath'
        );
    }

    protected function createDeployer(LoggerInterface $logger, array $mock): Deployer
    {
        $mock += [
            'getGitDir' => "{$this->projectRoot}/.git",
        ];

        /** @var \PHPUnit\Framework\MockObject\MockObject|\Sweetchuck\GitHooks\Deployer $deployer */
        $deployer = $this
            ->getMockBuilder(Deployer::class)
            ->setConstructorArgs([$logger, null, $this->projectRoot])
            ->onlyMethods(array_keys($mock))
            ->getMock();
        foreach ($mock as $mockMethod => $mockReturn) {
            $deployer
                ->expects($this->any())
                ->method($mockMethod)
                ->willReturn($mockReturn);
        }

        return $deployer;
    }
}
