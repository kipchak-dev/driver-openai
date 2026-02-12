<?php

namespace Kipchak\Driver\OpenAI;

use OpenAI\Contracts\ClientContract;

class DefaultModelClient
{
    private ClientContract $client;
    private ?string $defaultModel;
    private array $resourcesWithModel;

    public function __construct(ClientContract $client, ?string $defaultModel, ?array $resourcesWithModel = null)
    {
        $this->client = $client;
        $this->defaultModel = $defaultModel;
        $this->resourcesWithModel = $resourcesWithModel ?? [
            'responses',
            'chat',
            'completions',
            'embeddings',
            'images',
            'audio',
            'moderations'
        ];
    }

    public function __call(string $name, array $arguments)
    {
        $resource = $this->client->$name(...$arguments);
        if ($this->defaultModel === null || $this->defaultModel === '') {
            return $resource;
        }

        if (in_array($name, $this->resourcesWithModel, true) && is_object($resource) && method_exists($resource, 'create')) {
            return new DefaultModelResource($resource, $this->defaultModel);
        }

        return $resource;
    }

    public function raw(): ClientContract
    {
        return $this->client;
    }
}
