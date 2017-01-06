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
    $start_time = time();
    $prev_workers = 5;
    $pp->decide_action = function($current_worker, $max_worker) use($start_time, &$prev_workers) {
        $current_time = time();
        $max_worker = $max_worker + (int)(($current_time - $start_time) / 6);
        if ( $max_worker > 10 ) {
            $max_worker = 10;
        }
        if ( $max_worker !== $prev_workers ) {
            echo "===> max worker: $prev_workers => $max_worker\n";
            $prev_workers = $max_worker;
        }
        return $current_worker < $max_worker;
    };
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
