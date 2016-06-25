<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Class FeatureContext.
 */
// @codingStandardsIgnoreStart
class FeatureContext extends \PHPUnit_Framework_Assert implements Context
    // @codingStandardsIgnoreEnd
{

    /**
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
     * @var string
     */
    protected static $suitRootDir = '';

    /**
     * @var Filesystem
     */
    protected static $fs = null;

    /**
     * @var string[]
     */
    protected static $gitHooks = [
        'applypatch-msg',
        'commit-msg',
        'post-applypatch',
        'post-checkout',
        'post-commit',
        'post-merge',
        'post-receive',
        'post-rewrite',
        'post-update',
        'pre-applypatch',
        'pre-auto-gc',
        'pre-commit',
        'pre-push',
        'pre-rebase',
        'pre-receive',
        'prepare-commit-msg',
        'push-to-checkout',
        'update',
    ];

    /**
     * @BeforeSuite
     */
    public static function hookBeforeSuite()
    {
        static::$projectRootDir = getcwd();

        static::initComposer();
        static::initTmpDirRoot();
        static::initFilesystem();
        static::cleanGitTemplate();
        static::initGitTemplate();
        static::cleanTmpDirRoot();
    }

    /**
     * @AfterSuite
     */
    public static function hookAfterSuite()
    {
        static::cleanTmpDirRoot();
        static::cleanGitTemplate();
    }

    protected static function initFilesystem()
    {
        static::$fs = new Filesystem();
    }

    protected static function initComposer()
    {
        $file_name = static::$projectRootDir . '/composer.json';
        static::$composer = json_decode(file_get_contents($file_name), true);
        if (static::$composer === null) {
            throw new \InvalidArgumentException("Composer JSON file cannot be decoded. '$file_name'");
        }
    }

    protected static function initTmpDirRoot()
    {
        static::$suitRootDir = sys_get_temp_dir() . '/'
            . static::$composer['name'];
    }

    /**
     * Cleans test folders in the temporary directory.
     */
    protected static function cleanTmpDirRoot()
    {
        if (static::$fs->exists(static::$suitRootDir)) {
            static::$fs->remove(static::$suitRootDir);
        }
    }

    protected static function initGitTemplate()
    {
        $git_template_dir = static::getGitTemplateDir();
        $dir = "$git_template_dir/branches";
        static::$fs->mkdir($dir);

        $files = array_fill_keys(static::$gitHooks, 0777);
        $files['_common'] = 0666;
        $mask = umask();
        foreach ($files as $file => $mode) {
            $src = static::$projectRootDir . "/$file";
            $dst = "$git_template_dir/hooks/$file";
            static::$fs->copy($src, $dst, true);
            static::$fs->chmod($dst, $mode, $mask);
        }
    }

    protected static function cleanGitTemplate()
    {
        $git_template_dir = static::getGitTemplateDir();
        if (static::$fs->exists("$git_template_dir/hooks")) {
            static::$fs->remove("$git_template_dir/hooks");
        }
    }

    /**
     * @return string
     */
    protected static function getGitTemplateDir()
    {
        return static::$projectRootDir . '/' . static::$fixturesDir
        . '/git-template';
    }

    /**
     * Normalize path.
     *
     * @param string $path
     *
     * @return string
     *   Normalized path.
     */
    protected static function normalizePath($path)
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
    public function initWorkingDir()
    {
        $this->scenarioRootDir = static::$suitRootDir . '/' . md5(microtime()
                * rand(0, 10000));
        static::$fs->mkdir($this->scenarioRootDir);
        $this->cwd = "{$this->scenarioRootDir}/workspace";
    }

    /**
     * @AfterScenario
     */
    public function cleanWorkingDir()
    {
        if (static::$fs->exists($this->scenarioRootDir)) {
            static::$fs->remove($this->scenarioRootDir);
        }
    }

    /**
     * @Given I run git add remote :name :uri
     *
     * @param string $name
     * @param string $uri
     */
    public function doGitRemoteAdd($name, $uri)
    {
        $cmd_pattern = '%s remote add %s %s';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($name),
            escapeshellarg($uri),
        ];

        $this->process = $this->doExec(vsprintf($cmd_pattern, $cmd_args));
    }

    /**
     * @Given I create a :type project in :dir directory
     *
     * @param string $dir
     * @param string $type
     */
    public function doCreateProjectInstance($dir, $type)
    {
        $dir_normalized = $this->getWorkspacePath($dir);
        if (static::$fs->exists("$dir_normalized/composer.json")) {
            throw new \LogicException("A project is already exists in: '$dir_normalized'");
        }

        $this->doCreateProjectCache($type);
        $project_cache_dir = $this->getProjectCacheDir($type);
        static::$fs->mirror($project_cache_dir, $dir_normalized);
        $this->doGitInitLocal($dir);
    }

    /**
     * @Given I am in the :dir directory
     *
     * @param string $dir
     *
     * @throws \Exception
     */
    public function doChangeWorkingDirectory($dir)
    {
        $dir_normal = $this->getWorkspacePath($dir);

        if (strpos($dir_normal, $this->scenarioRootDir) !== 0) {
            throw new \InvalidArgumentException('Out of working directory.');
        }

        static::$fs->mkdir($dir_normal);

        if (!chdir($dir_normal)) {
            throw new IOException("Failed to step into directory: '$dir_normal'.");
        }

        $this->cwd = $dir_normal;
    }

    /**
     * @Given I create a :file_name file
     *
     * @param string $file_name
     */
    public function doCreateFile($file_name)
    {
        static::$fs->touch($this->getWorkspacePath($file_name));
    }

    /**
     * @Given I initialize a local Git repo in directory :dir
     *
     * @param string $dir
     */
    public function doGitInitLocal($dir)
    {
        $this->doGitInit($dir, false);
    }

    /**
     * @Given I initialize a bare Git repo in directory :dir
     *
     * @param string $dir
     */
    public function doGitInitBare($dir)
    {
        $this->doGitInit($dir, true);

        $src = static::$projectRootDir . '/' . static::$fixturesDir . '/'
            . 'project-template/basic';

        $files = [
            '.gitignore',
            'composer.json',
            'composer.lock',
            'RoboFile.php',
        ];
        foreach ($files as $file) {
            static::$fs->copy("$src/$file", "./$file");
        }

        $this->doExec('composer install');
    }

    /**
     * @Given I run git add :files
     *
     * @param string $files
     */
    public function doGitAdd($files)
    {
        $files = preg_split('/, /', $files);
        $cmd_pattern = '%s add --' . str_repeat(' %s', count($files));
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
        ];

        foreach ($files as $file) {
            $cmd_args[] = escapeshellcmd($file);
        }

        $this->process = $this->doExec(vsprintf($cmd_pattern, $cmd_args));
    }

    /**
     * @Given I run git commit
     * @Given /^I run git commit -m "(?P<message>[^"]+)"$/
     *
     * @param string $message
     */
    public function doGitCommit($message = null)
    {
        $cmd_pattern = '%s commit';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
        ];

        if ($message) {
            $cmd_pattern .= ' -m %s';
            $cmd_args[] = escapeshellarg($message);
        }

        $this->process = $this->doExec(
            vsprintf($cmd_pattern, $cmd_args),
            [
                'exitCode' => false,
            ]
        );
    }

    /**
     * @Given I run git push :remote :branch
     *
     * @param string $remote
     * @param string $branch
     */
    public function doGitPush($remote, $branch)
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
     * @Given I commit a new :file_name file with message :message and content:
     *
     * @param string $file_name
     * @param string $message
     * @param \Behat\Gherkin\Node\PyStringNode $content
     */
    public function doGitCommitNewFileWithMessageAndContent($file_name, $message, PyStringNode $content)
    {
        $this->doCreateFile($file_name);
        static::$fs->dumpFile($file_name, $content);
        $this->doGitAdd($file_name);
        $this->doGitCommit($message);
    }

    /**
     * @Given I run git checkout -b :branch
     *
     * @param string $branch
     */
    public function doGitCheckoutNewBranch($branch)
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
     *
     * @param string $branch
     * @param string $file
     */
    public function doGitCheckoutFile($branch, $file)
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
     *
     * @param string $branch
     *   Branch name to checkout.
     */
    public function doRunGitCheckout($branch)
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
     *
     * @param string $branch
     *   Name of the new branch.
     */
    public function doGitBranchCreate($branch)
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
    public function doRunGitRebase($upstream, $branch = null)
    {
        $cmd_pattern = '%s rebase %s';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($upstream),
        ];

        if ($branch) {
            $cmd_pattern .= ' %s';
            $cmd_args[] = escapeshellarg($branch);
        }

        $this->process = $this->doExec(
            vsprintf($cmd_pattern, $cmd_args),
            [
                'exitCode' => false,
            ]
        );
    }

    /**
     * @Given I run git merge :branch -m :message
     *
     * @param string $branch
     * @param string $message
     */
    public function doGitMerge($branch, $message)
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
     *
     * @param string $branch
     * @param string $message
     */
    public function doGitMergeSquash($branch, $message)
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
     *
     * @param string $editor
     */
    public function doGitConfigSetCoreEditor($editor)
    {
        $this->doGitConfigSet('core.editor', $editor);
    }

    /**
     * @Given /^I wait for (?P<amount>\d+) seconds$/
     *
     * @param int $amount
     */
    public function doWait($amount)
    {
        sleep(intval($amount));
    }

    /**
     * @Then /^the exit code should be (?P<exit_code>\d+)$/
     *
     * @param int $exit_code
     */
    public function assertExitCodeEquals($exit_code)
    {
        $this->assertEquals(
            $exit_code,
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
        $output = explode("\n", $this->process->getOutput());
        for ($i = 0; $i < count($output); $i++) {
            $output[$i] = rtrim($output[$i]);
        }

        $this->assertContains($string->getRaw(), implode("\n", $output));
    }

    /**
     * @Then /^the stdErr should contains the following text:$/
     *
     * @param \Behat\Gherkin\Node\PyStringNode $string
     */
    public function assertStdErrContains(PyStringNode $string)
    {
        $output = explode("\n", $this->process->getErrorOutput());
        for ($i = 0; $i < count($output); $i++) {
            $output[$i] = rtrim($output[$i]);
        }

        $this->assertContains($string->getRaw(), implode("\n", $output));
    }

    /**
     * @Given /^the number of commits is (?P<expected>\d+)$/
     *
     * @param int $expected
     */
    public function assertGitLogLength($expected)
    {
        $cmd_pattern = '%s log --format=%s | cat';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg('%h'),
        ];
        $git_log = $this->doExec(
            vsprintf($cmd_pattern, $cmd_args),
            [
                'exitCode' => false,
            ]
        );

        $this->assertEquals(
            $expected,
            substr_count($git_log->getOutput(), "\n")
        );
    }

    /**
     * @Given the git log is not empty
     */
    public function assertGitLogIsNotEmpty()
    {
        $cmd_pattern = '%s log -1';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
        ];
        $git_log = $this->doExec(vsprintf($cmd_pattern, $cmd_args));
        $this->assertNotEquals('', $git_log->getOutput());
    }

    /**
     * @Given the git log is empty
     */
    public function assertGitLogIsEmpty()
    {
        $cmd_pattern = '%s log -1';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
        ];
        $git_log = $this->doExec(vsprintf($cmd_pattern, $cmd_args));
        $this->assertEquals('', $git_log->getOutput());
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getWorkspacePath($path)
    {
        $normalized_path = static::normalizePath("{$this->cwd}/$path");
        $this->validateWorkspacePath($normalized_path);

        return $normalized_path;
    }

    /**
     * @param string $normalized_path
     */
    protected function validateWorkspacePath($normalized_path)
    {
        if (strpos($normalized_path, "{$this->scenarioRootDir}/workspace")
            !== 0
        ) {
            throw new \InvalidArgumentException('Out of working directory.');
        }
    }

    /**
     * @param string $type
     */
    protected function doCreateProjectCache($type)
    {
        $project_cache_dir = $this->getProjectCacheDir($type);
        if (static::$fs->exists($project_cache_dir)) {
            return;
        }

        $project_template_dir = static::$projectRootDir . '/'
            . static::$fixturesDir . "/project-template/$type";
        if (!static::$fs->exists($project_template_dir)) {
            throw new \InvalidArgumentException("Project template '$type' doesn't exists.");
        }

        if (!static::$fs->exists("{$this->scenarioRootDir}/project/$type")) {
            static::$fs->mirror($project_template_dir, $project_cache_dir);
        }

        $this->doExecCwd($project_cache_dir, 'composer install');
    }

    /**
     * I initialize a Git repo.
     *
     * @param string $dir
     * @param bool $bare
     */
    protected function doGitInit($dir, $bare)
    {
        $this->doChangeWorkingDirectory($dir);
        $cmd_pattern = '%s init --template=%s';
        $cmd_args = [
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg(static::getGitTemplateDir()),
        ];

        if ($bare) {
            $cmd_pattern .= ' --bare';
            $git_dir = '';
        } else {
            $git_dir = '.git/';
        }

        $cmd = vsprintf($cmd_pattern, $cmd_args);

        $git_init = $this->doExec($cmd);
        $cwd_real = realpath($this->cwd);
        $this->assertEquals(
            "Initialized empty Git repository in $cwd_real/$git_dir\n",
            $git_init->getOutput()
        );
    }

    /**
     * @param string $name
     * @param string $value
     */
    protected function doGitConfigSet($name, $value)
    {
        $cmd = sprintf(
            '%s config %s %s',
            escapeshellcmd(static::$gitExecutable),
            escapeshellarg($name),
            escapeshellarg($value)
        );
        $this->process = $this->doExec($cmd);
    }

    /**
     * @param string $wd
     * @param string $cmd
     * @param bool[] $check
     *
     * @return \Symfony\Component\Process\Process
     */
    protected function doExecCwd($wd, $cmd, $check = [])
    {
        $cwd_backup = $this->cwd;
        chdir($wd);
        $return = $this->doExec($cmd, $check);
        $this->cwd = $cwd_backup;

        return $return;
    }

    /**
     * @param string $cmd
     * @param bool[] $check
     *
     * @return \Symfony\Component\Process\Process
     */
    protected function doExec($cmd, $check = [])
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
            $this->assertEquals($check['stdErr'], $process->getErrorOutput());
        }

        return $process;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function getProjectCacheDir($type)
    {
        return "{$this->scenarioRootDir}/project-cache/$type";
    }
}
