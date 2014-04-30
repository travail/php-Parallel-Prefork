\Parallel\Prefork
========

## NAME

\Parallel\Prefork - A simple prefork server framework

## SYNOPSIS

```
<?php
use \Parallel\Prefork;

require_once '/path/to/vendor/autoload.php'

$pp = new Prefork(array(
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

## INSTALLATION

To install this package into your project via composer, add the following snippet to your `composer.json`. Then run `composer install`.

```
"require": {
    "travail/parallel-prefork": "dev-master"
}
```

If you want to install from gihub, add the following:

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:travail/php-Parallel-Prefork.git"
    }
]
```

## DESCRIPTION

`\Parallel\Prefork` supports graceful shutdown and run-time reconfiguration.

## DEPENDENCIES

* posix
* pcntl

## METHODS

### new

Instantiation. Takes a hashref as an argument. Recognized parameters are as follows.

#### max_workers

Number of worker processes (default: 3)

#### err_respawn_interval

Number of seconds to deter spawning of child processes after a worker exits abnormally (default: 1)

#### trap_signals

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

[Kazuho Oku](https://metacpan.org/pod/release/KAZUHO/Parallel-Prefork-0.05/lib/Parallel/Prefork.pm)

### AUTHOR

travail

### LICENSE

This library is free software. You can redistribute it and/or modify it under the same terms as PHP itself.
