<?php

use MainlyCode\HipChat\Client;
use MainlyCode\Stream\WriteStream;
use MainlyCode\Xmpp\Connection;
use MainlyCode\Xmpp\JabberId;
use Pimple\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleServiceProvider implements \Pimple\ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['console'] = function (Container $c) {
            $definition = new InputDefinition(array(
              new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

              new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message'),
              new InputOption('--quiet', '-q', InputOption::VALUE_NONE, 'Do not output any message'),
              new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug'),
              new InputOption('--version', '-V', InputOption::VALUE_NONE, 'Display this application version'),
              new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question'),
            ));
            $application = new Application();
            $application->setAutoExit(false);
            $application->setDefinition($definition);

            return $application;
        };

        $pimple['command.jira.ticket'] = function (Container $c) {
            $command = new Command('jira:ticket');
            $command->addArgument('project');
            $command->addArgument('summary', InputArgument::IS_ARRAY);
            $command->setCode(function (InputInterface $input, OutputInterface $output) use ($c) {
                /** @var \Jira\JiraClient $client */
                $client = $c['jira.client'];
                $project = $input->getArgument('project');
                $summary = $input->getArgument('summary');

                $issue = new \Jira\Remote\RemoteIssue();
                $issue
                  ->setProject($project)
                  ->setType(1)
                  ->setSummary(implode(' ', $summary))
                ;

                $client->create($issue);
                $output->write('created '.$issue->getId());
            });

            return $command;
        };

        $pimple->extend('console', function (Application $application, Container $c) {
            $application->add($c['command.jira.ticket']);

            return $application;
        });

        $pimple['console.input'] = function (Container $c) {
            return new ArgvInput();
        };

        $pimple['console.output'] = function (Container $c) {
            $input = $c['console.input'];
            $output = new ConsoleOutput();
            if (true === $input->hasParameterOption(array('--quiet', '-q'))) {
                $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            } else {
                if ($input->hasParameterOption('-vvv') || $input->hasParameterOption('--verbose=3') || $input->getParameterOption('--verbose') === 3) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
                } elseif ($input->hasParameterOption('-vv') || $input->hasParameterOption('--verbose=2') || $input->getParameterOption('--verbose') === 2) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
                } elseif ($input->hasParameterOption('-v') || $input->hasParameterOption('--verbose=1') || $input->hasParameterOption('--verbose') || $input->getParameterOption('--verbose')) {
                    $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
                }
            }

            return $output;
        };

        $pimple->extend('hipchat.jabber.client', function (Client $client, Container $c) {
            $client->on('xmpp.message.received', function (\SimpleXMLElement $message, WriteStream $write, Connection $connection) use ($client, $c) {
                $matches = array();
                $name = strtolower($c['hipchat.jabber.nickname']);
                if (preg_match('/\@'.$name.' ?(.*)$/i', $message->body, $matches)) {
                    $logger = $c['logger'];
                    $logger->info('Running command', array('input' => $matches[1], 'message' => $message, 'connection' => $connection));
                    $input = new StringInput($matches[1]);
                    $output = new BufferedOutput();
                    $output->setDecorated(false);

                    /** @var Application $application */
                    $application = $c['console'];
                    $exitCode = $application->run($input, $output);

                    $write->xmppMessage(new JabberId($message['from']), $c['hipchat.jabber_id'], '/quote '.$output->fetch());
                }
            });

            return $client;
        });
    }
}
