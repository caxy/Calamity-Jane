<?php

use Jira\JiraClient;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

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
    }
}
