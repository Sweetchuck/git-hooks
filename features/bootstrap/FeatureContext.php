<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use PHPUnit\Framework\Assert;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

// @codingStandardsIgnoreStart
class FeatureContext implements Context
    // @codingStandardsIgnoreEnd
{

    /**
     * Base reference point. The directory of the behat.yml.
     *
     * @var string
     */
    protected static $projectRootDir = '';

    /**
     * Relative to static::$projectRootDir.
     *
     * @var string
     */
    protected static $fixturesDir = 'fixtures';

    /**
     * @var string
     */
    protected static $gitExecutable = 'git';

    /**
     * @var array
     */
    protected static $composer = [];

    /**
     * Random directory name somewhere in the /tmp directory.
     *
     * @var string
     */
    protected static $suitRootDir = '';

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected static $fs;

    /**
     * @BeforeSuite
     */
    public static function hookBeforeSuite()
    {
        static::$projectRootDir = getcwd();

        static::initComposer();
        static::initSuitRootDir();
        static::initFilesystem();
    }

    /**
     * @AfterSuite
     */
    public static function hookAfterSuite()
    {
        if (getenv('SWEETCHUCK_GIT_HOOKS_SKIP_AFTER_CLEANUP') === 'true') {
            return;
        }

        if (static::$fs->exists(static::$suitRootDir)) {
            static::$fs->remove(static::$suitRootDir);
        }
    }

    protected static function initFilesystem()
    {
        static::$fs = new Filesystem();
    }

    protected static function initComposer()
    {
        $fileName = static::$projectRootDir . '/composer.json';
        static::$composer = json_decode(file_get_contents($fileName), true);
        if (static::$composer === null) {
            throw new \InvalidArgumentException("Composer JSON file cannot be decoded. '$fileName'");
        }
    }

    protected static function initSuitRootDir()
    {
        static::$suitRootDir = implode('/', [
            sys_get_temp_dir(),
            static::$composer['name'],
            'suit-' . static::randomId(),
        ]);
    }

    protected static function getGitTemplateDir(string $type): string
    {
        return implode('/', [
            static::$projectRootDir,
            static::$fixturesDir,
            'git-template',
            $type,
        ]);
    }

    protected static function normalizePath(string $path): string
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
            throw new \LogicException("Path is outside of the defined root, path: [$path], resolved: [$normalized]");
        }

        return rtrim($normalized, '/');
    }

    /**
     * Absolute directory name. This dir is under the static::$suitRootDir.
     *
     * @var string
     */
    protected $scenarioRootDir = '';

    /**
     * Current working directory.
     *
     * @var string
     */
    protected $cwd = '';

    /**
     * @var Symfony\Component\Process\Process
     */
    protected $process = null;

    /**
     * Prepares test folders in the temporary directory.
     *
     * @BeforeScenario
     */
    public function initScenarioRootDir()
    {
        $this->scenarioRootDir = static::$suitRootDir . '/scenario-' .  static::randomId();
        static::$fs->mkdir($this->scenarioRootDir);
        $this->cwd = "{$this->scenarioRootDir}/workspace";
    }

    /**
     * @AfterScenario
     */
    public function cleanScenarioRootDir()
    {
        if (getenv('SWEETCHUCK_GIT_HOOKS_SKIP_AFTER_CLEANUP') === 'true') {
            return;
        }

        if (static::$fs->exists($this->scenarioRootDir)) {
            static::$fs->remove($this->scenarioRootDir);
        }
    }

    protected static function randomId(): string
    {
        return md5(microtime(true) * rand(0, 10000));
    }

    /**
     * @Given I run git add remote :name :uri
     */
    public function doGitRemoteAdd(string $name, string $uri)
    {
        $cmdPattern = '%s remote add %s %s';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($name),
            escapeshellarg($uri),
        ];

        $this->process = $this->doExec(vsprintf($cmdPattern, $cmdArgs));
    }

    /**
     * @Given I create a :type project in :dir directory
     */
    public function doCreateProjectInstance(string $dir, string $type)
    {
        $dirNormalized = $this->getWorkspacePath($dir);
        if (static::$fs->exists("$dirNormalized/composer.json")) {
            throw new \LogicException("A project is already exists in: '$dirNormalized'");
        }

        $this->doCreateProjectCache($type);
        $projectCacheDir = $this->getProjectCacheDir($type);
        static::$fs->mirror($projectCacheDir, $dirNormalized);
        $this->doGitInitLocal($dir);

        $this->doExec('composer run post-install-cmd');
    }

    /**
     * @Given I am in the :dir directory
     */
    public function doChangeWorkingDirectory(string $dir)
    {
        $dirNormal = $this->getWorkspacePath($dir);

        if (strpos($dirNormal, $this->scenarioRootDir) !== 0) {
            throw new \InvalidArgumentException('Out of working directory.');
        }

        static::$fs->mkdir($dirNormal);

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
        static::$fs->touch($this->getWorkspacePath($fileName));
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
     * @Given I initialize a bare Git repo in directory :dir with :ttype git template
     */
    public function doGitInitBare(string $dir, string $type = 'basic')
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

        $this->doExec('composer run post-install-cmd');
    }

    /**
     * @Given I run git add :files
     */
    public function doGitAdd(string $files)
    {
        $files = preg_split('/, /', $files);
        $cmdPattern = '%s add --' . str_repeat(' %s', count($files));
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
        ];

        foreach ($files as $file) {
            $cmdArgs[] = escapeshellcmd($file);
        }

        $this->process = $this->doExec(vsprintf($cmdPattern, $cmdArgs));
    }

    /**
     * @Given I run git commit
     * @Given /^I run git commit -m "(?P<message>[^"]+)"$/
     */
    public function doGitCommit(?string $message = null)
    {
        $cmdPattern = '%s commit';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
        ];

        if ($message) {
            $cmdPattern .= ' -m %s';
            $cmdArgs[] = escapeshellarg($message);
        }

        $this->process = $this->doExec(
            vsprintf($cmdPattern, $cmdArgs),
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
        $cmd = vsprintf('%s push %s %s', [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($remote),
            escapeshellarg($branch)
        ]);

        $this->process = $this->doExec(
            $cmd,
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
        PyStringNode $content
    ) {
        $this->doCreateFile($fileName);
        static::$fs->dumpFile($fileName, $content);
        $this->doGitAdd($fileName);
        $this->doGitCommit($message);
    }

    /**
     * @Given I run git checkout -b :branch
     */
    public function doGitCheckoutNewBranch(string $branch)
    {
        $cmd = sprintf(
            '%s checkout -b %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git checkout :branch -- :file
     */
    public function doGitCheckoutFile(string $branch, string $file)
    {
        $cmd = sprintf(
            '%s checkout %s -- %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch),
            escapeshellarg($file)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git checkout :branch
     */
    public function doRunGitCheckout(string $branch)
    {
        $cmd = sprintf(
            '%s checkout %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git branch :branch
     */
    public function doGitBranchCreate(string $branch)
    {
        $cmd = sprintf(
            '%s branch %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch)
        );
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
        $cmdPattern = '%s rebase %s';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($upstream),
        ];

        if ($branch) {
            $cmdPattern .= ' %s';
            $cmdArgs[] = escapeshellarg($branch);
        }

        $this->process = $this->doExec(
            vsprintf($cmdPattern, $cmdArgs),
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
        $cmd = sprintf(
            '%s merge %s -m %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch),
            escapeshellarg($message)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given I run git merge :branch --squash -m :message
     */
    public function doGitMergeSquash(string $branch, string $message)
    {
        $cmd = sprintf(
            '%s merge %s --ff --squash -m %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($branch),
            escapeshellarg($message)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @Given /^I run git config core.editor (?P<editor>true|false)$/
     */
    public function doGitConfigSetCoreEditor(string $editor)
    {
        $this->doGitConfigSet('core.editor', $editor);
    }

    /**
     * @Given /^I wait for (?P<amount>\d+) seconds$/
     */
    public function doWait(int $amount)
    {
        sleep(intval($amount));
    }

    /**
     * @Then /^the exit code should be (?P<exitCode>\d+)$/
     */
    public function assertExitCodeEquals(string $exitCode)
    {
        Assert::assertEquals(
            $exitCode,
            $this->process->getExitCode(),
            "Exit codes don't match"
        );
    }

    /**
     * @Then /^the stdOut should contains the following text:$/
     *
     * @param \Behat\Gherkin\Node\PyStringNode $string
     */
    public function assertStdOutContains(PyStringNode $string)
    {
        $output = $this->trimTrailingWhitespaces($this->process->getOutput());
        $output = $this->removeColorCodes($output);

        Assert::assertContains($string->getRaw(), $output);
    }

    /**
     * @Then /^the stdErr should contains the following text:$/
     *
     * @param \Behat\Gherkin\Node\PyStringNode $string
     */
    public function assertStdErrContains(PyStringNode $string)
    {
        $output = $this->trimTrailingWhitespaces($this->process->getErrorOutput());
        $output = $this->removeColorCodes($output);

        Assert::assertContains($string->getRaw(), $output);
    }

    /**
     * @Given /^the number of commits is (?P<expected>\d+)$/
     */
    public function assertGitLogLength(int $expected)
    {
        $cmdPattern = '%s log --format=%s | cat';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg('%h'),
        ];
        $git_log = $this->doExec(
            vsprintf($cmdPattern, $cmdArgs),
            [
                'exitCode' => false,
            ]
        );

        Assert::assertEquals(
            $expected,
            substr_count($git_log->getOutput(), "\n")
        );
    }

    /**
     * @Given the git log is not empty
     */
    public function assertGitLogIsNotEmpty()
    {
        $cmdPattern = '%s log -1';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
        ];
        $git_log = $this->doExec(vsprintf($cmdPattern, $cmdArgs));
        Assert::assertNotEquals('', $git_log->getOutput());
    }

    /**
     * @Given the git log is empty
     */
    public function assertGitLogIsEmpty()
    {
        $cmdPattern = '%s log -1';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
        ];
        $gitLog = $this->doExec(vsprintf($cmdPattern, $cmdArgs));
        Assert::assertEquals('', $gitLog->getOutput());
    }

    protected function getWorkspacePath(string $path): string
    {
        $normalizedPath = static::normalizePath("{$this->cwd}/$path");
        $this->validateWorkspacePath($normalizedPath);

        return $normalizedPath;
    }

    protected function validateWorkspacePath(string $normalizedPath)
    {
        if (strpos($normalizedPath, "{$this->scenarioRootDir}/workspace")
            !== 0
        ) {
            throw new \InvalidArgumentException('Out of working directory.');
        }
    }

    protected function doCreateProjectCache(string $projectType)
    {
        $projectCacheDir = $this->getProjectCacheDir($projectType);
        if (static::$fs->exists($projectCacheDir)) {
            return;
        }

        $projectTemplate = implode('/', [
            static::$projectRootDir,
            'fixtures',
            'project-template',
            $projectType,
        ]);
        static::$fs->mirror($projectTemplate, $projectCacheDir);

        $package = json_decode(file_get_contents("$projectCacheDir/composer.json"), true);
        $package['repositories']['local']['url'] = static::$projectRootDir;
        static::$fs->dumpFile("$projectCacheDir/composer.json", json_encode($package, JSON_PRETTY_PRINT));

        if ($projectType !== 'basic') {
            $master = implode('/', [
                static::$projectRootDir,
                'fixtures',
                'project-template',
                'basic',
            ]);
            $files = [
                '.git-hooks',
                '.gitignore',
                'RoboFile.php',
            ];
            foreach ($files as $fileName) {
                static::$fs->copy("$master/$fileName", "$projectCacheDir/$fileName");
            }
        }

        $this->doExecCwd($projectCacheDir, 'composer install --no-interaction');
    }

    /**
     * I initialize a Git repo.
     */
    protected function doGitInit(string $dir, string $tpl, bool $bare)
    {
        $this->doChangeWorkingDirectory($dir);
        $cmdPattern = '%s init --template=%s';
        $cmdArgs = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg(static::getGitTemplateDir($tpl)),
        ];

        if ($bare) {
            $cmdPattern .= ' --bare';
            $gitDir = '';
        } else {
            $gitDir = '.git/';
        }

        $cmd = vsprintf($cmdPattern, $cmdArgs);

        $gitInit = $this->doExec($cmd);
        $cwdReal = realpath($this->cwd);
        Assert::assertEquals(
            "Initialized empty Git repository in $cwdReal/$gitDir\n",
            $gitInit->getOutput()
        );
    }

    protected function doGitConfigSet(string $name, string $value)
    {
        $cmd = sprintf(
            '%s config %s %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($name),
            escapeshellarg($value)
        );
        $this->process = $this->doExec($cmd);
    }

    protected function doExecCwd(string $wd, string $cmd, array $check = []): Process
    {
        $cwdBackup = $this->cwd;
        chdir($wd);
        $return = $this->doExec($cmd, $check);
        $this->cwd = $cwdBackup;

        return $return;
    }

    protected function doExec(string $cmd, array $check = []): Process
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
            Assert::assertEquals($check['stdErr'], $process->getErrorOutput());
        }

        return $process;
    }

    protected function getProjectCacheDir(string $type): string
    {
        return static::$suitRootDir . "/cache/project/$type";
    }

    protected function trimTrailingWhitespaces(string $string): string
    {
        return preg_replace('/[ \t]+\n/', "\n", rtrim($string, " \t"));
    }

    protected function removeColorCodes(string $string): string
    {
        return preg_replace('/\x1B\[[0-9;]*[JKmsu]/', '', $string);
    }
}
