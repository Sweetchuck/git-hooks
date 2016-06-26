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

    /**
     * @var string
     */
    protected $sayPrefix = '>  ';

    /**
     * @var string
     */
    protected $yellPrefix = '>  ';

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
     * @param string $ref_old
     * @param string $ref_new
     * @param string $is_branch
     */
    public function githookPostCheckout($ref_old, $ref_new, $is_branch)
    {
        $pattern = '/^[a-z0-9]{40}$/i';
        $ref_old_label = (preg_match($pattern, $ref_old) ? 'OLD_REF' : $ref_old);
        $ref_new_label = (preg_match($pattern, $ref_new) ? 'NEW_REF' : $ref_new);
        $is_branch_label = ($is_branch ? 'yes' : 'no');

        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Old ref: "%s"', $ref_old_label));
        $this->say(sprintf('New ref: "%s"', $ref_new_label));
        $this->say(sprintf('Branch checkout: "%s"', $is_branch_label));
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
     * @param string $trigger
     */
    public function githookPostRewrite($trigger)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Trigger: "%s"', $trigger));

        $pattern = '/^[a-z0-9]{40}$/i';
        $num_of_lines = 0;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $num_of_lines++;
            if (!$line) {
                continue;
            }

            $keys = [
                'old_rev',
                'new_rev',
                'extra',
            ];
            $input = array_combine($keys, explode(' ', $line) + [2 => null]);

            $old_rev_label = (preg_match($pattern, $input['old_rev']) ? 'OLD_REV' : $input['old_rev']);
            $new_rev_label = (preg_match($pattern, $input['new_rev']) ? 'NEW_REV' : $input['new_rev']);

            $this->say(sprintf(
                'stdInput line %d: "%s" "%s" "%s"',
                $num_of_lines,
                $old_rev_label,
                $new_rev_label,
                $input['extra']
            ));
        }
        $this->say(sprintf('Lines in stdInput: "%d"', $num_of_lines));

        $this->stopOnFail(true);
        $this
            ->taskPredestined(true)
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
            $line = rtrim($line, "\n");

            $num_of_lines++;
            if (!$line) {
                continue;
            }

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

                $valid = !preg_match('/^Invalid pre-push(\n|$)/', trim($commit_message));
            }
        }

        $this->say("Lines in stdInput: $num_of_lines");

        $this->stopOnFail(true);
        $this
            ->taskPredestined($valid)
            ->run();
    }

    public function githookPreReceive()
    {
        $this->say(__METHOD__ . ' is called');

        $num_of_lines = 0;
        $valid = true;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $num_of_lines++;
            if (!$line) {
                continue;
            }

            list(, , $ref_name) = explode(' ', $line);

            if ($valid) {
                $valid = !preg_match('@^refs/heads/invalid-pre-receive$@', $ref_name);
            }

            $this->say("Ref: '$ref_name'");
        }
        $this->say("Lines in stdInput: '$num_of_lines'");

        $this->stopOnFail(true);
        $this
            ->taskPredestined($valid)
            ->run();
    }

    public function githookPostReceive()
    {
        $this->say(__METHOD__ . ' is called');

        $pattern = '/^[a-z0-9]{40}$/i';
        $num_of_lines = 0;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $num_of_lines++;
            if (!$line) {
                continue;
            }

            list($old_rev, $new_rev, $ref_name) = explode(' ', $line);
            $old_rev_label = (preg_match($pattern, $old_rev) ? 'OLD_REV' : $old_rev);
            $new_rev_label = (preg_match($pattern, $new_rev) ? 'NEW_REV' : $new_rev);

            $this->say(sprintf(
                'stdInput line %d: "%s" "%s" "%s"',
                $num_of_lines,
                $old_rev_label,
                $new_rev_label,
                $ref_name
            ));
        }
        $this->say(sprintf('Lines in stdInput: "%d"', $num_of_lines));

        $this->stopOnFail(true);
        $this
            ->taskPredestined(true)
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
     * @param string $file_name
     *   The name of the file that has the commit message.
     * @param string $description
     *   The description of the commit message's source.
     */
    public function githookPrepareCommitMsg($file_name, $description = null)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("File name: '$file_name'");
        $this->say("Description: '$description'");

        if (!$description) {
            $fh = fopen($file_name, 'a');
            fwrite($fh, "This line added by the 'prepare-commit-msg' callback\n\n");
            fclose($fh);
        }
    }

    /**
     * @param string $file_name
     *   The name of the file that has the commit message.
     */
    public function githookCommitMsg($file_name)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("File name: '$file_name'");

        $fh = fopen($file_name, 'a');
        fwrite($fh, "This line added by the 'commit-msg' callback\n\n");
        fclose($fh);

        $msg = file_get_contents($file_name);
        $this->stopOnFail(true);
        $this
            ->taskPredestined(!preg_match('/^Invalid commit-msg(\n|$)/', $msg))
            ->run();
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

    /**
     * {@inheritdoc}
     */
    protected function say($text)
    {
        $this->getOutput()->writeln("{$this->sayPrefix}$text");
    }

    /**
     * {@inheritdoc}
     */
    protected function yell($text, $length = 40, $color = 'green')
    {
        $format = "%s<fg=white;bg=$color;options=bold> %s </fg=white;bg=$color;options=bold>";
        $delimiter = sprintf($format, $this->yellPrefix, str_repeat(' ', $length));
        $o = $this->getOutput();

        $o->writeln($delimiter);
        $o->writeln(sprintf($format, $this->yellPrefix, str_pad($text, $length, ' ', STR_PAD_BOTH)));
        $o->writeln($delimiter);
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
