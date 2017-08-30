<?php

namespace Parallel;

declare(ticks = 1);

class Prefork
{
    /**
     * @var string The version of this package.
     */
    const VERSION = '0.2.0';

    /**
     * @var array $trap_signals Manager process will trap the signals listed in the keys of the array, and send the signal specified in the associated value to all worker processes.
     */
    public $trap_signals = [SIGTERM => SIGTERM];

    /**
     * @var int $max_workers The number of the workers.
     */
    public $max_workers = 10;

    /**
     * @var int $err_respawn_interval Number of seconds to deter spawning of child processes after a worker exits abnormally.
     */
    public $err_respawn_interval = 1;

    /**
     * @var callable $on_child_reap Lambda function that is called when a child is reaped.
     */
    public $on_child_reap;

    private $signal_received;
    private $manager_pid;
    private $in_child    = false;
    private $worker_pids = [];
    private $generation  = 0;

    public function __construct(array $args = [])
    {
        if (array_key_exists('max_workers', $args)) {
            $this->max_workers = (int)$args['max_workers'];
        }
        if (array_key_exists('trap_signals', $args) && is_array($args['trap_signals'])) {
            $this->trap_signals = $args['trap_signals'];
        }
        if (array_key_exists('err_respawn_interval', $args)) {
            $this->err_respawn_interval = (int)$args['err_respawn_interval'];
        }

        $self = $this;
        foreach (array_keys($this->trap_signals) as $sig) {
            pcntl_signal(
                $sig,
                function ($sig) use ($self) {$self->signal_received = $sig;},
                false
            );
        }
    }

    /**
     * The main routine. Returns true within manager process upon receiving a signal specified in the trap_signals, false in child processes.
     *
     * @return bool True in manager process, false in child processes
     */
    public function start()
    {
        $this->manager_pid     = posix_getpid();
        $this->signal_received = null;
        $this->generation++;

        if ($this->in_child) {
            die("Cannot start another process while you are in child process\n");
        }

        // For debugging.
        if ($this->max_workers === 0) {
            return true;
        }

        // Main loop.
        while ($this->signal_received === null) {
            $pid = null;
            if (count(array_keys($this->worker_pids)) < $this->max_workers) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    sleep($this->err_respawn_interval);
                    continue;
                }
                if ($pid === 0) {
                    // Child process.
                    $this->in_child = true;
                    foreach (array_keys($this->trap_signals) as $sig) {
                        pcntl_signal($sig, SIG_DFL);
                    }
                    if ($this->signal_received !== null) {
                        exit(0);
                    }

                    return false;
                }
                $this->worker_pids[$pid] = $this->generation;
            }

            $exit_pid = $pid === null ? pcntl_waitpid(-1, $status) : pcntl_waitpid(-1, $status, WNOHANG);

            if ($exit_pid > 0 && $status !== null) {
                $this->runChildReap($exit_pid, $status);
                if (
                    isset($this->worker_pids[$exit_pid]) &&
                    $this->worker_pids[$exit_pid] === $this->generation &&
                    pcntl_wifexited($status) !== true
                ) {
                    sleep($this->err_respawn_interval);
                }
                unset($this->worker_pids[$exit_pid]);
            }
        }

        // Send signals to workers.
        if ($sig = $this->trap_signals[$this->signal_received]) {
            $this->signalAllChildren($sig);
        }

        return true;
    }

    /**
     * Child processes (when executed by a zero-argument call to start) should call this function for termination. Takes exit code as an optional argument. Only usable from child processes.
     *
     * @param int $exit_code
     */
    public function finish($exit_code = 0)
    {
        if ($this->max_workers === 0) {
            return;
        }

        exit($exit_code);
    }

    /**
     * Blocks until all worker processes exit. Only usable from manager process.
     */
    public function waitAllChildren()
    {
        foreach (array_keys($this->worker_pids) as $pid) {
            if ($exit_pid = pcntl_wait($status)) {
                unset($this->worker_pids[$exit_pid]);
                $this->runChildReap($pid, $status);
            }
        }
    }

    /**
     * Sends signal to all worker processes. Only usable from manager process.
     *
     * @param int $sig
     */
    public function signalAllChildren($sig)
    {
        foreach (array_keys($this->worker_pids) as $pid) {
            posix_kill($pid, $sig);
        }
    }

    /**
     * Returns the received signal.
     */
    public function signalReceived()
    {
        return $this->signal_received;
    }

    /**
     * @param int $exit_pid
     * @param int $status
     */
    private function runChildReap($exit_pid, $status)
    {
        $callback = $this->on_child_reap;
        if (is_callable($callback)) {
            $callback($exit_pid, $status);
        }
    }
}
