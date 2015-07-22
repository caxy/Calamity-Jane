<?php

class HipchatRESTClientProvider implements \Pimple\ServiceProviderInterface
{
    public function register(\Pimple\Container $pimple)
    {
        $pimple['hipchat.client.v1'] = function (\Pimple\Container $c) {
            return new \HipChat\HipChat($c['hipchat_v1_token']);
        };

        $pimple['hipchat.client.v2'] = function (\Pimple\Container $c) {
            $oauth = new \GorkaLaucirica\HipchatAPIv2Client\Auth\OAuth2($c['hipchat_v2_token']);

            return new \GorkaLaucirica\HipchatAPIv2Client\Client($oauth);
        };
    }
}
