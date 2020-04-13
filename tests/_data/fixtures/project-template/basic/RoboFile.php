<?php

use Robo\Contract\TaskInterface;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Tasks;

class RoboFile extends Tasks
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

    public function githookPreCommit(): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');

        $output = [];
        $exitCode = null;
        exec('git diff --cached --name-only', $output, $exitCode);

        return $this->taskPredestined(!$exitCode && !in_array('false.txt', $output));
    }

    public function githookPostCommit()
    {
        $this->say(__METHOD__ . ' is called');
    }

    public function githookPostCheckout(string $refOld, string $refNew, string $isBranch)
    {
        $pattern = '/^[a-z0-9]{40}$/i';
        $refOldLabel = (preg_match($pattern, $refOld) ? 'OLD_REF' : $refOld);
        $refNewLabel = (preg_match($pattern, $refNew) ? 'NEW_REF' : $refNew);
        $isBranchLabel = ($isBranch ? 'yes' : 'no');

        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Old ref: "%s"', $refOldLabel));
        $this->say(sprintf('New ref: "%s"', $refNewLabel));
        $this->say(sprintf('Branch checkout: "%s"', $isBranchLabel));
    }

    public function githookPreRebase(string $baseBranch, ?string $subjectBranch = null): TaskInterface
    {
        $currentBranch = $this->gitCurrentBranch();
        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Current branch: "%s"', $currentBranch));
        $this->say(sprintf('Upstream: "%s"', $baseBranch));
        $this->say(sprintf('Subject branch: "%s"', $subjectBranch));

        if (!$subjectBranch) {
            $subjectBranch = $currentBranch;
        }

        return $this->taskPredestined(($subjectBranch !== 'protected'));
    }

    public function githookPostRewrite(string $trigger): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');
        $this->say(sprintf('Trigger: "%s"', $trigger));

        $pattern = '/^[a-z0-9]{40}$/i';
        $numOfLines = 0;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $numOfLines++;
            if (!$line) {
                continue;
            }

            $keys = [
                'old_rev',
                'new_rev',
                'extra',
            ];
            $input = array_combine($keys, explode(' ', $line) + [2 => null]);

            $oldRevLabel = (preg_match($pattern, $input['old_rev']) ? 'OLD_REV' : $input['old_rev']);
            $newRevLabel = (preg_match($pattern, $input['new_rev']) ? 'NEW_REV' : $input['new_rev']);

            $this->say(sprintf(
                'stdInput line %d: "%s" "%s" "%s"',
                $numOfLines,
                $oldRevLabel,
                $newRevLabel,
                $input['extra']
            ));
        }
        $this->say(sprintf('Lines in stdInput: "%d"', $numOfLines));

        return $this->taskPredestined(true);
    }

    public function githookPrePush(string $remote_name, string $remote_uri): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("Remote name: $remote_name");
        $this->say("Remote URI: $remote_uri");

        $gitLogPattern = 'git log --format=%s -n 1 %s';
        $valid = true;
        $numOfLines = 0;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $numOfLines++;
            if (!$line) {
                continue;
            }

            list($localRef, $localSha) = explode(' ', $line);
            if ($valid && $this->gitRefIsBranch($localRef)) {
                $cmd = sprintf(
                    $gitLogPattern,
                    escapeshellarg('%B'),
                    escapeshellarg($localSha)
                );

                $commitMessage = $this
                    ->taskExec($cmd)
                    ->printOutput(false)
                    ->run()
                    ->getMessage();

                $valid = !preg_match('/^Invalid pre-push(\n|$)/', trim($commitMessage));
            }
        }

        $this->say("Lines in stdInput: $numOfLines");

        return $this->taskPredestined($valid);
    }

    public function githookPreReceive(): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');

        $numOfLines = 0;
        $valid = true;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $numOfLines++;
            if (!$line) {
                continue;
            }

            list(, , $ref_name) = explode(' ', $line);

            if ($valid) {
                $valid = !preg_match('@^refs/heads/invalid-pre-receive$@', $ref_name);
            }

            $this->say("Ref: '$ref_name'");
        }
        $this->say("Lines in stdInput: '$numOfLines'");

        return $this->taskPredestined($valid);
    }

    public function githookPostReceive(): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');

        $pattern = '/^[a-z0-9]{40}$/i';
        $numOfLines = 0;
        while ($line = fgets(STDIN)) {
            $line = rtrim($line, "\n");

            $numOfLines++;
            if (!$line) {
                continue;
            }

            list($oldRev, $newRev, $refName) = explode(' ', $line);
            $oldRevLabel = (preg_match($pattern, $oldRev) ? 'OLD_REV' : $oldRev);
            $newRevLabel = (preg_match($pattern, $newRev) ? 'NEW_REV' : $newRev);

            $this->say(sprintf(
                'stdInput line %d: "%s" "%s" "%s"',
                $numOfLines,
                $oldRevLabel,
                $newRevLabel,
                $refName
            ));
        }
        $this->say(sprintf('Lines in stdInput: "%d"', $numOfLines));

        return $this->taskPredestined(true);
    }

    public function githookPostMerge(string $isSquash)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("Squash: $isSquash");
    }

    /**
     * @param string $fileName
     *   The name of the file that has the commit message.
     * @param string $description
     *   The description of the commit message's source.
     */
    public function githookPrepareCommitMsg(string $fileName, ?string $description = null)
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("File name: '$fileName'");
        $this->say("Description: '$description'");

        if (!$description) {
            $fh = fopen($fileName, 'a');
            fwrite($fh, "This line added by the 'prepare-commit-msg' callback\n\n");
            fclose($fh);
        }
    }

    /**
     * @param string $fileName
     *   The name of the file that has the commit message.
     */
    public function githookCommitMsg(string $fileName): TaskInterface
    {
        $this->say(__METHOD__ . ' is called');
        $this->say("File name: '$fileName'");

        $fh = fopen($fileName, 'a');
        fwrite($fh, "This line added by the 'commit-msg' callback\n\n");
        fclose($fh);

        $msg = file_get_contents($fileName);

        return $this->taskPredestined(!preg_match('/^Invalid commit-msg(\n|$)/', $msg));
    }

    protected function gitRefIsBranch(string $ref): bool
    {
        return strpos($ref, 'refs/heads/') === 0;
    }

    protected function gitCurrentBranch(): string
    {
        $result = $this
            ->taskExec('git rev-parse --abbrev-ref HEAD')
            ->printOutput(false)
            ->run();

        return trim($result->getMessage());
    }

    /**
     * {@inheritdoc}
     */
    protected function say($text)
    {
        $this->output()->writeln("{$this->sayPrefix}$text");
    }

    /**
     * {@inheritdoc}
     */
    protected function yell($text, $length = 40, $color = 'green')
    {
        $format = "%s<fg=white;bg=$color;options=bold> %s </fg=white;bg=$color;options=bold>";
        $delimiter = sprintf($format, $this->yellPrefix, str_repeat(' ', $length));

        $this->writeln($delimiter);
        $this->writeln(sprintf($format, $this->yellPrefix, str_pad($text, $length, ' ', STR_PAD_BOTH)));
        $this->writeln($delimiter);
    }
}

trait PredestinedLoadTasks
{

    /**
     * @return \PredestinedTask
     */
    public function taskPredestined(bool $outcome)
    {
        return new PredestinedTask($outcome);
    }
}

class PredestinedTask extends BaseTask
{

    /**
     * @var bool
     */
    protected $outcome = true;

    public function __construct(bool $outcome)
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
