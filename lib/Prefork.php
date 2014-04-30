<?php

namespace Parallel;

declare(ticks = 1);

class Prefork
{
    public $trap_signals         = array(SIGTERM => SIGTERM);
    public $max_workers          = 10;
    public $err_respawn_interval = 1;
    public $manager_pid          = null;
    public $on_child_reap        = null;
    public $signal_received      = null;
    private $in_child            = false;
    private $worker_pids         = array();
    private $generation          = 0;

    function __construct($args = array())
    {
        $this->max_workers = isset($args['max_workers'])
            ? $args['max_workers'] : $this->max_workers;
        $this->trap_signals = isset($args['trap_signals'])
            ? $args['trap_signals'] : $this->trap_signals;

        foreach (array_keys($this->trap_signals) as $sig) {
            pcntl_signal($sig, array($this, '_signalHandler'), false);
        }
    }

    private function _signalHandler(&$sig)
    {
        $this->signal_received = $sig;
    }

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
            if (count(array_keys($this->worker_pids)) < $this->max_workers) {
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

    public function finish($exitCode = 0)
    {
        if ($this->max_workers === 0) return;

        exit($exitCode);
    }

    public function waitAllChildren()
    {
        foreach (array_keys($this->worker_pids) as $pid) {
            if ($exit_pid = pcntl_wait($status)) {
                unset($this->worker_pids[$exit_pid]);
                $this->_runChildReapCb($pid, $status);
            }
        }
    }

    public function signalAllChildren($sig)
    {
        foreach (array_keys($this->worker_pids) as $pid) {
            posix_kill($pid, $sig);
        }
    }

    public function signalReceived()
    {
        return $this->signal_received;
    }

    public function generation()
    {
        return $this->generation;
    }

    // PHP 5.2.x don't have lamda function
    private function _runChildReapCb($exit_pid, $status)
    {
        $cb = $this->on_child_reap;
        if ($cb) {
/*
            try {
                $cb->($exit_pid, $status);
            }
*/
        }
    }
}
