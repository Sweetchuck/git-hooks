<?php

namespace Cheppers\Robo\Task\GitHooksRobo;

use Robo\Container\SimpleServiceProvider;

/**
 * Class loadTasks.
 */
trait LoadTasks {

  /**
   * Return services.
   */
  public static function getGitHooksRoboServices() {
    return new SimpleServiceProvider([
      'taskGitHooksRoboDeploy' => DeployTask::class,
    ]);
  }

  /**
   * @return \Cheppers\Robo\Task\GitHooksRobo\DeployTask
   */
  protected function taskGitHooksRoboDeploy() {
    return $this->task(__FUNCTION__);
  }

}
