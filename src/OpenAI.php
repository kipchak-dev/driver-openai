<?php

namespace Kipchak\Driver\OpenAI;

use InvalidArgumentException;
use Kipchak\Core\Interfaces;
use GuzzleHttp\Client as GuzzleClient;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Http\Http;
use OpenAI\Contracts\ClientContract;
use OpenAI\Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;

class OpenAI implements Interfaces\Driver
{
    /**
     * Guzzle options for a connection without 'http_client'. No limit on the whole request, so long streamed
     * completions are never cut off; instead, a request is abandoned after 600 seconds without receiving data,
     * which is how Symfony's client (this driver's transport before 2.10) times out.
     *
     * @return array<string, mixed>
     */
    public static function defaultHttpOptions(): array
    {
        $options = [
            'connect_timeout' => 10,
            'timeout' => 0,
            'read_timeout' => 600, // honoured by Guzzle's PHP stream handler
        ];

        // Guzzle's cURL handler ignores read_timeout; cURL's low-speed limit is the same rule.
        if (\defined('CURLOPT_LOW_SPEED_LIMIT')) {
            $options['curl'] = [\CURLOPT_LOW_SPEED_LIMIT => 1, \CURLOPT_LOW_SPEED_TIME => 600];
        }

        return $options;
    }

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
                    $factory = $factory->withHttpClient(self::httpClient($connectionDetails));

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

    /**
     * The PSR-18 client for a connection: the psr18 client named by its 'http_client' setting in kipchak.http,
     * or a Guzzle client of its own. Guzzle's client also lets the SDK stream responses.
     */
    private static function httpClient(array $connectionDetails): ClientInterface
    {
        $name = $connectionDetails['http_client'] ?? null;

        return $name === null ? new GuzzleClient(self::defaultHttpOptions()) : Http::psr18($name);
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
