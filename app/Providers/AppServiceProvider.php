<?php

namespace App\Providers;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Contracts\ChatQueryRepositoryInterface;
use App\Contracts\EventRepositoryInterface;
use App\Infrastructure\PostgresChatProjectionRepository;
use App\Infrastructure\PostgresChatQueryRepository;
use App\Infrastructure\PostgresEventRepository;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EventRepositoryInterface::class, PostgresEventRepository::class);
        $this->app->bind(ChatProjectionRepositoryInterface::class, PostgresChatProjectionRepository::class);
        $this->app->bind(ChatQueryRepositoryInterface::class, PostgresChatQueryRepository::class);
    }
}
