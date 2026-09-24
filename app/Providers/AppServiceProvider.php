<?php

namespace App\Providers;

use App\Contracts\TvSpeechSynthesizer;
use App\Models\ClinicSetting;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\MediaItem;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicMessageInbox;
use App\Services\ClinicSettings;
use App\Services\OperationalContext;
use App\Services\TvTts\EspeakNgSynthesizer;
use App\Support\AdminNavigation;
use App\Support\AdminPresentation;
use App\Support\AttendantNavigation;
use App\Support\PermissionCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TvSpeechSynthesizer::class, EspeakNgSynthesizer::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('kiosk-page', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });

        // TV polling (~3s) + media (~45s); allow headroom without aiding brute-force of short codes.
        RateLimiter::for('tv-page', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('tv-tts', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        foreach (PermissionCatalog::keys() as $permissionKey) {
            Gate::define($permissionKey, function (User $user) use ($permissionKey): bool {
                return $user->hasPermission($permissionKey);
            });
        }

        View::composer('layouts.admin', function ($view): void {
            $user = auth()->user();
            $sections = AdminNavigation::sections();

            $sections = array_map(function (array $section) use ($user): array {
                $section['items'] = array_values(array_filter(
                    $section['items'],
                    function (array $item) use ($user): bool {
                        if (! ($item['available'] ?? false)) {
                            return false;
                        }

                        return match ($item['route'] ?? null) {
                            'dashboard' => $user?->hasPermission('dashboard.view') ?? false,
                            'clinic.show' => $user?->clinic !== null && $user->can('view', $user->clinic),
                            'settings.index' => $user?->can('viewAny', ClinicSetting::class) ?? false,
                            'users.index' => $user?->can('viewAny', User::class) ?? false,
                            'roles.index' => $user?->hasPermission('roles.view') ?? false,
                            'desks.index' => $user?->can('viewAny', Desk::class) ?? false,
                            'sectors.index' => $user?->can('viewAny', Sector::class) ?? false,
                            'display-panels.index' => $user?->can('viewAny', DisplayPanel::class) ?? false,
                            'media-items.index' => $user?->can('viewAny', MediaItem::class) ?? false,
                            'kiosks.index' => $user?->can('viewAny', Kiosk::class) ?? false,
                            'unit-ticket-types.index' => $user?->hasPermission('unit_ticket_types.manage') ?? false,
                            'ticket-types.index' => $user?->can('viewAny', TicketType::class) ?? false,
                            'tickets.issue' => $user?->can('create', Ticket::class) ?? false,
                            'queue-policies.index' => $user?->hasPermission('queue_policy.view') ?? false,
                            'attendant.panel' => $user?->canAccessAttendantPanel() ?? false,
                            default => false,
                        };
                    },
                ));

                return $section;
            }, $sections);

            $sections = array_values(array_filter(
                $sections,
                fn (array $section): bool => $section['items'] !== [],
            ));

            $context = app(OperationalContext::class);
            $clinic = $user?->clinic;
            $adminBranding = $clinic
                ? AdminPresentation::forClinic(
                    $clinic,
                    app(ClinicSettings::class),
                    app(ClinicBranding::class),
                )->toArray()
                : [];

            $view->with([
                'navigationSections' => $sections,
                'currentClinic' => $clinic,
                'activeUnit' => $user ? $context->activeUnit($user, session()) : null,
                'adminBranding' => $adminBranding,
            ]);
        });

        View::composer('layouts.attendant', function ($view): void {
            $user = auth()->user();
            $context = app(OperationalContext::class);
            $clinic = $user?->clinic;
            $branding = $clinic
                ? AdminPresentation::forClinic(
                    $clinic,
                    app(ClinicSettings::class),
                    app(ClinicBranding::class),
                )->toArray()
                : [];

            $unread = $user
                ? app(ClinicMessageInbox::class)->unreadCountFor($user)
                : 0;

            $view->with([
                'currentClinic' => $clinic,
                'activeUnit' => $user ? $context->activeUnit($user, session()) : null,
                'activeDesk' => $user ? $context->activeDesk($user, session()) : null,
                'attendantBranding' => $branding,
                'attendantNavItems' => $user ? AttendantNavigation::items($user, $unread) : [],
                'showAdminShortcut' => $user !== null
                    && ! $user->isAttendant()
                    && $user->hasPermission('dashboard.view'),
            ]);
        });
    }
}
