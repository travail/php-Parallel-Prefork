<?php

namespace Parallel;

declare(ticks = 1);

class Prefork
{
    public $trapSignals = array(
        SIGTERM => SIGTERM,
    );
    public $maxWorkers         = 3;
    public $errRespawnInterval = 1;
    public $managerPid         = null;
    public $onChildReap        = null;
    public $signalReceived     = null;
    private $inChild           = false;
    private $workerPids        = array();
    private $generation        = 0;

    function __construct($args = array())
    {
        $this->maxWorkers = isset($args['max_workers'])
            ? $args['max_workers'] : $this->maxWorkers;
        $this->trapSignals = isset($args['trap_signals'])
            ? $args['trap_signals'] : $this->trapSignals;

        foreach (array_keys($this->trapSignals) as $sig) {
            pcntl_signal($sig, array($this, '_signalHandler'), false);
        }
    }

    private function _signalHandler(&$sig)
    {
        $this->signalReceived = $sig;
    }

    public function start()
    {
        $this->managerPid     = posix_getpid();
        $this->signalReceived = null;
        $this->generation++;

        if ($this->inChild) {
            die("Cannot start another process while you are in child process\n");
        }

        // for debugging
        if ($this->maxWorkers === 0) {
            return true;
        }

        // main loop
        while ($this->signalReceived === null) {
            $pid = null;
            if (count(array_keys($this->workerPids)) < $this->maxWorkers) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    echo "fork failed!\n";
                    sleep($this->errRespawnInterval);
                    continue;
                }
                if ($pid === 0) {
                    // child process
                    $this->inChild = true;
                    foreach (array_keys($this->trapSignals) as $sig) {
                        pcntl_signal($sig, SIG_DFL, true);
                    }
                    if ($this->signalReceived !== null) {
                        exit(0);
                    }
                    return false;
                }
                $this->workerPids[$pid] = $this->generation;
            }
            $exitPid = is_null($pid)
                ? pcntl_waitpid(-1, $status)
                : pcntl_waitpid(-1, $status, WNOHANG);
            if ($exitPid > 0 && isset($status)) {
                $this->_runChildReapCb($exitPid, $status);
                if (
                    isset($this->workerPids[$exitPid])
                    && $this->workerPids[$exitPid] === $this->generation
                    && pcntl_wifexited($status) !== true
                    ) {
                    sleep($this->errRespawnInterval);
                }
                unset($this->workerPids[$exitPid]);
            }
        }
        // send signals to workers
        if ($sig = $this->trapSignals[$this->signalReceived]) {
            $this->signalAllChildren($sig);
        }

        return true;
    }

    public function finish($exitCode = 0)
    {
        if ($this->maxWorkers === 0) {
            return;
        }
        exit($exitCode);
    }

    public function waitAllChildren()
    {
        foreach (array_keys($this->workerPids) as $pid) {
            if ($exitPid = pcntl_wait($status)) {
                unset($this->workerPids[$exitPid]);
                $this->_runChildReapCb($pid, $status);
            }
        }
    }

    public function signalAllChildren($sig)
    {
        foreach (array_keys($this->workerPids) as $pid) {
            posix_kill($pid, $sig);
        }
    }

    public function signalReceived()
    {
        return $this->signalReceived;
    }

    public function generation()
    {
        return $this->generation;
    }

    // PHP 5.2.x ain't implemented lamda function
    private function _runChildReapCb($exitPid, $status)
    {
        $cb = $this->onChildReap;
        if ($cb) {
/*
            try {
                $cb->($exitPid, $status);
            }
*/
            // XXX - hmph, what to do hear?
        }
    }
}
