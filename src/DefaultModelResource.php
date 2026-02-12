<?php

namespace Kipchak\Driver\OpenAI;

class DefaultModelResource
{
    private object $resource;
    private string $defaultModel;

    public function __construct(object $resource, string $defaultModel)
    {
        $this->resource = $resource;
        $this->defaultModel = $defaultModel;
    }

    public function __call(string $name, array $arguments)
    {
        if ($name === 'create' || $name === 'createStream' || $name === 'createStreamed') {
            $payloadIndex = null;
            if (isset($arguments[0]) && is_array($arguments[0])) {
                $payloadIndex = 0;
            } elseif (isset($arguments[1]) && is_array($arguments[1])) {
                $payloadIndex = 1;
            }

            if ($payloadIndex !== null) {
                if (!isset($arguments[$payloadIndex]['model']) || $arguments[$payloadIndex]['model'] === '') {
                    $arguments[$payloadIndex]['model'] = $this->defaultModel;
                }
            }
        }

        return $this->resource->$name(...$arguments);
    }
}
