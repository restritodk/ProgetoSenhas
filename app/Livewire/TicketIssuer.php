<?php

namespace App\Livewire;

use App\Actions\IssueTicket;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Services\NextTicketSelector;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TicketIssuer extends Component
{
    public ?int $unitId = null;

    public ?int $ticketTypeId = null;

    public ?int $lastIssuedTicketId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);

        $firstUnit = $this->availableUnits->first();
        $this->unitId = $firstUnit?->id;

        $firstType = $this->availableTicketTypes->first();
        $this->ticketTypeId = $firstType?->id;
    }

    public function updatedUnitId(): void
    {
        $this->lastIssuedTicketId = null;
        $this->statusMessage = '';
    }

    public function issue(IssueTicket $issueTicket): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->validate();

        $ticket = $issueTicket->handle($actor, (int) $this->unitId, (int) $this->ticketTypeId);

        $this->lastIssuedTicketId = $ticket->id;
        $this->statusMessage = 'Senha emitida com sucesso.';
        unset($this->queue, $this->lastIssuedTicket);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function availableUnits(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'clinic_id', 'active']);
    }

    /**
     * @return Collection<int, TicketType>
     */
    #[Computed]
    public function availableTicketTypes(): Collection
    {
        return TicketType::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('active', true)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'prefix', 'priority', 'clinic_id', 'active']);
    }

    #[Computed]
    public function lastIssuedTicket(): ?Ticket
    {
        if ($this->lastIssuedTicketId === null) {
            return null;
        }

        return Ticket::query()
            ->with('ticketType:id,name,prefix,priority')
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->lastIssuedTicketId)
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Ticket>
     */
    #[Computed]
    public function queue(): \Illuminate\Support\Collection
    {
        if ($this->unitId === null) {
            return collect();
        }

        $unit = Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->unitId)
            ->first();

        if ($unit === null) {
            return collect();
        }

        return app(NextTicketSelector::class)->rankedWaitingQueue(
            $unit,
            CarbonImmutable::now(config('app.timezone')),
        );
    }

    public function render(): View
    {
        return view('livewire.ticket-issuer');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId)->where('active', true),
                ),
            ],
            'ticketTypeId' => [
                'required',
                'integer',
                Rule::exists('ticket_types', 'id')->where(
                    fn ($query) => $query->where('clinic_id', $clinicId)->where('active', true),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada é inválida ou está desativada.',
            'ticketTypeId.required' => 'Selecione o tipo de senha.',
            'ticketTypeId.exists' => 'O tipo de senha selecionado é inválido ou está desativado.',
        ];
    }
}
