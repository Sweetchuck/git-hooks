<?php
/**
 * @file
 * Robo task definitions for cheppers/git-hooks-robo.
 */

use Robo\Tasks;
use Symfony\Component\Process\Process;

/**
 * Class RoboFile.
 */
// @codingStandardsIgnoreStart
class RoboFile extends Tasks
    // @codingStandardsIgnoreEnd
{

    /**
     * The "bin-dir" configured in composer.json.
     *
     * @todo This could be dynamic with `composer config bin-dir`.
     *
     * @var string
     */
    protected $binDir = 'vendor/bin';

    /**
     * @var string
     */
    protected $gitExecutable = 'git';

    /**
     * @var string[]
     */
    protected $filesToDeploy = [
        '_common',
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

    public function deployGitHooks()
    {
        $this->stopOnFail(true);
        $task = $this->getTaskDeployGitHooks();
        if ($task) {
            $task->run();
        }
    }

    public function test()
    {
        $this->composerValidate();
        $this->behat();
    }

    public function behat()
    {
        $this->stopOnFail(true);

        $this
            ->getTaskBehatRun()
            ->run();
    }

    public function composerValidate()
    {
        $this->stopOnFail(true);
        $this
            ->getTaskComposerValidate()
            ->run();
    }

    public function lint()
    {
        $this->stopOnFail(true);

        /** @var \Robo\Collection\Collection $c */
        $c = $this->collection();
        $c
            ->add($this->getTaskPhpcsLint())
            ->add($this->getTaskComposerValidate())
            ->run();
    }

    public function lintPhpcs()
    {
        $this
            ->getTaskPhpcsLint()
            ->run();
    }

    public function githookPreCommit()
    {
        $this->lint();
    }

    /**
     * @return \Robo\Task\Base\Exec
     */
    protected function getTaskPhpcsLint()
    {
        $cmd_pattern = '%s --standard=%s --ignore=%s %s %s %s';
        $cmd_args = [
            escapeshellcmd("{$this->binDir}/phpcs"),
            escapeshellarg('PSR2'),
            escapeshellarg('fixtures/project-template/*/vendor/'),
            escapeshellarg('features/bootstrap/'),
            escapeshellarg('fixtures/project-template/'),
            escapeshellarg('RoboFile.php'),
        ];

        return $this->taskExec(vsprintf($cmd_pattern, $cmd_args));
    }

    /**
     * @return \Robo\Task\Base\Exec
     */
    protected function getTaskComposerValidate()
    {
        return $this->taskExec('composer validate');
    }

    /**
     * @return \Robo\Task\Filesystem\FilesystemStack|null
     */
    protected function getTaskDeployGitHooks()
    {
        $current_dir = realpath(getcwd());
        $repo_type = $this->getGitRepoType();
        if ($repo_type === null) {
            // This directory is not tracked by Git.
            return null;
        }

        $git_dir = $this->getGitDir();
        if (!($repo_type === 'bare' && strpos($current_dir, $git_dir) === 0)
            && !($repo_type === 'not-bare' && file_exists("$current_dir/.git"))
        ) {
            // Git directory cannot be detected 100%.
            return null;
        }

        /** @var \League\Container\Container $container */
        $container = $this->getContainer();

        /** @var \Robo\Task\Filesystem\FilesystemStack $fsStack */
        $fsStack = $container->get('taskFilesystemStack');

        $git_dir = preg_replace('@^' . preg_quote("$current_dir/", '@') . '@', './', $git_dir);
        foreach ($this->filesToDeploy as $file_name) {
            $fsStack->copy("./$file_name", "$git_dir/hooks/$file_name");
        }

        return $fsStack;
    }

    /**
     * @return \Robo\Task\Base\Exec
     */
    protected function getTaskBehatRun()
    {
        $cmd = sprintf(
            '%s --colors --strict',
            escapeshellcmd("{$this->binDir}/behat")
        );

        return  $this->taskExec($cmd);
    }

    /**
     * @return string|null
     */
    protected function getGitRepoType()
    {
        $cmd = sprintf(
            '%s rev-parse --is-bare-repository',
            escapeshellcmd($this->gitExecutable)
        );

        $process = new Process($cmd);
        $exit_code = $process->run();
        if ($exit_code) {
            return null;
        }

        return trim($process->getOutput()) === 'true' ? 'bare' : 'not-bare';
    }

    /**
     * @return string|null
     */
    protected function getGitDir()
    {
        $cmd = sprintf(
            '%s rev-parse --git-dir',
            escapeshellcmd($this->gitExecutable)
        );

        $process = new Process($cmd);
        $exit_code = $process->run();
        if ($exit_code !== 0) {
            return null;
        }

        return realpath(rtrim($process->getOutput(), "\n"));
    }
}
