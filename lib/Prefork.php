<?php

namespace Parallel;

declare(ticks = 1);

class Prefork
{
    /**
     * @var string The version of this package
     */
    const VERSION = '0.1.0';

    /**
     * @var array Manager process will trap the signals listed in the keys of the array, and send the signal specified in the associated value to all worker processes.
     */
    public $trap_signals = array(SIGTERM => SIGTERM);

    /**
     * @var integer The number of the workers
     */
    public $max_workers = 10;

    /**
     * @var integer number of seconds to deter spawning of child processes after a worker exits abnormally
     */
    public $err_respawn_interval = 1;

    /**
     * @var callable lambda function that is called when a child is reaped
     */
    public $on_child_reap = null;

    /**
     * callable lambda fuction that decide action
     */
    private $decide_action = null;

    private $signal_received = null;
    private $manager_pid     = null;
    private $in_child        = false;
    private $worker_pids     = array();
    private $generation      = 0;

    function __construct(array $args = array())
    {
        $this->max_workers = isset($args['max_workers'])
            ? $args['max_workers'] : $this->max_workers;
        $this->trap_signals = isset($args['trap_signals'])
            ? $args['trap_signals'] : $this->trap_signals;
        $this->decide_action = isset($args['decide_action'])
            ? $args['decide_action'] : null;
        if ( isset($this->decide_action) && !is_callable($this->decide_action) ){
            die("decide_action should be callable\n");
        }

        foreach (array_keys($this->trap_signals) as $sig) {
            pcntl_signal($sig, array($this, '_signalHandler'), false);
        }
    }

    private function _signalHandler(&$sig)
    {
        $this->signal_received = $sig;
    }

    /**
     * The main routine. Returns true within manager process upon receiving a signal specified in the trap_signals, false in child processes.
     *
     * @return bool True in manager proccess, false in child processes
     */
    public function start()
    {
        $this->manager_pid     = posix_getpid();
        $this->signal_received = null;
        $this->generation++;

        if ($this->in_child) {
            die("Cannot start another process while you are in child process\n");
        }

        // for debugging
        if ($this->max_workers === 0) return true;

        // main loop
        while ($this->signal_received === null) {
            $pid = null;
            $currect_workers = count(array_keys($this->worker_pids));
            $should_fork = $currect_workers < $this->max_workers;
            if ( is_callable($this->decide_action) ) {
                try {
                    $should_fork = call_user_func_array($this->decide_action,array($currect_workers, $this->max_workers));
                } catch ( \Exception $e ) {
                    printf("Exception. Use default action: %s\n",$e->getMessage());
                }
            }
            if ($should_fork) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    echo "fork failed!\n";
                    sleep($this->err_respawn_interval);
                    continue;
                }
                if ($pid === 0) {
                    // child process
                    $this->in_child = true;
                    foreach (array_keys($this->trap_signals) as $sig) {
                        pcntl_signal($sig, SIG_DFL, true);
                    }
                    if ($this->signal_received !== null) {
                        exit(0);
                    }
                    return false;
                }
                $this->worker_pids[$pid] = $this->generation;
            }
            $exit_pid = is_null($pid)
                ? pcntl_waitpid(-1, $status)
                : pcntl_waitpid(-1, $status, WNOHANG);
            if ($exit_pid > 0 && isset($status)) {
                $this->_runChildReapCb($exit_pid, $status);
                if (
                    isset($this->worker_pids[$exit_pid])
                    && $this->worker_pids[$exit_pid] === $this->generation
                    && pcntl_wifexited($status) !== true
                    ) {
                    sleep($this->err_respawn_interval);
                }
                unset($this->worker_pids[$exit_pid]);
            }
        }
        // send signals to workers
        if ($sig = $this->trap_signals[$this->signal_received]) {
            $this->signalAllChildren($sig);
        }

        return true;
    }

    /**
     * Child processes (when executed by a zero-argument call to start) should call this function for termination. Takes exit code as an optional argument. Only usable from child processes.
     */
    public function finish($exitCode = 0)
    {
        if ($this->max_workers === 0) return;

        exit($exitCode);
    }

    /**
     * Blocks until all worker processes exit. Only usable from manager process.
     */
    public function waitAllChildren()
    {
        foreach (array_keys($this->worker_pids) as $pid) {
            if ($exit_pid = pcntl_wait($status)) {
                unset($this->worker_pids[$exit_pid]);
                $this->_runChildReapCb($pid, $status);
            }
        }
    }

    /**
     * Sends signal to all worker processes. Only usable from manager process.
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

    private function generation()
    {
        return $this->generation;
    }

    private function _runChildReapCb($exit_pid, $status)
    {
        $cb = $this->on_child_reap;
        if (is_callable($cb)) {
            try {
                call_user_func_array($cb, array($exit_pid, $status));
            } catch (\Exception $e) {
                // ignore
                printf("Ignore Exception: %s\n",$e->getMessage());
            };
        }
    }
}
