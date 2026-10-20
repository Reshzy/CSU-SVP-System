<?php

namespace App\Providers;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Observers\PurchaseRequestObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use PhpOffice\PhpWord\Settings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePhpWordTempDirectory();
        $this->configureSuperAdminGate();
        $this->configurePulseGate();

        PurchaseRequest::observe(PurchaseRequestObserver::class);
    }

    /**
     * System Admin and Executive Officer pass every ability check.
     *
     * Without this, CEO and admin screens fail whenever a policy or `can:`
     * middleware names a permission nobody thought to grant them. Returning
     * null rather than false is required so normal gate resolution continues
     * for every other role.
     */
    protected function configureSuperAdminGate(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasAnyRole(['System Admin', 'Executive Officer']) ? true : null;
        });
    }

    /**
     * Pulse is ops telemetry, not the purchase-request activity timeline.
     * System Admin (and Executive Officer via Gate::before) can open /pulse.
     */
    protected function configurePulseGate(): void
    {
        Gate::define('viewPulse', function (?User $user): bool {
            return $user?->hasRole('System Admin') ?? false;
        });
    }

    /**
     * PhpWord defaults to sys_get_temp_dir(), which can be unwritable
     * (C:\Windows) when PHP is spawned without a user TEMP.
     */
    protected function configurePhpWordTempDirectory(): void
    {
        $directory = storage_path('app/phpword-temp');

        File::ensureDirectoryExists($directory);

        Settings::setTempDir($directory);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
