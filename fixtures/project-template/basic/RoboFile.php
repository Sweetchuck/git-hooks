<?php
/**
 * @file
 * Test helper Robo task definitions.
 */

use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Tasks;

/**
 * Class RoboFile.
 */
// @codingStandardsIgnoreStart
class RoboFile extends Tasks
    // @codingStandardsIgnoreEnd
{
    use \PredestinedLoadTasks;

    public function githookPreCommit()
    {
        $this->say(__METHOD__ . ' is called');

        $output = [];
        $exit_code = null;
        exec('git diff --cached --name-only', $output, $exit_code);
        $this->stopOnFail(true);
        $this
            ->taskPredestined(!$exit_code && !in_array('false.txt', $output))
            ->run();
    }

    public function githookPostCommit()
    {
        $this->say(__METHOD__ . ' is called');
    }

    /**
     * @param string $base_branch
     * @param string|null $subject_branch
     */
    public function githookPreRebase($base_branch, $subject_branch = null)
    {
        $current_branch = $this->gitCurrentBranch();
        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Current branch: "%s"', $current_branch));
        $this->say(sprintf('Upstream: "%s"', $base_branch));
        $this->say(sprintf('Subject branch: "%s"', $subject_branch));

        if (!$subject_branch) {
            $subject_branch = $current_branch;
        }

        $this->stopOnFail(true);
        $this
            ->taskPredestined(($subject_branch !== 'protected'))
            ->run();
    }

    /**
     * @param string $remote_name
     * @param string $remote_uri
     */
    public function githookPrePush($remote_name, $remote_uri)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("Remote name: $remote_name");
        $this->say("Remote URI: $remote_uri");

        $git_log_pattern = 'git log --format=%s -n 1 %s';
        $valid = true;
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
                    ->printed(false)
                    ->run()
                    ->getMessage();

                $valid = (trim($commit_message) != 'Invalid');
            }
        }

        $this->say("Lines in stdInput: $num_of_lines");

        $this->stopOnFail(true);
        $this
            ->taskPredestined($valid)
            ->run();
    }

    /**
     * @param string $is_squash
     */
    public function githookPostMerge($is_squash)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("Squash: $is_squash");
    }

    /**
     * @param string $ref
     *
     * @return bool
     */
    protected function gitRefIsBranch($ref)
    {
        return strpos($ref, 'refs/heads/') === 0;
    }

    /**
     * @return string
     */
    protected function gitCurrentBranch()
    {
        $result = $this
            ->taskExec('git rev-parse --abbrev-ref HEAD')
            ->printed(false)
            ->run();

        return trim($result->getMessage());
    }
}

/**
 * Class PredestinedLoadTasks.
 */
// @codingStandardsIgnoreStart
trait PredestinedLoadTasks
    // @codingStandardsIgnoreEnd
{

    /**
     * @param bool $outcome
     *
     * @return \PredestinedTask
     */
    public function taskPredestined($outcome)
    {
        return new PredestinedTask($outcome);
    }
}

/**
 * Class PredestinedTask.
 */
// @codingStandardsIgnoreStart
class PredestinedTask extends BaseTask
    // @codingStandardsIgnoreEnd
{

    /**
     * @var bool
     */
    protected $outcome = true;

    /**
     * PredestinedTask constructor.
     *
     * @param bool $outcome
     */
    public function __construct($outcome)
    {
        $this->outcome = $outcome;
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        return $this->outcome ?
            Result::success($this, 'True as expected')
            : Result::error($this, 'False as expected');
    }
}
