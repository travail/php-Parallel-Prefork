<?php

use \Parallel\Prefork;

require_once __DIR__ . '/../vendor/autoload.php';

main();
exit();

function main()
{
    $pp = new Prefork(array(
        'max_workers'  => 5,
        'trap_signals' => array(
            SIGHUP  => SIGTERM,
            SIGTERM => SIGTERM,
        ),
    ));
    while ($pp->signalReceived() !== SIGTERM) {
        loadConfig();
        if ($pp->start()) {
            continue;
        }
        workChildren();
        $pp->finish();
    }
    $pp->waitAllChildren();
}

function loadConfig()
{
    echo "Load configuration\n";
}

function workChildren()
{
    for ($i = 1; $i <= 3; $i++) {
        echo "Sleep $i seconds\n";
        sleep($i);
    }
}
