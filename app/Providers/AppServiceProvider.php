<?php

namespace App\Providers;

use App\Models\ClinicSetting;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\MediaItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\OperationalContext;
use App\Support\AdminNavigation;
use App\Support\AdminPresentation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.admin', function ($view): void {
            $user = auth()->user();
            $sections = AdminNavigation::sections();

            $sections = array_map(function (array $section) use ($user): array {
                $section['items'] = array_values(array_filter(
                    $section['items'],
                    function (array $item) use ($user): bool {
                        return match ($item['route'] ?? null) {
                            'clinic.show' => $user?->clinic !== null && $user->can('manage', $user->clinic),
                            'settings.index' => $user?->can('viewAny', ClinicSetting::class) ?? false,
                            'users.index' => $user?->can('viewAny', User::class) ?? false,
                            'desks.index' => $user?->can('viewAny', Desk::class) ?? false,
                            'display-panels.index' => $user?->can('viewAny', DisplayPanel::class) ?? false,
                            'media-items.index' => $user?->can('viewAny', MediaItem::class) ?? false,
                            'kiosks.index' => $user?->can('viewAny', Kiosk::class) ?? false,
                            'unit-ticket-types.index' => $user?->can('manageAny', Unit::class) ?? false,
                            'ticket-types.index' => $user?->can('viewAny', TicketType::class) ?? false,
                            'tickets.issue' => $user?->can('create', Ticket::class) ?? false,
                            'attendant.panel' => $user?->canAccessAttendantPanel() ?? false,
                            default => true,
                        };
                    },
                ));

                return $section;
            }, $sections);

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

            $view->with([
                'currentClinic' => $user?->clinic,
                'activeUnit' => $user ? $context->activeUnit($user, session()) : null,
                'activeDesk' => $user ? $context->activeDesk($user, session()) : null,
            ]);
        });
    }
}
