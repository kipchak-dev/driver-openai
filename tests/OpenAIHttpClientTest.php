<?php

declare(strict_types=1);

namespace Kipchak\Driver\OpenAI\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Http\Http;
use Kipchak\Driver\OpenAI\OpenAI;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * The driver sends its requests through Kipchak's HTTP clients: a psr18 client named in kipchak.http, or a
 * Guzzle client of its own.
 */
final class OpenAIHttpClientTest extends TestCase
{
    private ContainerInterface $container;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface, options: array}> */
    private array $sent = [];

    private function boot(array $connection, array $httpConfig = []): void
    {
        $this->container = new class implements ContainerInterface {
            private array $entries = [];
            private array $resolved = [];

            public function set(string $id, mixed $value): void
            {
                $this->entries[$id] = $value;
                unset($this->resolved[$id]);
            }

            public function get(string $id): mixed
            {
                if (!array_key_exists($id, $this->entries)) {
                    throw new class ("No entry {$id}") extends \RuntimeException implements NotFoundExceptionInterface {
                    };
                }

                return $this->resolved[$id] ??= $this->entries[$id] instanceof \Closure ? ($this->entries[$id])($this) : $this->entries[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->entries);
            }
        };
        $this->container->set('drivers.config', [
            'kipchak.openai' => ['connections' => ['default' => $connection + ['api_key' => 'sk-test', 'default_model' => 'gpt-4o-mini']]],
            'kipchak.http' => $httpConfig,
        ]);
        (new \ReflectionProperty(Config::class, 'container'))->setValue(null, $this->container);

        Http::initialise($this->container);
        OpenAI::initialise($this->container);
    }

    /**
     * Replaces a named kipchak.http client with Guzzle on a mock handler, recording what it sends.
     */
    private function mockClient(string $name, Response ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));
        $this->container->set('drivers.http.' . $name, new GuzzleClient(['handler' => $stack]));
    }

    private function transportClient(): object
    {
        $client = OpenAI::get()->raw();
        $transporter = (new \ReflectionProperty($client, 'transporter'))->getValue($client);

        return (new \ReflectionProperty($transporter, 'client'))->getValue($transporter);
    }

    public function testRequestsGoThroughTheNamedKipchakClient(): void
    {
        $this->boot(['http_client' => 'ai'], ['ai' => ['type' => 'psr18']]);
        $this->mockClient('ai', new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'object' => 'list',
            'data' => [['id' => 'gpt-4o-mini', 'object' => 'model', 'created' => 1, 'owned_by' => 'openai']],
        ])));

        $models = OpenAI::get()->models()->list();

        $this->assertSame('gpt-4o-mini', $models->data[0]->id);
        $this->assertCount(1, $this->sent);
        $this->assertSame('https://api.openai.com/v1/models', (string) $this->sent[0]['request']->getUri());
        $this->assertSame('Bearer sk-test', $this->sent[0]['request']->getHeaderLine('Authorization'));
    }

    public function testStreamingWorksThroughTheKipchakClient(): void
    {
        $this->boot(['http_client' => 'ai'], ['ai' => ['type' => 'psr18']]);
        $chunk = fn (string $text) => 'data: ' . json_encode([
            'id' => 'c1', 'object' => 'chat.completion.chunk', 'created' => 1, 'model' => 'gpt-4o-mini',
            'choices' => [['index' => 0, 'delta' => ['content' => $text], 'finish_reason' => null]],
        ]) . "\n\n";
        $this->mockClient('ai', new Response(200, ['Content-Type' => 'text/event-stream'], $chunk('Hel') . $chunk('lo') . "data: [DONE]\n\n"));

        $text = '';
        foreach (OpenAI::get()->chat()->createStreamed(['messages' => [['role' => 'user', 'content' => 'Hi']]]) as $response) {
            $text .= $response->choices[0]->delta->content;
        }

        $this->assertSame('Hello', $text);
        $this->assertTrue($this->sent[0]['options']['stream'] ?? false, 'the SDK asked Guzzle to stream');
        $this->assertSame('gpt-4o-mini', json_decode((string) $this->sent[0]['request']->getBody(), true)['model'], 'default model still applied');
    }

    public function testWithoutHttpClientAConnectionGetsAGuzzleClientWithIdleTimeouts(): void
    {
        $this->boot([]);

        $client = $this->transportClient();
        $this->assertInstanceOf(GuzzleClient::class, $client);
        $this->assertSame(10, $client->getConfig('connect_timeout'));
        $this->assertSame(0, $client->getConfig('timeout'), 'no limit on the whole request');
        $this->assertSame(600, $client->getConfig('read_timeout'));
        $this->assertSame(600, $client->getConfig('curl')[\CURLOPT_LOW_SPEED_TIME]);
        $this->assertSame(1, $client->getConfig('curl')[\CURLOPT_LOW_SPEED_LIMIT]);
    }

    public function testANamedClientMustBePsr18(): void
    {
        $this->boot(['http_client' => 'default'], ['default' => ['type' => 'laravel']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("kipchak.http client 'default' is of type 'laravel', not 'psr18'");
        OpenAI::get();
    }
}
