<?php

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Monolog\Formatter\LineFormatter;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;

class MonologServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['logger'] = function () use ($app) {
            return $app['monolog'];
        };

        $app['monolog.logger.class'] = 'Monolog\Logger';

        $app['monolog'] = function ($app) {
            $log = new $app['monolog.logger.class']($app['monolog.name']);

            $log->pushHandler($app['monolog.handler']);
            $log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());

            return $log;
        };

        $app['monolog.formatter'] = function () {
            return new LineFormatter();
        };

        $app['monolog.handler'] = function () use ($app) {
            $handler = new ConsoleHandler($app['console.output'], $app['monolog.bubble']);
            $handler->setFormatter($app['monolog.formatter']);

            return $handler;
        };

        $app['monolog.name'] = 'myapp';
        $app['monolog.bubble'] = true;
        $app['monolog.exception.logger_filter'] = null;
    }
}
