<?php

use MainlyCode\HipChat\Client;
use MainlyCode\HipChat\Connection;
use MainlyCode\Xmpp\JabberId;

class HipchatJabberProvider implements \Pimple\ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param \Pimple\Container $pimple An Container instance
     */
    public function register(\Pimple\Container $pimple)
    {
        $pimple['hipchat.jabber_id'] = function (\Pimple\Container $c) {
            return new JabberId($c['hipchat.jabber.id']);
        };
        $pimple['hipchat.jabber.connection'] = function (\Pimple\Container $c) {
            return new Connection($c['hipchat.jabber_id'], $c['hipchat.jabber.nickname'], $c['hipchat.jabber.password']);
        };
        $pimple['hipchat.jabber.client'] = function (\Pimple\Container $c) {
            return new Client($c['loop']);
        };
    }
}
