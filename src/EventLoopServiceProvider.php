<?php

use Pimple\ServiceProviderInterface;
use React\EventLoop\Factory;

class EventLoopServiceProvider implements ServiceProviderInterface
{
    public function register(\Pimple\Container $pimple)
    {
        $pimple['event_loop'] = function () {
            return Factory::create();
        };
    }
}
