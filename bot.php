<?php

require 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$pimple = new \Pimple\Container();

$pimple['event_dispatcher'] = function () {
    return new \Symfony\Component\EventDispatcher\EventDispatcher();
};
$pimple['loop'] = function () {
    return \React\EventLoop\Factory::create();
};
$pimple->register(new HipchatRESTClientProvider(), array(
    'hipchat_v1_token' => $_SERVER['HIPCHAT_V1_TOKEN'],
    'hipchat_v2_token' => $_SERVER['HIPCHAT_V2_TOKEN'],
));
$pimple->register(new HipchatJabberProvider(), [
  'hipchat.jabber.id' => $_SERVER['HIPCHAT_JABBER_ID'],
  'hipchat.jabber.nickname' => $_SERVER['HIPCHAT_JABBER_NICKNAME'],
  'hipchat.jabber.password' => $_SERVER['HIPCHAT_JABBER_PASSWORD'],
]);
$pimple->register(new ConsoleServiceProvider());
$pimple->register(new MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));
$pimple->register(new SerializerServiceProvider());
$pimple->register(new JiraClientProvider(), array(
    'jira.url' => $_SERVER['JIRA_URL'],
    'jira.username' => $_SERVER['JIRA_USERNAME'],
    'jira.password' => $_SERVER['JIRA_PASSWORD'],
));

$pimple['hipchat.cache.user'] = function (\Pimple\Container $c) {
    return new \React\Cache\ArrayCache();
};

$pimple->extend('hipchat.jabber.client', function (\MainlyCode\HipChat\Client $client, \Pimple\Container $c) {
    $logger = $c['logger'];

    $events = array('connect.before', 'connect.after');
    foreach ($events as $event) {
        $client->on($event, function (\MainlyCode\Xmpp\Connection $connection) use ($logger, $event) {
            $logger->debug($event, array('connection' => $connection));
        });
    }

    $client->on('connect.error', function ($message, \MainlyCode\Xmpp\Connection $connection) use ($logger) {
        $logger->error('connect.error', array('message' => $message, 'connection' => $connection));
    });

    $client->on('xmpp.received', function ($message) use ($logger) {
        $logger->debug('xmpp.received', array('message' => $message));
    });

    $client->on('xmpp.sent', function ($message) use ($logger) {
        $logger->debug('xmpp.sent', array('message' => $message));
    });

    $client->on('xmpp.session.established', function ($message, \MainlyCode\Stream\WriteStream $write) use ($c) {
        // Probably not necessary.
        $write->xmppPresence($c['hipchat.jabber_id']);

        // This joins the Dev room.
        $write->xmppJoin($c['hipchat.jabber_id'], new \MainlyCode\Xmpp\JabberId('14868_operation_wild_ocelot@conf.hipchat.com'), 'Calamity Jane');

        // This call turns up too much data for the buffer to hold, so the XML does not parse.
        // $write->xmppDiscoverRooms($c['hipchat.jabber_id'], 'conf.hipchat.com');
    });

    // This logs all Jabber stanzas.
    $client->on('xmpp.stanza.received', function (\SimpleXMLElement $message, \MainlyCode\Stream\WriteStream $write, \MainlyCode\Xmpp\Connection $connection) use ($c) {
        $logger = $c['logger'];
        $logger->warning($message->getName(), $c['serializer']->decode($message->asXML(), 'xml'));
    });

    return $client;
});

$pimple['hipchat.jabber.client']->run($pimple['hipchat.jabber.connection']);
