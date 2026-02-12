# OpenAI Driver for Kipchak

This driver uses the [openai-php/client](https://github.com/openai-php/client) package with the
Symfony PSR-18 HTTP client by default.

It supports multiple OpenAI connections.

#### Composer Package

`kipchak/driver-openai`

#### Sample Config File:

This config file should be placed in your Kipchak project's config directory, as in the starter
project at https://1x.ax/mamluk/kipchak/starter/~files/master/config/kipchak.openai.php.

The config file should look like this (also avalable in sample.config.php):

```
<?php

use function Kipchak\Core\env;

return [
    'connections' => [
        'default' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'organization' => env('OPENAI_ORG', ''),
            'project' => env('OPENAI_PROJECT', ''),
            'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini')
        ]
    ]
];

```

## How to use it?

Install it via composer: `composer require kipchak/driver-openai`.

### Example Usage

```php
// Load the OpenAI driver.
$openai = Kipchak\Driver\OpenAI\OpenAI::get('default'); // default is a connection name defined in the config file.
// Use the $openai client as you would the openai-php client.

$response = $openai->responses()->create([
    'input' => 'Write a haiku about Kipchak.'
]);
```

The `default_model` config is automatically applied when calling `create` or `createStream` on supported
resources. You can still override it by passing a `model` in the request payload.
Use `$openai->raw()` if you need the underlying `OpenAI\Client` instance.

## Advanced customization

- Provide a `client_builder` callable in the connection config to override or extend the client setup.

## What is a Kipchak Driver?

Kipchak Drivers are used to connect Kipchak to various data sources or storage systems.

They provide a standardized interface for interacting with different data sources, allowing developers to
focus on building their applications rather than dealing with the complexities of each data source.

Drivers were introduced as a part of the Kipchak 2.0 release.

Drivers are basically Container Dependencies injected into Kipchak's Service Container.

## How do Kipchak Drivers work?

Some Kipchak drivers are wired into the Service Container via a config file (where required).

If applicable, you will find a sample config file in this repository as well as in the starter project at https://1x.ax/mamluk/kipchak/starter.

Each driver defines an implementation of Kipchak's Driver Interface. Where applicable, the dependency may
accept a parameter for a specific instance of the driver specified as a config property, for instance, in the case
of multiple database connections or S3 buckets.

So you may access the driver by invoking \Mamluk\Kipchak\Driver\Memcached\Memcached::get('cache'), where
`cache` is the name of one of the memcached connection pools specified in the config file.
