<?php

namespace Cheppers\Robo\Task\GitHooksRobo;

use Robo\Common\Timer;
use Robo\Config;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Task\Filesystem\loadShortcuts;

/**
 * Class TaskPhpcsLint.
 *
 * @package Cheppers\Robo\Task\Phpcs
 */
class DeployTask extends BaseTask {

  use Timer;
  use loadShortcuts;

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

    $git_dir = rtrim(`git rev-parse --git-dir`, "\n");

    $result = NULL;
    $this->startTimer();
    foreach ($this->fileNames as $file_name) {
      $r = $this->_copy("./$file_name", "$git_dir/hooks/$file_name");
      if (!$r->wasSuccessful()) {
        $result = $r;

        break;
      }
    }
    $this->stopTimer();

    if (!$result) {
      $result = Result::success($this, 'Git hooks in the house', ['time' => $this->getExecutionTime()]);
    }

    return $result;

  }

}
