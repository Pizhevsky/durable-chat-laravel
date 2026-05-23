<?php

namespace App\Providers;

use App\Contracts\ChatListQueryRepositoryInterface;
use App\Contracts\ChatProjectionRepositoryInterface;
use App\Contracts\EventRepositoryInterface;
use App\Contracts\MessageQueryRepositoryInterface;
use App\Contracts\UserQueryRepositoryInterface;
use App\Infrastructure\PostgresChatListQueryRepository;
use App\Infrastructure\PostgresChatProjectionRepository;
use App\Infrastructure\PostgresEventRepository;
use App\Infrastructure\PostgresMessageQueryRepository;
use App\Infrastructure\PostgresUserQueryRepository;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EventRepositoryInterface::class, PostgresEventRepository::class);
        $this->app->bind(ChatProjectionRepositoryInterface::class, PostgresChatProjectionRepository::class);
        $this->app->bind(UserQueryRepositoryInterface::class, PostgresUserQueryRepository::class);
        $this->app->bind(ChatListQueryRepositoryInterface::class, PostgresChatListQueryRepository::class);
        $this->app->bind(MessageQueryRepositoryInterface::class, PostgresMessageQueryRepository::class);
    }
}
