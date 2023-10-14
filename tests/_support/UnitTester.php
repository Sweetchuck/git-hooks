<?php

namespace Sweetchuck\GitHooks\Tests;

use Codeception\Actor;
use DirectoryIterator;

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
*/
class UnitTester extends Actor
{
    use _generated\UnitTesterActions;

    public function assertSymlink(string $expected, string $file)
    {
        $this->assertFileExists($file);

        $this->assertSame(
            'link',
            filetype($file),
            "$file is a symbolic link"
        );

        $this->assertSame(
            $expected,
            readlink($file),
            "symbolic link $file points to $expected"
        );
    }

    public function assertDirContainsAllTheFiles(string $expected, string $actual)
    {
        $file = new DirectoryIterator($expected);
        while ($file->valid()) {
            if ($file->isDot() || $file->isDir()) {
                $file->next();
                continue;
            }

            $actualFile = "$actual/" . $file->getFilename();
            $this->assertFileExists($actualFile);
            $this->assertSame(
                $file->getPerms(),
                fileperms($actualFile),
                "file permissions of $actualFile"
            );

            $this->assertSame(
                md5_file($file->getPathname()),
                md5_file($actualFile),
                "content of $actualFile file"
            );

            $file->next();
        }
    }

    public function assertLogEntries(array $expected, array $actual, array $replacementPairs = [], string $message = '')
    {
        if ($message === '') {
            $message = 'log entries';
        }

        $this->assertCount(
            count($expected),
            $actual,
            "$message - number of log entries"
        );

        foreach ($expected as $i => $expectedLogEntry) {
            $expectedLogEntry['message'] = strtr($expectedLogEntry['message'], $replacementPairs);
            $this->assertSame(
                $expectedLogEntry,
                $actual[$i],
                "$message - log entry $i"
            );
        }
    }
}
