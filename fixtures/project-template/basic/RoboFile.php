<?php
/**
 * @file
 * Test helper Robo task definitions.
 */

use Robo\Result;
use Robo\Task\BaseTask;

/**
 * Class RoboFile.
 */
class RoboFile extends Robo\Tasks {

  use \PredestinedLoadTasks;

  public function githookPreCommit() {
    $this->say(__METHOD__ . ' is called');

    $output = [];
    $exit_code = NULL;
    exec('git diff --cached --name-only', $output, $exit_code);
    $this->stopOnFail(TRUE);
    $this
      ->taskPredestined(!$exit_code && !in_array('false.txt', $output))
      ->run();
  }

}

/**
 * Class PredestinedLoadTasks.
 */
trait PredestinedLoadTasks {

  /**
   * @param bool $outcome
   *
   * @return \PredestinedTask
   */
  public function taskPredestined($outcome) {
    return new PredestinedTask($outcome);
  }

}

/**
 * Class PredestinedTask.
 */
class PredestinedTask extends BaseTask {

  /**
   * @var bool
   */
  protected $outcome = TRUE;

  /**
   * PredestinedTask constructor.
   *
   * @param bool $outcome
   */
  public function __construct($outcome) {
    $this->outcome = $outcome;
  }

  /**
   * {@inheritdoc}
   */
  public function run() {
    return $this->outcome ?
      Result::success($this, 'True as expected')
      : Result::error($this, 'False as expected');
  }

}
