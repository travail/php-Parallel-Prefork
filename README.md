php-Parallel-Prefork
========

## README

This, maintenance version, is for PHP 5.2, will no longer be updated other than bug fixes. If you use PHP 5.3 or later I strongly recommend to use other branches or tags on [GitHub](https://github.com/travail/php-Parallel-Prefork). 

## NAME

Parallel_Prefork - A simple prefork server framework

## SYNOPSIS

```
<?php
require_once '/paht/to/Parallel/Prefork.php';

$pp = new Parallel_Prefork(array(
    'max_workers'  => 5,
    'trap_signals' => array(
        SIGHUP  => SIGTERM,
        SIGTERM => SIGTERM,
    ),
));

while ($pp->signalReceived() !== SIGTERM) {
    if ($pp->start()) {
        continue;
    }

    // ... do some work within the child process ...

    $pp->finish();
}

$pp->waitAllChildren();
```

## DESCRIPTION

`Parallel_Prefork` supports graceful shutdown and run-time reconfiguration.

## METHODS

### new

Instantiation. Takes a hashref as an argument. Recognized attributes are as follows.

#### maxWorkers

Number of worker processes (default: 3)

#### errRespawnInterval

Number of seconds to deter spawning of child processes after a worker exits abnormally (default: 1)

#### trapSignals

Array of signals to be trapped. Manager process will trap the signals listed in the keys of the array, and send the signal specified in the associated value (if any) to all worker processes.

### start

The main routine. Returns undef in child processes. Returns a `true` within manager process upon receiving a signal specified in the `trapSignals` array.

### finish

Child processes should call this function for termination. Takes exit code as an optional argument. Only usable from child processes.

### signalAllChildren

Sends signal to all worker processes. Only usable from manager process.

### waitAllChildren

Blocks until all worker processes exit. Only usable from manager process.

### THANKS TO

[Kazuho Oku](https://metacpan.org/pod/release/KAZUHO/Parallel-Prefork-0.09/lib/Parallel/Prefork.pm)

### AUTHOR

travail

### LICENSE

This library is free software. You can redistribute it and/or modify it under the same terms as PHP itself.

