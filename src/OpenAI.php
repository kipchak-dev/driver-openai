<?php

namespace Kipchak\Driver\OpenAI;

use InvalidArgumentException;
use Kipchak\Core\Interfaces;
use Kipchak\Driver\Config\Config;
use OpenAI\Contracts\ClientContract;
use OpenAI\Factory;
use Psr\Container\ContainerInterface;

class OpenAI implements Interfaces\Driver
{
    protected static ContainerInterface $container;

    public static function initialise(ContainerInterface $container, ?string $configFileName = null): void
    {
        self::$container = $container;
        self::set($configFileName);
    }

    public static function set(?string $configFileName = null): void
    {
        $configFileName = $configFileName ?? 'kipchak.openai';
        $config = Config::get($configFileName);
        if (isset($config['connections'])) {
            $connections = $config['connections'];
            foreach ($connections as $connectionName => $connectionDetails) {
                self::$container->set('drivers.openai.' . $connectionName, function (ContainerInterface $c) use ($connectionDetails): DefaultModelClient | Client {
                    $apiKey = $connectionDetails['api_key'] ?? null;
                    if ($apiKey === null || $apiKey === '') {
                        throw new InvalidArgumentException('OpenAI api_key is required.');
                    }

                    $factory = new Factory();
                    $factory = $factory->withApiKey($apiKey);

                    if (!empty($connectionDetails['organization']) && method_exists($factory, 'withOrganization')) {
                        $factory = $factory->withOrganization($connectionDetails['organization']);
                    }

                    if (!empty($connectionDetails['project']) && method_exists($factory, 'withProject')) {
                        $factory = $factory->withProject($connectionDetails['project']);
                    }

                    if (!empty($connectionDetails['base_uri']) && method_exists($factory, 'withBaseUri')) {
                        $factory = $factory->withBaseUri($connectionDetails['base_uri']);
                    }

                    if (isset($connectionDetails['headers']) && is_array($connectionDetails['headers'])
                        && method_exists($factory, 'withHttpHeader')) {
                        foreach ($connectionDetails['headers'] as $name => $value) {
                            $factory = $factory->withHttpHeader($name, $value);
                        }
                    }

                    $defaultModel = $connectionDetails['default_model'] ?? null;

                    if (isset($connectionDetails['client_builder']) && is_callable($connectionDetails['client_builder'])) {
                        $customBuilder = $connectionDetails['client_builder']($factory, $connectionDetails);
                        if ($customBuilder instanceof DefaultModelClient) {
                            return $customBuilder;
                        }
                        if ($customBuilder instanceof ClientContract) {
                            return new DefaultModelClient($customBuilder, $defaultModel);
                        }
                        if ($customBuilder instanceof Factory) {
                            $factory = $customBuilder;
                        }
                    }

                    $client = $factory->make();
                    return new DefaultModelClient($client, $defaultModel);
                });
            }
        }
    }

    public static function get(?string $configKey = null): DefaultModelClient
    {
        $key = $configKey ?? 'default';
        return self::$container->get('drivers.openai.' . $key);
    }

    public static function getDefaultModel(?string $configKey = null): ?string
    {
        $configFileName = 'kipchak.openai';
        $config = Config::get($configFileName);
        if (!isset($config['connections'])) {
            return null;
        }

        $key = $configKey ?? 'default';
        if (!isset($config['connections'][$key])) {
            return null;
        }

        $model = $config['connections'][$key]['default_model'] ?? null;
        return is_string($model) && $model !== '' ? $model : null;
    }
}
