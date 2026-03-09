<?php

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Adapters\MetaGraphV20Adapter;
use App\Domain\Meta\Contracts\MetaApiAdapter;
use App\Models\MetaConnection;
use InvalidArgumentException;

class MetaAdapterFactory
{
    public function resolve(?MetaConnection $connection = null): MetaApiAdapter
    {
        $version = $connection?->api_version ?? config('services.meta.default_api_version', 'v20.0');

        return match ($version) {
            'v20.0' => app(MetaGraphV20Adapter::class),
            default => throw new InvalidArgumentException("Desteklenmeyen Meta API version: {$version}"),
        };
    }
}
