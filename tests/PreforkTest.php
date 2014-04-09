<?php

use Parallel\Prefork;

class PreforkTest extends PHPUnit_Framework_TestCase
{
    /** @var \Parallel\Prefork */
    private $pp;

    public function setUp()
    {
        $this->pp = new Prefork(array(
            'max_workers'  => 5,
            'trap_signals' => array(
                SIGHUP  => SIGTERM,
                SIGTERM => SIGTERM,
            ),
        ));
    }

    public function test()
    {
        $this->assertTrue($this->pp instanceof Prefork);
    }
}
