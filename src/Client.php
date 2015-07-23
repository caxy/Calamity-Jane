<?php

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use MainlyCode\Stream\ReadStream;
use MainlyCode\Stream\WriteStream;
use MainlyCode\Xmpp\Connection;
use MainlyCode\Xmpp\StanzaParser;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\SocketClient\ConnectorInterface;
use React\SocketClient\SecureStream;
use React\Stream\Stream;

class Client implements EventEmitterInterface
{
    use EventEmitterTrait;

    private $connector;
    private $loop;
    private $readStream;
    private $writeStream;

    public function __construct(LoopInterface $loop = null, ConnectorInterface $connector)
    {
        $this->connector = $connector;
        $this->loop = $loop;
        $this->readStream = new ReadStream(new StanzaParser());
        $this->writeStream = new WriteStream();
    }

    /**
     * @param Connection $connection
     */
    public function run(Connection $connection)
    {
        $this->connect($connection);

        $this->loop->run();
    }

    /**
     * @param Connection $connection
     *
     * @return \React\Promise\PromiseInterface|static
     */
    public function connect(Connection $connection)
    {
        $this->emit('connect.before', array($connection));

        /** @var Promise $promise */
        $promise = $this->connector->create($connection->getHost(), $connection->getPort());

        return $promise
          ->then(function (Stream $stream) use ($connection) {
              $stream = new SecureStream($stream, $this->loop);
              $this->configureStreams($stream, $this->readStream, $this->writeStream, $connection);

              return $stream;
          }, function ($reason) use ($connection) {
              $this->emit('connect.error', array($reason, $connection));
          })
          ->then(function (Stream $stream) use ($connection) {
              $this->emit('connect.after', array($connection));

              return $stream;
          })
        ;
    }

    protected function configureStreams(Stream $stream, ReadStream $read, WriteStream $write, Connection $connection)
    {
        $write->pipe($stream)->pipe($read);

        $read->on('data', $this->getReadCallback($write, $connection, 'xmpp.received'));
        $read->on('xmpp.stanza.received', $this->getReadCallback($write, $connection, 'xmpp.stanza.received'));
        $read->on('xmpp.session.established', $this->getReadCallback($write, $connection, 'xmpp.session.established'));
        $read->on('xmpp.message.received', $this->getReadCallback($write, $connection, 'xmpp.message.received'));
        $write->on('data', $this->getWriteCallback($connection));

        $stream->on('end', $this->getEndCallback($connection));

        $error = $this->getErrorCallback($connection);
        $read->on('error', $error);
        $write->on('error', $error);

        $loop = $this->loop;

        $this->on('connect.after', function (Connection $connection) use ($write, $loop) {
            $write->xmppStartStream($connection->getHost());

            $loop->addPeriodicTimer(60, function () use ($write) {
                $write->keepAlive();
            });
        });

        $read->on('xmpp.tls.required', function ($data) use ($write) {
            $write->xmppStartTls();
        });

        $read->on('xmpp.tls.proceed', function ($data) use ($stream, $write, $connection) {
            $this->encryptSocket($stream->stream);
            $write->xmppStartStream($connection->getHost());
        });

        $read->on('xmpp.features', function ($data) use ($connection, $write) {
            $write->xmppAuthenticateNonSasl(
              $connection->getJabberId()->getLocalPart(),
              $connection->getPassword(),
              $connection->getJabberId()->getResourcePart()
            );
        });

        $read->on('xmpp.authentication.success', function ($data) use ($connection, $write) {
            //$write->xmppBind(); // @todo not required on HipChat?
            $write->xmppEstablishSession($connection->getHost());
        });

        $read->on('xmpp.stream.end', function ($data) use ($loop) {
            $loop->stop();
        });

        $this->on('connect.end', function ($connection) use ($loop) {
            $loop->stop();
        });
    }

    /**
     * @param resource $socket
     *
     * @return bool
     */
    protected function encryptSocket($socket)
    {
        stream_set_blocking($socket, 1);
        $result = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        stream_set_blocking($socket, 0);

        return $result;
    }

    /**
     * @param WriteStream $write
     * @param Connection  $connection
     * @param string      $event
     *
     * @return callable
     */
    protected function getReadCallback($write, $connection, $event)
    {
        $client = $this;

        return function ($message) use ($client, $write, $connection, $event) {
            $client->emit($event, array($message, $write, $connection));
        };
    }

    /**
     * @param Connection $connection
     *
     * @return callable
     */
    protected function getWriteCallback($connection)
    {
        $client = $this;

        return function ($message) use ($client, $connection) {
            $client->emit('xmpp.sent', array($message, $connection));
        };
    }

    /**
     * @param Connection $connection
     *
     * @return callable
     */
    protected function getErrorCallback($connection)
    {
        $client = $this;

        return function ($message) use ($client, $connection) {
            $client->emit('connect.error', array($message, $connection));
        };
    }

    /**
     * @param Connection $connection
     *
     * @return callable
     */
    protected function getEndCallback($connection)
    {
        $client = $this;

        return function () use ($client, $connection) {
            $client->emit('connect.end', array($connection));
        };
    }

    /**
     * @return WriteStream
     */
    public function getWriteStream()
    {
        return $this->writeStream;
    }
}
