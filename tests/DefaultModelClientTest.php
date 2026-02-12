<?php

namespace Kipchak\Driver\OpenAI\Tests;

use Kipchak\Driver\OpenAI\DefaultModelClient;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\StreamResponse;
use OpenAI\Testing\ClientFake;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

final class DefaultModelClientTest extends TestCase
{
    public function testDefaultModelInjectedOnCreate(): void
    {
        $fake = new ClientFake([CreateResponse::fake()]);
        $client = new DefaultModelClient($fake, 'gpt-4o-mini');

        $client->chat()->create([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ]);

        $fake->assertSent(Chat::class, function (string $method, array $parameters): bool {
            return $method === 'create' && ($parameters['model'] ?? null) === 'gpt-4o-mini';
        });
    }

    public function testExplicitModelIsPreserved(): void
    {
        $fake = new ClientFake([CreateResponse::fake()]);
        $client = new DefaultModelClient($fake, 'gpt-4o-mini');

        $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ]);

        $fake->assertSent(Chat::class, function (string $method, array $parameters): bool {
            return $method === 'create' && ($parameters['model'] ?? null) === 'gpt-4o';
        });
    }

    public function testDefaultModelInjectedOnCreateStreamed(): void
    {
        $fake = new ClientFake([$this->fakeStreamResponse()]);
        $client = new DefaultModelClient($fake, 'gpt-4o-mini');

        $client->chat()->createStreamed([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ]);

        $fake->assertSent(Chat::class, function (string $method, array $parameters): bool {
            return $method === 'createStreamed' && ($parameters['model'] ?? null) === 'gpt-4o-mini';
        });
    }

    public function testNoDefaultModelLeavesPayloadUntouched(): void
    {
        $fake = new ClientFake([CreateResponse::fake()]);
        $client = new DefaultModelClient($fake, null);

        $client->chat()->create([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ]
        ]);

        $fake->assertSent(Chat::class, function (string $method, array $parameters): bool {
            return $method === 'create' && !array_key_exists('model', $parameters);
        });
    }

    private function fakeStreamResponse(): StreamResponse
    {
        $body = (new StreamFactory())->createStream("data: [DONE]\n\n");
        $response = (new Response())->withBody($body);

        return new StreamResponse(CreateResponse::class, $response);
    }
}
