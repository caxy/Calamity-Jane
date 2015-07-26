<?php

use MainlyCode\Stream\WriteStream;
use MainlyCode\Xmpp\Connection;
use Pimple\Container;

class JabberClientExtension
{
    public function __invoke(Client $client, Container $c) {
        $logger = $c['logger'];

        $events = array('connect.before', 'connect.after');
        foreach ($events as $event) {
            $client->on($event, function (Connection $connection) use ($logger, $event) {
                $logger->debug($event, array('connection' => $connection));
            });
        }

        $client->on('connect.error', function ($message, Connection $connection) use ($logger) {
            $logger->error('connect.error', array('message' => $message, 'connection' => $connection));
        });

        $client->on('xmpp.received', function ($message) use ($logger) {
            $logger->debug('xmpp.received', array('message' => $message));
        });

        $client->on('xmpp.sent', function ($message) use ($logger) {
            $logger->debug('xmpp.sent', array('message' => $message));
        });

        $client->on('xmpp.session.established', function ($message, WriteStream $write) use ($c) {
            // Probably not necessary.
            $write->xmppPresence($c['hipchat.jabber_id']);

            // This joins the Dev room.
            // $write->xmppJoin($c['hipchat.jabber_id'], new \MainlyCode\Xmpp\JabberId('14868_operation_wild_ocelot@conf.hipchat.com'), 'Calamity Jane');

            // This call turns up too much data for the buffer to hold, so the XML does not parse.
            // $write->xmppDiscoverRooms($c['hipchat.jabber_id'], 'conf.hipchat.com');
        });

        // This logs all Jabber stanzas.
        $client->on('xmpp.stanza.received', function (\SimpleXMLElement $message, WriteStream $write, Connection $connection) use ($c) {
            $logger = $c['logger'];
            $logger->warning($message->getName(), $c['serializer']->decode($message->asXML(), 'xml'));
        });

        return $client;
    }
}
