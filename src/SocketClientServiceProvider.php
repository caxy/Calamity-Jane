<?php

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use React\Dns\Resolver\Factory;
use React\SocketClient\Connector;
use React\SocketClient\SecureConnector;

class SocketClientServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        // This is Google's DNS.
        $pimple['socket_client.nameserver'] = '8.8.8.8';

        $pimple['socket_client.dns'] = function (Container $c) {
            $factory = new Factory();
            $dns = $factory->createCached($c['socket_client.nameserver'], $c['event_loop']);

            return $dns;
        };

        $pimple['socket_client.connector'] = function (Container $c) {
            return new Connector($c['event_loop'], $c['socket_client.dns']);
        };

        $pimple['socket_client.secure_connector'] = function (Container $c) {
            return new SecureConnector($c['socket_client.connector'], $c['event_loop']);
        };
    }
}
