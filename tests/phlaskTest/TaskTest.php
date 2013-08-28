<?php

use phlask\TaskSpec\PhpRunnable;
use phlask\TaskSpec\ShellRunnable;
use phlask\Task;

class TaskTest extends PHPUnit_Framework_TestCase
{
    protected static $simpleScriptFixture;
    protected static $phpExec;
    protected static $fixturesDir;

    public static function setUpBeforeClass()
    {
        $fixtures = $script = dirname(dirname(__FILE__)) . '/fixtures';
        self::$fixturesDir = $fixtures;
        self::$simpleScriptFixture = "$fixtures/SimplePhpScript.php";

        self::$phpExec = exec('which php');
    }

    public function phpFilesToExecute()
    {
        $f = dirname(dirname(__FILE__)) . '/fixtures';
        return [
            ["$f/SimpleFatalError.php", 255],
            ["$f/SimplePhpScript.php", 200]
        ];
    }

    public function shellCommandsToExecute()
    {
        return [
            ['sleep 1 && ls', '/', 'list'],
            ['sleep 1 && echo `pwd`', '/', 'blah']
        ];
    }

    /**
     * @dataProvider phpFilesToExecute
     * @cover Task::factory, Task::run, Task::statusCheck, Task::getExitCode, Task::getStatus
     */
    public function testSimpleRun($file, $exitCodeExpect)
    {
        $task = Task::factory(PhpRunnable::factory([
            'file' => $file,
            'php' => self::$phpExec
        ]));

        $task->run();
        $validPid = $task->getPid() > 1024 && $task->getPid() < 65535;
        $this->assertTrue($validPid, "The PID was expected to be > 1024. Actually: " . var_export($task->getPid(), true));
        $this->assertSame(Task::STATUS_RUNNING, $task->getStatus());

        //wait until it's done
        $start = time();
        while ($task->getStatus() != Task::STATUS_COMPLETE) {
            usleep(10000);
            $task->statusCheck();
            if (time() - $start > 5) {
                $this->fail("We polled the task for 5 seconds and yet it's still running. Perhaps it hung?");
            }
        }

        $task->statusCheck();
        $this->assertSame($exitCodeExpect, $task->getExitCode());

        //verify that re-calling statusCheck doesn't lose our exit code
        $task->statusCheck();
        $this->assertSame($exitCodeExpect, $task->getExitCode());
        $this->assertSame(Task::STATUS_COMPLETE, $task->getStatus(), "Status should be Task::STATUS_COMPLETE");
        
        $validRuntime = $task->getRuntime() > 0.0001;
        $this->assertTrue($validRuntime, 'The runtime is expected to be a positive float. Got:' . $task->getRuntime());
    }

    /**
     * @dataProvider phpFilesToExecute
     * @cover Task::factory, Task::run, Task::statusCheck, Task::getExitCode, Task::getStatus
     */
    public function testSimpleTermination($file, $exitCodeExpect)
    {
        $task = Task::factory(PhpRunnable::factory([
            'file' => $file,
            'php' => self::$phpExec
        ]));

        $task->run();
        $task->terminate();
        $task->statusCheck();

        //wait until it's done
        $start = time();
        while ($task->getStatus() != Task::STATUS_SIGNALED) {
            usleep(10000);
            $task->statusCheck();
            if (time() - $start > 5) {
                $this->fail(
                    "We polled the task for 5 seconds and yet it's still running. Perhaps it hung? Status:"
                    . $task->getStatus()
                );
            }
        }

        $this->assertSame(Task::SIG_TERM, $task->getTermSignal(), 'The signal should have been Task::SIG_TERM');
        $this->assertSame(Task::STATUS_SIGNALED, $task->getStatus(), "The status should be Task::STATUS_SIGNALED");
        $this->assertNull($task->getExitCode());
    }

    /**
     * @dataProvider shellCommandsToExecute
     * @cover Task::factory, Task::run, Task::statusCheck, Task::getExitCode, Task::getStatus
     */
    public function testCustomTermination($cmd, $cwd, $name)
    {
        $task = Task::factory(ShellRunnable::factory([
            'cmd' => $cmd,
            'cwd' => $cwd,
            'name' => $name
        ]));

        $task->run();
        $task->terminate(Task::SIG_ABRT);
        $task->statusCheck();

        //wait until it's done
        $start = time();
        while ($task->getStatus() != Task::STATUS_SIGNALED) {
            usleep(10000);
            $task->statusCheck();
            if (time() - $start > 5) {
                $this->fail(
                    "We polled the task for 5 seconds and yet it's still running. Perhaps it hung? Status:"
                    . $task->getStatus()
                );
            }
        }

        $this->assertSame(Task::STATUS_SIGNALED, $task->getStatus(), "The status should be Task::STATUS_SIGNALED");
        $this->assertSame(Task::SIG_ABRT, $task->getTermSignal(), 'The signal should have been Task::SIG_ABRT');
        $this->assertNull($task->getExitCode());
    }
}