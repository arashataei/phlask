<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask\TaskSpec;
use phlask\Exception;
use phlask\TaskSpecInterface;

class ShellRunnable implements TaskSpecInterface
{
    /**
     * The command to run
     *
     * @var string
     */
    protected $cmd;

    /**
     * The working directory to start in.
     *
     * @var string
     */
    protected $cwd;

    /**
     * Arguments to be passed to the file during run.
     *
     * @var array
     */
    protected $args;

    /**
     * A friendly name for this process.
     *
     * @var string
     */
    protected $name;

    /**
     * An identifier for this task.
     *
     * @var string
     */
    protected $id;

    /**
     * All new specs should created by means of a factory.
     *
     * @param array $config A list of configs (optional).
     *
     * @return TaskSpecInterface instance
     *
     * @throws Exception\InvalidArgumentException When the config is off.
     */
    public static function factory(array $config = array())
    {
        if (!isset($config['cmd'])) {
            throw new Exception\InvalidArgumentException("No shell command specified in config");
        }

        if (!isset($config['cwd'])) {
            throw new Exception\InvalidArgumentException("No cwd specified in config");
        }

        if (!is_readable($config['cwd']) || !is_dir($config['cwd'])) {
            throw new Exception\InvalidArgumentException("The cwd needs to be a readable directory");
        }

        if (!isset($config['name'])) {
            throw new Exception\InvalidArgumentException("No friendly name specified for this command");
        }

        //generate an id to use
        $id = isset($config['id']) ? $config['id'] : uniqid();

        return new self(
            $config['cmd'],
            $config['cwd'],
            $config['name'],
            isset($config['args']) ? $config['args'] : array(),
            $id
        );
    }

    protected function __construct($cmd, $cwd, $name, array $args = array(), $id)
    {
        $this->cmd  = $cmd;
        $this->cwd  = $cwd;
        $this->name = $name;
        $this->args = $args;
        $this->id   = $id;
    }

    /**
     * Retrieves the ID for this task.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Indicates whether the exit code can be trusted for
     * raising error levels after execution completes.
     *
     * @return bool True if trustworthy exit codes.
     */
    public function trustExitCode()
    {
        return true;
    }

    /**
     * Retrieves the command to execute.
     *
     * @return string The command to run.
     */
    public function getCommand()
    {
        $cmd = $this->cmd;

        if (count($this->args)) {
            foreach ($this->args as $arg) {
                $cmd .= ' ' . escapeshellarg($arg);
            }
        }

        return trim($cmd);
    }

    /**
     * Retrieves additional environment vars needed to run
     * this command.
     *
     * @return array|null An array of elements or null if none.
     */
    public function getEnv()
    {
        return array();
    }

    /**
     * Sets up the starting working directory.
     *
     * @return string The working directory.
     */
    public function getCwd()
    {
        return $this->cwd;
    }

    /**
     * Tells whether or not this process should be allowed to
     * run in the background indefinitely. This is used to
     * start up daemon processes that can run indefinitely.
     *
     * @return bool True if a daemon process.
     */
    public function isDaemon()
    {
        return true;
    }

    /**
     * Sets a time limit on this process for running in milliseconds.
     * Note that without realtime systems this value will always be
     * exceeded since there's no way to precisely stop at a particular
     * time. For example, setting 1000 may result in the process running
     * 1023 milliseconds.
     *
     * @return int The timeout in milliseconds. Zero indicates no timeout. Note
     *             that this value is ignored if isDaemon() returns true.
     */
    public function getTimeout()
    {
        return 0;
    }

    /**
     * Retrieves a string name for the task.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
