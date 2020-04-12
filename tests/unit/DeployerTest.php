<?php

declare(strict_types = 1);

namespace  Sweetchuck\GitHooks\Tests\Unit;

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
        $this->fs->remove($this->projectRoot);
        parent::_after();
    }

    public function casesDeploy(): array
    {
        $selfRootDir = $this->selfProjectRoot();
        $defaultCoreHooksPath = "$selfRootDir/git-hooks";

        return [
            'basic' => [
                [
                    'result' => [
                        'exitCode' => 0,
                    ],
                ],
                [
                    'symlink' => false,
                    'core.hooksPath' => $defaultCoreHooksPath,
                ],
            ],
        ];
    }

    /**
     * @dataProvider casesDeploy
     */
    public function testDeploy(array $expected, array $config)
    {
        $logger = new TestLogger();

        /** @var \PHPUnit\Framework\MockObject\MockObject|\Sweetchuck\GitHooks\Deployer $deployer */
        $deployer = $this
            ->getMockBuilder(Deployer::class)
            ->setConstructorArgs([$logger, null, $this->projectRoot])
            ->onlyMethods(['getGitDir'])
            ->getMock();
        $deployer
            ->expects($this->any())
            ->method('getGitDir')
            ->willReturn($this->projectRoot . '/.git');

        $result = $deployer->deploy($config);
        $this->tester->assertSame($expected['result'], $result);
    }
}
