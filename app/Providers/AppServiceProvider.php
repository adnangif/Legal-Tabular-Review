<?php

namespace App\Providers;

use App\Console\Commands\Ltr\ReingestDocumentCommand;
use App\Services\Ltr\Pdf\CommandLinePdfTextExtractor;
use App\Services\Ltr\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfTextExtractor::class, CommandLinePdfTextExtractor::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReingestDocumentCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure indexed string columns fit within older MySQL/MariaDB limits.
        Schema::defaultStringLength(191);
    }
}
