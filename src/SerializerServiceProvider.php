<?php

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class SerializerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['serializer'] = function ($app) {
            return new Serializer($app['serializer.normalizers'], $app['serializer.encoders']);
        };

        $app['serializer.encoders'] = function () {
            return array(new JsonEncoder(), new XmlEncoder());
        };

        $app['serializer.normalizers'] = function () {
            return array(new CustomNormalizer(), new GetSetMethodNormalizer());
        };
    }
}
