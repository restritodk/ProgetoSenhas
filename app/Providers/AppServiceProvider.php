<?php

namespace App\Providers;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\OperationalContext;
use App\Support\AdminNavigation;
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
                            'users.index' => $user?->can('viewAny', User::class) ?? false,
                            'desks.index' => $user?->can('viewAny', Desk::class) ?? false,
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

            $view->with([
                'navigationSections' => $sections,
                'currentClinic' => $user?->clinic,
                'activeUnit' => $user ? $context->activeUnit($user, session()) : null,
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
