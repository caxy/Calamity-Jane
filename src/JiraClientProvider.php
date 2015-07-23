<?php

use Jira\JiraClient;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JiraClientProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['jira.url'] = '';
        $pimple['jira.username'] = '';
        $pimple['jira.password'] = '';

        $pimple['jira.client'] = function (Container $c) {
            $client = new JiraClient($c['jira.url']);
            $client->login($c['jira.username'], $c['jira.password']);

            return $client;
        };

        $pimple['jira.ticket.command'] = function (Container $c) {
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

                /** @var \Jira\Remote\RemoteIssue $issue */
                $issue = $client->create($issue);
                $output->write('created '.$issue->getKey().': '. $c['jira.url'].'/browse/'. $issue->getKey());
            });

            return $command;
        };

        $pimple->extend('console', function (Application $application, Container $c) {
            $application->add($c['jira.ticket.command']);

            return $application;
        });
    }
}
