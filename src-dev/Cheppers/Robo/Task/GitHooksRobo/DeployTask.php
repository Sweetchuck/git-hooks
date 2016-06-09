<?php

namespace Cheppers\Robo\Task\GitHooksRobo;

use Robo\Common\Timer;
use Robo\Config;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Task\Base\loadTasks as BaseLoadTasks;
use Symfony\Component\Process\Process;

/**
 * Class TaskPhpcsLint.
 *
 * @package Cheppers\Robo\Task\Phpcs
 */
class DeployTask extends BaseTask {

  use Timer;
  use BaseLoadTasks;

  /**
   * @var string[]
   */
  protected $fileNames = [
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

  /**
   * {@inheritdoc}
   */
  public function getContainer() {
    return Config::getContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    $this->printTaskInfo('Deploying Git hooks');

    $current_dir = realpath(getcwd());
    $repo_type = $this->getGitRepoType();
    if ($repo_type === NULL) {
      return Result::cancelled('This directory is not tracked by Git', ['directory' => $current_dir]);
    }

    $git_dir = $this->getGitDir();
    if (!($repo_type === 'bare' && strpos($current_dir, $git_dir) === 0)
      && !($repo_type === 'not-bare' && file_exists("$current_dir/.git"))
    ) {
      return Result::cancelled('Git directory cannot be detected 100%.');
    }

    /** @var \Robo\Task\Filesystem\FilesystemStack $fsStack */
    $fsStack = $this->getContainer()->get('taskFilesystemStack');
    $git_dir = preg_replace('@^' . preg_quote("$current_dir/", '@') . '@', './', $git_dir);
    foreach ($this->fileNames as $file_name) {
      $fsStack->copy("./$file_name", "$git_dir/hooks/$file_name");
    }

    return $fsStack->run();
  }

  /**
   * @return string|null
   */
  protected function getGitRepoType() {
    $process = new Process('git rev-parse --is-bare-repository');
    $exit_code = $process->run();
    if ($exit_code) {
      return NULL;
    }

    return trim($process->getOutput()) === 'true' ? 'bare' : 'not-bare';
  }

  /**
   * @return string|null
   */
  protected function getGitDir() {
    $process = new Process('git rev-parse --git-dir');
    $exit_code = $process->run();
    if ($exit_code !== 0) {
      return NULL;
    }

    return realpath(rtrim($process->getOutput(), "\n"));
  }

}
