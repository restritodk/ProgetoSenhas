<?php

namespace App\Livewire;

use App\Services\AttendantPerformanceAnalytics;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AttendantDashboard extends Component
{
    public string $period = 'today';

    public string $customFrom = '';

    public string $customTo = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessAttendantPanel(), 403);

        $today = now(config('app.timezone'))->toDateString();
        $this->customFrom = $today;
        $this->customTo = $today;
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', '7d', '30d', 'month', 'year', 'custom'], true)) {
            return;
        }

        $this->period = $period;
        $this->errorMessage = '';
        unset($this->analytics);
    }

    public function applyCustomPeriod(): void
    {
        $this->period = 'custom';
        $this->errorMessage = '';
        unset($this->analytics);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function analytics(): array
    {
        try {
            return app(AttendantPerformanceAnalytics::class)->forAuthenticatedUser(
                $this->period,
                $this->period === 'custom' ? $this->customFrom : null,
                $this->period === 'custom' ? $this->customTo : null,
            );
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível carregar o período.';

            return app(AttendantPerformanceAnalytics::class)->forAuthenticatedUser('today');
        }
    }

    public function formatDuration(int|float|null $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $total = max(0, (int) round((float) $seconds));
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $secs = $total % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dmin', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dmin %02ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }

    public function render(): View
    {
        return view('livewire.attendant-dashboard');
    }
}
