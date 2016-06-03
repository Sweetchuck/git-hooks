<?php
/**
 * @file
 * Robo task definitions for cheppers/git-hooks-robo.
 */

use Cheppers\Robo\Task\GitHooksRobo\LoadTasks as GHRLoadTasks;
use Robo\Config;
use Robo\Tasks;

/**
 * Class RoboFile.
 */
class RoboFile extends Tasks {

  use GHRLoadTasks;

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    // @todo Make sure this is the right way to add services.
    /** @var \League\Container\ContainerInterface $c */
    $c = Config::getContainer();
    $c->addServiceProvider(GHRLoadTasks::getGitHooksRoboServices());
  }

  public function deployGitHooks() {
    $this
      ->taskGitHooksRoboDeploy()
      ->run();
  }

  public function test() {
    $this->composerValidate();
    $this->behat();
  }

  public function behat() {
    $this->stopOnFail(TRUE);
    $this
      ->taskExec('./vendor/bin/behat --colors --strict')
      ->run();
  }

  public function composerValidate() {
    $this->stopOnFail(TRUE);
    $this
      ->taskExec('composer validate')
      ->run();
  }
  
  public function githooksPreCommit() {
    $this->composerValidate();
  }

}
