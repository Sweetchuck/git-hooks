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

  public function githookPostCommit() {
    $this->say(__METHOD__ . ' is called');
  }

  /**
   * @param string $remote_name
   * @param string $remote_uri
   */
  public function githookPrePush($remote_name, $remote_uri) {
    $this->say(__METHOD__ . ' is called');
    $this->say("Remote name: $remote_name");
    $this->say("Remote URI: $remote_uri");

    $git_log_pattern = 'git log --format=%s -n 1 %s';
    $valid = TRUE;
    $num_of_lines = 0;
    while ($line = fgets(STDIN)) {
      $num_of_lines++;
      list($local_ref, $local_sha) = explode(' ', $line);
      if ($valid && $this->gitRefIsBranch($local_ref)) {
        $cmd = sprintf(
          $git_log_pattern,
          escapeshellarg('%B'),
          escapeshellarg($local_sha)
        );

        $commit_message = $this
          ->taskExec($cmd)
          ->printed(FALSE)
          ->run()
          ->getMessage();

        $valid = (trim($commit_message) != 'Invalid');
      }
    }

    $this->say("Lines in stdInput: $num_of_lines");

    $this->stopOnFail(TRUE);
    $this
      ->taskPredestined($valid)
      ->run();
  }

  /**
   * @param string $is_squash
   */
  public function githookPostMerge($is_squash) {
    $this->say(__METHOD__ . ' is called');
    $this->say("Squash: $is_squash");
  }

  /**
   * @param string $ref
   *
   * @return bool
   */
  protected function gitRefIsBranch($ref) {
    return strpos($ref, 'refs/heads/') === 0;
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
