<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Observers\OpportunityObserver;
use App\Support\DocumentNumberGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentNumberGenerator::class);
    }

    public function boot(): void
    {
        // Stable morph aliases: activities.relatable_type stores "opportunity"
        // instead of a fully qualified class name, so the table survives a
        // namespace refactor or a model rename.
        Relation::enforceMorphMap([
            'lead' => Lead::class,
            'account' => Account::class,
            'contact' => Contact::class,
            'opportunity' => Opportunity::class,
            // Spatie stores the role pivot against the user morph class.
            'user' => User::class,
        ]);

        Opportunity::observe(OpportunityObserver::class);

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->user()?->getKey() ?: $request->ip());
        });

        // Administrators are unconstrained; every other role is checked against
        // the granular Spatie permissions in the policies.
        Gate::before(function (User $user): ?bool {
            return $user->hasRole('Admin') ? true : null;
        });
    }
}
