<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Test\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Acceptance extends Module
{

    protected string $projectRootDir;

    /**
     * Absolute path to the fixtures directory.
     */
    protected string $fixturesDir = '';

    protected array $composer = [];

    /**
     * @var
     */
    protected string $suitRootDir;

    protected Filesystem $fs;

    /**
     * Absolute directory name. This dir is under the static::$suitRootDir.
     */
    protected string $scenarioRootDir = '';

    /**
     * Current working directory.
     */
    protected string $cwd = '';

    protected ?Process $process = null;

    protected string $defaultGitBranch = '1.x';

    /**
     * {@inheritDoc}
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);

        $this->projectRootDir = getcwd();

        $this->initComposer();
        $this->initSuitRootDir();
        $this->initFilesystem();
    }

    /**
     * {@inheritDoc}
     */
    public function _afterSuite()
    {
        if ($this->fs->exists($this->suitRootDir)) {
            //$this->fs->remove($this->suitRootDir);
        }

        chdir(dirname(__DIR__, 3));

        parent::_afterSuite();
    }

    /**
     * {@inheritDoc}
     */
    public function _before(TestInterface $test)
    {
        parent::_before($test);

        $this->fixturesDir = codecept_data_dir('fixtures');

        $this->scenarioRootDir = "{$this->suitRootDir}/scenario-" .  $this->randomId();
        $this->fs->mkdir($this->scenarioRootDir);
        $this->cwd = "{$this->scenarioRootDir}/workspace";
    }

    /**
     * {@inheritDoc}
     */
    public function _after(TestInterface $test)
    {
        parent::_after($test);

        if ($this->fs->exists($this->scenarioRootDir)) {
            //$this->fs->remove($this->scenarioRootDir);
        }
    }

    /**
     * @Given I run git remote add :name :uri
     */
    public function doGitRemoteAdd(string $name, string $uri)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'remote',
            'add',
            $name,
            $uri,
        ];

        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I create a :type project in :dir directory
     */
    public function doCreateProjectInstance(string $type, string $dir)
    {
        $dirNormalized = $this->getWorkspacePath($dir);
        if ($this->fs->exists("$dirNormalized/composer.json")) {
            throw new LogicException("A project is already exists in: '$dirNormalized'");
        }

        $this->doCreateProjectCache($type);
        $projectCacheDir = $this->getProjectCacheDir($type);
        $this->fs->mirror($projectCacheDir, $dirNormalized);
        $this->doGitInitLocal($dir);

        $this->doExec([
            $this->config['composerExecutable'],
            'run',
            'post-install-cmd',
        ]);
    }

    /**
     * @Given I am in the :dir directory
     */
    public function doChangeWorkingDirectory(string $dir)
    {
        $dirNormal = $this->getWorkspacePath($dir);

        if (strpos($dirNormal, $this->scenarioRootDir) !== 0) {
            throw new InvalidArgumentException('Out of working directory.');
        }

        $this->fs->mkdir($dirNormal);

        if (!chdir($dirNormal)) {
            throw new IOException("Failed to step into directory: '$dirNormal'.");
        }

        $this->cwd = $dirNormal;
    }

    /**
     * @Given I create a :fileName file
     */
    public function doCreateFile(string $fileName)
    {
        $this->fs->touch($this->getWorkspacePath($fileName));
    }

    /**
     * @Given I initialize a local Git repo in directory :dir
     * @Given I initialize a local Git repo in directory :dir with :tpl git template
     */
    public function doGitInitLocal(string $dir, string $tpl = 'basic')
    {
        $this->doGitInit($dir, $tpl, false);
    }

    /**
     * @Given I initialize a bare Git repo in directory :dir
     * @Given I initialize a bare Git repo in directory :dir with :type git template
     */
    public function doGitInitBare(string $dir, string $type = 'basic')
    {
        $dirNormalized = $this->getWorkspacePath($dir);
        if ($this->fs->exists("$dirNormalized/.git")
            || $this->fs->exists("$dirNormalized/config")
        ) {
            throw new LogicException("A git repository is already exists in: '$dirNormalized'");
        }

        $this->doCreateProjectCache($type);
        $projectCacheDir = $this->getProjectCacheDir($type);
        $this->fs->mirror($projectCacheDir, $dirNormalized);
        $this->doGitInit($dir, $type, true);

        $this->doExec([
            $this->config['composerExecutable'],
            'run',
            'post-install-cmd',
        ]);
    }

    /**
     * @Given I run git add :files
     */
    public function doGitAdd(string $files)
    {
        $cmd = array_merge(
            [
                $this->config['gitExecutable'],
                'add',
                '--',
            ],
            preg_split('/, /', $files)
        );

        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git commit
     * @Given /^I run git commit -m "(?P<message>[^"]+)"$/
     */
    public function doGitCommit(?string $message = null)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'commit',
        ];

        if ($message) {
            $cmd[] = '-m';
            $cmd[] = $message;
        }

        $this->process = $this->doExec(
            $cmd,
            [
                'exitCode' => false,
            ]
        );
    }

    /**
     * @Given I run git push :remote :branch
     */
    public function doGitPush(string $remote, string $branch)
    {
        $this->process = $this->doExec(
            [
                $this->config['gitExecutable'],
                'push',
                $remote,
                $branch,
            ],
            [
                'exitCode' => false,
            ]
        );
    }

    /**
     * @Given I commit a new :fileName file with message :message and content:
     */
    public function doGitCommitNewFileWithMessageAndContent(
        string $fileName,
        string $message,
        string $content
    ) {
        $this->doCreateFile($fileName);
        $this->fs->dumpFile($fileName, $content);
        $this->doGitAdd($fileName);
        $this->doGitCommit($message);
    }

    /**
     * @Given I run git checkout -b :branch
     */
    public function doGitCheckoutNewBranch(string $branch)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'checkout',
            '-b',
            $branch,
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git checkout :branch -- :file
     */
    public function doGitCheckoutFile(string $branch, string $file)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'checkout',
            $branch,
            '--',
            $file,
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git checkout :branch
     */
    public function doRunGitCheckout(string $branch)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'checkout',
            $branch
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git branch :branch
     */
    public function doGitBranchCreate(string $branch)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'branch',
            $branch
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git rebase :upstream
     * @Given I run git rebase :upstream :branch
     *
     * @param string $upstream
     *   Upstream branch to compare against.
     * @param string $branch
     *   Name of the base branch.
     */
    public function doRunGitRebase(string $upstream, ?string $branch = null)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'rebase',
            $upstream,
        ];

        if ($branch) {
            $cmd[] = $branch;
        }

        $this->process = $this->doExec(
            $cmd,
            [
                'exitCode' => false,
            ]
        );
    }

    /**
     * @Given I run git merge :branch -m :message
     */
    public function doGitMerge(string $branch, string $message)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'merge',
            $branch,
            '-m',
            $message,
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git merge :branch --squash -m :message
     */
    public function doGitMergeSquash(string $branch, string $message)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'merge',
            $branch,
            '--ff',
            '--squash',
            '-m',
            $message
        ];
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given /^I run git config core.editor (?P<value>true|false)$/
     */
    public function doGitConfigSetCoreEditor(string $value)
    {
        $this->doGitConfigSet('core.editor', $value);
    }

    /**
     * @Given /^I run git config "(?P<name>[^"]+)" (?P<vale>.+)$/
     */
    public function doGitConfigSet(string $name, string $value)
    {
        $cmd = [
            $this->config['gitExecutable'],
            'config',
            $name,
            $value,
        ];

        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given /^I wait for (?P<amount>\d+) seconds$/
     */
    public function doWait(string $amount)
    {
        sleep(intval($amount));
    }

    public function doComposer(array $command)
    {
        array_unshift($command, $this->config['composerExecutable']);

        $this->process = $this->doExec($command);
    }

    /**
     * @Then /^the exit code should be (?P<exitCode>\d+)$/
     */
    public function assertExitCodeEquals(string $exitCode)
    {
        $this->assertSame(
            (int) $exitCode,
            $this->process->getExitCode(),
            "Exit codes don't match"
        );
    }

    /**
     * @Then /^the stdOut should contains the following text:$/
     */
    public function assertStdOutContains(string $string)
    {
        $output = $this->trimTrailingWhitespaces($this->process->getOutput());
        $output = $this->removeColorCodes($output);

        $this->assertStringContainsString($string, $output);
    }

    /**
     * @Then /^the stdErr should contains the following text:$/
     */
    public function assertStdErrContains(string $string)
    {
        $output = $this->trimTrailingWhitespaces($this->process->getErrorOutput());
        $output = $this->removeColorCodes($output);

        $this->assertStringContainsString($string, $output);
    }

    /**
     * @Given /^the number of commits is (?P<expected>\d+)$/
     */
    public function assertGitLogLength(string $expected)
    {
        $cmd = [
            'bash',
            '-c',
            sprintf(
                '%s log --format=%s | cat',
                $this->config['gitExecutable'],
                '%h'
            ),
        ];
        $gitLog = $this->doExec(
            $cmd,
            [
                'exitCode' => false,
            ]
        );

        $this->assertSame(
            (int) $expected,
            substr_count($gitLog->getOutput(), "\n")
        );
    }

    /**
     * @Given the git log is not empty
     */
    public function assertGitLogIsNotEmpty()
    {
        $cmd = [
            $this->config['gitExecutable'],
            'log',
            '-1',
        ];

        $gitLog = $this->doExec($cmd);
        $this->assertNotEquals('', $gitLog->getOutput());
    }

    /**
     * @Given the git log is empty
     */
    public function assertGitLogIsEmpty()
    {
        $cmd = [
            $this->config['gitExecutable'],
            'log',
            '-1',
        ];
        $gitLog = $this->doExec($cmd);
        $this->assertEquals('', $gitLog->getOutput());
    }

    protected function getWorkspacePath(string $path): string
    {
        $normalizedPath = $this->normalizePath("{$this->cwd}/$path");
        $this->validateWorkspacePath($normalizedPath);

        return $normalizedPath;
    }

    protected function validateWorkspacePath(string $normalizedPath)
    {
        if (strpos($normalizedPath, "{$this->scenarioRootDir}/workspace") !== 0) {
            throw new InvalidArgumentException('Out of working directory.');
        }
    }

    protected function doCreateProjectCache(string $projectType)
    {
        $projectCacheDir = $this->getProjectCacheDir($projectType);
        if ($this->fs->exists($projectCacheDir)) {
            return;
        }

        $projectTemplate = implode('/', [
            $this->fixturesDir,
            'project-template',
            $projectType,
        ]);
        $this->fs->mirror($projectTemplate, $projectCacheDir);

        $composerJson = json_decode(file_get_contents("$projectCacheDir/composer.json"), true);
        $composerJson['repositories']['local']['url'] = $this->projectRootDir;
        $this->fs->dumpFile(
            "$projectCacheDir/composer.json",
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $composerLock = json_decode(file_get_contents("$projectCacheDir/composer.lock"), true);
        foreach ($composerLock['packages'] as $i => $package) {
            if ($package['name'] !== 'sweetchuck/git-hooks') {
                continue;
            }

            $composerLock['packages'][$i]['dist'] = [
                'type' => 'path',
                'url' => $this->projectRootDir,
                'reference' => 'abcdefg',
            ];

            $this->fs->dumpFile(
                "$projectCacheDir/composer.lock",
                json_encode($composerLock, JSON_PRETTY_PRINT)
            );

            break;
        }

        if ($projectType !== 'basic') {
            $master = implode('/', [
                $this->fixturesDir,
                'project-template',
                'basic',
            ]);
            $files = [
                '.git-hooks',
                '.gitignore',
                'RoboFile.php',
            ];
            foreach ($files as $fileName) {
                $this->fs->copy("$master/$fileName", "$projectCacheDir/$fileName");
            }
        }

        $cmd = [
            $this->config['composerExecutable'],
            'install',
            '--no-interaction',
        ];

        $this->doExecCwd($projectCacheDir, $cmd);
    }

    /**
     * I initialize a Git repo.
     */
    protected function doGitInit(string $dir, string $tpl, bool $bare)
    {
        $cmd = [
            $this->config['gitExecutable'],
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
        $this->assertSame(
            "Initialized empty Git repository in $cwdReal/$gitDir\n",
            $gitInit->getOutput()
        );

        $result = $this->doExec([
            $this->config['gitExecutable'],
            'symbolic-ref',
            'HEAD',
            'refs/heads/' . $this->defaultGitBranch,
        ]);
        $this->assertSame(0, $result->getExitCode());
    }

    protected function doExecCwd(string $wd, array $cmd, array $check = []): Process
    {
        $cwdBackup = $this->cwd;
        chdir($wd);
        $return = $this->doExec($cmd, $check);
        $this->cwd = $cwdBackup;

        return $return;
    }

    protected function doExec(array $cmd, array $check = []): Process
    {
        $check += [
            'exitCode' => true,
            'stdErr' => false,
        ];

        $process = new Process($cmd);
        $process->run();
        if ($check['exitCode'] && !$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if ($check['stdErr'] !== false) {
            $this->assertSame($check['stdErr'], $process->getErrorOutput());
        }

        return $process;
    }

    protected function getProjectCacheDir(string $type): string
    {
        return  "{$this->suitRootDir}/cache/project/$type";
    }

    protected function trimTrailingWhitespaces(string $string): string
    {
        return preg_replace('/[ \t]+\n/', "\n", rtrim($string, " \t"));
    }

    protected function removeColorCodes(string $string): string
    {
        return preg_replace('/\x1B\[[0-9;]*[JKmsu]/', '', $string);
    }

    protected function initComposer()
    {
        $fileName =  "{$this->projectRootDir}/composer.json";
        $this->composer = json_decode(file_get_contents($fileName), true);
        if ($this->composer === null) {
            throw new InvalidArgumentException("Composer JSON file cannot be decoded. '$fileName'");
        }
    }

    protected function initSuitRootDir()
    {
        $this->suitRootDir = implode('/', [
            sys_get_temp_dir(),
            $this->composer['name'],
            'suit-' . $this->randomId(),
        ]);
    }

    protected function initFilesystem()
    {
        $this->fs = new Filesystem();
    }

    protected function randomId(): string
    {
        return md5((string) (microtime(true) * rand(0, 10000)));
    }

    protected function normalizePath(string $path): string
    {
        // Remove any kind of funky unicode whitespace.
        $normalized = preg_replace('#\p{C}+|^\./#u', '', $path);

        // Remove self referring paths ("/./").
        $normalized = preg_replace('#/\.(?=/)|^\./|\./$#', '', $normalized);

        // Regex for resolving relative paths.
        $pattern = '#\/*[^/\.]+/\.\.#Uu';

        while (preg_match($pattern, $normalized)) {
            $normalized = preg_replace($pattern, '', $normalized);
        }

        if (preg_match('#/\.{2}|\.{2}/#', $normalized)) {
            throw new LogicException("Path is outside of the defined root, path: [$path], resolved: [$normalized]");
        }

        return rtrim($normalized, '/');
    }

    protected function getGitTemplateDir(string $type): string
    {
        return implode('/', [
            $this->fixturesDir,
            'git-template',
            $type,
        ]);
    }
}
