<?php

namespace App\Providers;

use App\Domain\Meta\Contracts\MetaApiAdapter;
use App\Domain\Meta\Services\MetaAdapterFactory;
use App\Domain\Tenants\Support\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class, fn (): WorkspaceContext => new WorkspaceContext());
        $this->app->bind(MetaApiAdapter::class, fn () => $this->app->make(MetaAdapterFactory::class)->resolve());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
