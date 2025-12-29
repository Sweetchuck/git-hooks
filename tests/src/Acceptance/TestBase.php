<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Tests\Acceptance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TestBase extends TestCase
{

    // region Suite.
    protected static Filesystem $fs;

    protected static string $gitExecutable = 'git';

    protected static string $composerExecutable = 'composer';

    /**
     * Path to this (sweetchuck/git-hooks) package.
     */
    protected static string $projectRootDir = '.';

    /**
     * Data from "::$projectRootDir/composer.json".
     *
     * @var array<string, mixed>
     */
    protected static array $composer = [];

    /**
     * Absolute path to the "fixtures" directory.
     */
    protected static string $fixturesDir = 'tests/fixtures';

    protected static string $suitRootDir;

    protected static string $defaultGitBranch = '1.x';

    protected static ?string $gitVersion = null;

    protected static function getGitVersion(): string
    {
        if (static::$gitVersion === null) {
            static::$gitVersion = preg_replace(
                '/^git version /',
                '',
                trim((string) shell_exec('git --version')),
            );
        }

        return static::$gitVersion;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$fs = new Filesystem();
        static::$projectRootDir = dirname(__DIR__, 3);
        static::$fixturesDir = Path::join(static::$projectRootDir, 'tests/fixtures');

        static::initComposer();
        static::initSuitRootDir();
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public static function tearDownAfterClass(): void
    {
        if (static::$fs->exists(static::$suitRootDir)) {
            static::$fs->remove(static::$suitRootDir);
        }

        chdir(dirname(__DIR__, 3));

        parent::tearDownAfterClass();
    }

    protected static function initComposer(): void
    {
        $filePath = Path::join(static::$projectRootDir, 'composer.json');
        $composer = json_decode(static::$fs->readFile($filePath), true);
        if (!is_array($composer)) {
            throw new \InvalidArgumentException("Composer JSON file cannot be decoded. '$filePath'");
        }

        static::$composer = $composer;
    }

    protected static function initSuitRootDir(): void
    {
        static::$suitRootDir = Path::join(
            static::getTempDir(),
            static::$composer['name'],
            'suit-' . static::randomId(),
        );
    }

    protected static function getGitTemplateDir(string $type): string
    {
        return Path::join(
            static::$fixturesDir,
            'git-template',
            $type,
        );
    }

    protected static function getTempDir(): string
    {
        $tempDir = getenv('APP_TEST_TEMP_DIR');

        return $tempDir ?: sys_get_temp_dir();
    }

    protected static function randomId(): string
    {
        return date('YmdHis') . rand(1000, 9999);
    }

    protected static function getProjectCacheDir(string $type): string
    {
        return Path::join(
            static::$suitRootDir,
            'cache',
            'project',
            $type,
        );
    }

    protected static function trimTrailingWhitespaces(string $string): string
    {
        return preg_replace('/[ \t]+\n/', "\n", rtrim($string, " \t"));
    }

    protected static function removeColorCodes(string $string): string
    {
        return preg_replace('/\x1B\[[0-9;]*[JKmsu]/', '', $string);
    }

    protected static function normalizePath(string $path): string
    {
        // Remove any kind of funky unicode whitespace.
        $normalized = preg_replace('#\p{C}+|^\./#u', '', $path);

        // Remove self referring paths ("/./").
        $normalized = preg_replace('#/\.(?=/)|^\./|\./$#', '', $normalized);

        // Regex for resolving relative paths.
        $pattern = '#/*[^/.]+/\.\.#Uu';

        while (preg_match($pattern, $normalized)) {
            $normalized = preg_replace($pattern, '', $normalized);
        }

        if (preg_match('#/\.{2}|\.{2}/#', $normalized)) {
            throw new \LogicException("Path is outside of the defined root, path: [$path], resolved: [$normalized]");
        }

        return rtrim($normalized, '/');
    }
    // endregion

    // region Case.
    /**
     * Absolute directory name. This dir is under the static::$suitRootDir.
     */
    protected string $scenarioRootDir = '';

    /**
     * Current working directory of the test case.
     *
     * Points to "static::$suitRootDir/scenario-<random-id>/workspace".
     *
     * @todo Rename to "scenarioWorkspace".
     */
    protected string $cwd = '';

    protected ?Process $process = null;

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->scenarioRootDir = Path::join(
            static::$suitRootDir,
            'scenario-' . static::randomId(),
        );
        static::$fs->mkdir($this->scenarioRootDir);
        $this->cwd = Path::join($this->scenarioRootDir, 'workspace');
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function tearDown(): void
    {
        parent::tearDown();

        if (static::$fs->exists($this->scenarioRootDir)) {
            static::$fs->remove($this->scenarioRootDir);
        }
    }

    // region Case - helper.
    protected function getWorkspacePath(string $path): string
    {
        $normalizedPath = static::normalizePath("{$this->cwd}/$path");
        $this->validateWorkspacePath($normalizedPath);

        return $normalizedPath;
    }

    protected function validateWorkspacePath(string $normalizedPath): void
    {
        if (!str_starts_with($normalizedPath, "{$this->scenarioRootDir}/workspace")) {
            throw new \InvalidArgumentException('Out of working directory.');
        }
    }
    // endregion

    // region Case - do.
    protected function doGitRemoteAdd(string $name, string $uri): static
    {
        $cmd = [
            static::$gitExecutable,
            'remote',
            'add',
            $name,
            $uri,
        ];

        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doCreateProjectInstance(string $type, string $dir): static
    {
        $dirNormalized = $this->getWorkspacePath($dir);
        if (static::$fs->exists("$dirNormalized/composer.json")) {
            throw new \LogicException("A project is already exists in: '$dirNormalized'");
        }

        $this->doCreateProjectCache($type);
        $projectCacheDir = $this->getProjectCacheDir($type);
        static::$fs->mirror($projectCacheDir, $dirNormalized);
        $this->doGitInitLocal($dir);

        $this->doExec([
            static::$composerExecutable,
            'run',
            'post-install-cmd',
        ]);

        return $this;
    }

    protected function doChangeWorkingDirectory(string $dir): static
    {
        $dirNormal = $this->getWorkspacePath($dir);

        if (!str_starts_with($dirNormal, $this->scenarioRootDir)) {
            throw new \InvalidArgumentException('Out of working directory.');
        }

        static::$fs->mkdir($dirNormal);

        if (!chdir($dirNormal)) {
            throw new IOException("Failed to step into directory: '$dirNormal'.");
        }

        $this->cwd = $dirNormal;

        return $this;
    }

    protected function doCreateFile(string $fileName): static
    {
        static::$fs->touch($this->getWorkspacePath($fileName));

        return $this;
    }

    protected function doGitInitLocal(string $dir, string $tpl = 'basic'): static
    {
        $this->doGitInit($dir, $tpl, false);

        return $this;
    }

    protected function doGitInitBare(string $dir, string $type = 'basic'): static
    {
        $dirNormalized = $this->getWorkspacePath($dir);
        if (static::$fs->exists("$dirNormalized/.git")
            || static::$fs->exists("$dirNormalized/config")
        ) {
            throw new \LogicException("A git repository is already exists in: '$dirNormalized'");
        }

        $this->doCreateProjectCache($type);
        $projectCacheDir = $this->getProjectCacheDir($type);
        static::$fs->mirror($projectCacheDir, $dirNormalized);
        $this->doGitInit($dir, $type, true);

        $this->doExec([
            static::$composerExecutable,
            'run',
            'post-install-cmd',
        ]);

        return $this;
    }

    /**
     * @param array<string> $files
     */
    protected function doGitAdd(array $files): static
    {
        $command = [
            static::$gitExecutable,
            'add',
            '--',
            ...$files,
        ];

        $this->process = $this->doExec($command);

        return $this;
    }

    protected function doGitCommit(?string $message = null): static
    {
        $cmd = [
            static::$gitExecutable,
            'commit',
        ];

        if ($message) {
            $cmd[] = '--message=' . $message;
        }

        $this->process = $this->doExec(
            command: $cmd,
            env: [
                'GIT_EDITOR' => '',
                'EDITOR' => '',
            ],
            check: [
                'exitCode' => false,
            ],
        );

        return $this;
    }

    protected function doGitPush(string $remote, string $branch): static
    {
        $this->process = $this->doExec(
            command: [
                static::$gitExecutable,
                'push',
                $remote,
                $branch,
            ],
            check: [
                'exitCode' => false,
            ],
        );

        return $this;
    }

    protected function doGitCommitNewFileWithMessageAndContent(
        string $fileName,
        string $message,
        string $content,
    ): static {
        $this->doCreateFile($fileName);
        static::$fs->dumpFile($fileName, $content);
        $this->doGitAdd([$fileName]);
        $this->doGitCommit($message);

        return $this;
    }

    protected function doGitCheckoutNewBranch(string $branch): static
    {
        $cmd = [
            static::$gitExecutable,
            'checkout',
            '-b',
            $branch,
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doGitCheckoutFile(string $branch, string $file): static
    {
        $cmd = [
            static::$gitExecutable,
            'checkout',
            $branch,
            '--',
            $file,
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doRunGitCheckout(string $branch): static
    {
        $cmd = [
            static::$gitExecutable,
            'checkout',
            $branch,
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doGitBranchCreate(string $branch): static
    {
        $cmd = [
            static::$gitExecutable,
            'branch',
            $branch,
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    /**
     * I run git rebase :upstream
     *
     * I run git rebase :upstream :branch
     *
     * @param string $upstream
     *   Upstream branch to compare against.
     * @param string $branch
     *   Name of the base branch.
     */
    protected function doRunGitRebase(string $upstream, ?string $branch = null): static
    {
        $cmd = [
            static::$gitExecutable,
            'rebase',
            $upstream,
        ];

        if ($branch) {
            $cmd[] = $branch;
        }

        $this->process = $this->doExec(
            command: $cmd,
            check: [
                'exitCode' => false,
            ],
        );

        return $this;
    }

    protected function doGitMerge(string $branch, string $message): static
    {
        $cmd = [
            static::$gitExecutable,
            'merge',
            $branch,
            "--message=$message",
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doGitMergeSquash(string $branch, string $message): static
    {
        $cmd = [
            static::$gitExecutable,
            'merge',
            $branch,
            '--ff',
            '--squash',
            "--message=$message",
        ];
        $this->process = $this->doExec($cmd);

        return $this;
    }

    protected function doGitConfigSetCoreEditor(string $value): static
    {
        $this->doGitConfigSet('core.editor', $value);

        return $this;
    }

    protected function doGitConfigSet(string $name, string $value): static
    {
        $cmd = [
            static::$gitExecutable,
            'config',
            $name,
            $value,
        ];

        $this->process = $this->doExec($cmd, $this->cwd);

        return $this;
    }

    protected function doWait(int $seconds): static
    {
        sleep($seconds);

        return $this;
    }

    /**
     * @param array<string> $command
     */
    protected function doComposer(array $command): static
    {
        array_unshift($command, static::$composerExecutable);

        $this->process = $this->doExec($command);

        return $this;
    }

    protected function doCreateProjectCache(string $projectType): static
    {
        $projectCacheDir = $this->getProjectCacheDir($projectType);
        if (static::$fs->exists($projectCacheDir)) {
            return $this;
        }

        $projectTemplate = Path::join(
            static::$fixturesDir,
            'project-template',
            $projectType,
        );
        static::$fs->mirror($projectTemplate, $projectCacheDir);

        $composerJson = json_decode(
            static::$fs->readFile("$projectCacheDir/composer.json"),
            true,
        );
        $composerJson['repositories']['local']['url'] = static::$projectRootDir;
        static::$fs->dumpFile(
            "$projectCacheDir/composer.json",
            (string) json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
        );

        $this->doExec(
            [
                'composer',
                'update',
            ],
            $projectCacheDir,
        );

        if ($projectType !== 'basic') {
            $master = Path::join(
                static::$fixturesDir,
                'project-template',
                'basic',
            );
            $files = [
                '.git-hooks',
                '.gitignore',
                'RoboFile.php',
            ];
            foreach ($files as $fileName) {
                static::$fs->copy("$master/$fileName", "$projectCacheDir/$fileName");
            }
        }

        $cmd = [
            static::$composerExecutable,
            'install',
            '--no-interaction',
        ];
        $this->doExec($cmd, $projectCacheDir);

        return $this;
    }

    protected function doGitInit(string $dir, string $tpl, bool $bare): void
    {
        $cmd = [
            'git',
            'init',
            '--template=' . $this->getGitTemplateDir($tpl),
        ];
        $this->doChangeWorkingDirectory($dir);


        if ($bare) {
            $cmd[] = '--bare';
            $gitDir = '';
        } else {
            $gitDir = '.git/';
        }

        $gitInit = $this->doExec($cmd);
        $cwdReal = realpath($this->cwd);
        static::assertSame(
            "Initialized empty Git repository in $cwdReal/$gitDir\n",
            $gitInit->getOutput()
        );

        $result = $this->doExec([
            'git',
            'symbolic-ref',
            'HEAD',
            'refs/heads/' . static::$defaultGitBranch,
        ]);
        static::assertSame(0, $result->getExitCode());
    }

    /**
     * @param array<string> $command
     * @param null|array<string, string> $env
     * @param array<string, bool> $check
     */
    protected function doExec(
        array $command,
        ?string $cwd = null,
        ?array $env = null,
        array $check = [],
    ): Process {
        $check += [
            'exitCode' => true,
            'stdErr' => false,
        ];

        $process = new Process(
            command: $command,
            cwd: $cwd,
            env: $env,
        );
        $process->run();
        if ($check['exitCode'] && !$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if ($check['stdErr'] !== false) {
            static::assertSame($check['stdErr'], $process->getErrorOutput());
        }

        return $process;
    }
    // endregion

    // region Case - assert.
    public function assertExitCodeEquals(int $exitCode): void
    {
        static::assertSame(
            $exitCode,
            $this->process->getExitCode(),
            "Exit codes don't match",
        );
    }

    public function assertStdOutContains(string $string): void
    {
        $output = $this->trimTrailingWhitespaces($this->process->getOutput());
        $output = $this->removeColorCodes($output);

        static::assertStringContainsString($string, $output);
    }

    public function assertStdErrContains(string $string): void
    {
        $output = $this->trimTrailingWhitespaces($this->process->getErrorOutput());
        $output = $this->removeColorCodes($output);

        static::assertStringContainsString($string, $output);
    }

    public function assertGitLogLength(int $expected): void
    {
        $cmd = [
            'bash',
            '-c',
            sprintf(
                '%s log --format=%s | cat',
                static::$gitExecutable,
                '%h',
            ),
        ];
        $gitLog = $this->doExec(
            command: $cmd,
            check: [
                'exitCode' => false,
            ],
        );

        static::assertSame(
            $expected,
            substr_count($gitLog->getOutput(), "\n"),
        );
    }

    public function assertGitLogIsNotEmpty(): void
    {
        $cmd = [
            static::$gitExecutable,
            'log',
            '-1',
        ];

        $gitLog = $this->doExec($cmd);
        static::assertNotEquals('', $gitLog->getOutput());
    }

    public function assertGitLogIsEmpty(): void
    {
        $cmd = [
            static::$gitExecutable,
            'log',
            '-1',
        ];
        $gitLog = $this->doExec($cmd);
        static::assertEquals('', $gitLog->getOutput());
    }
    // endregion
    // endregion
}
