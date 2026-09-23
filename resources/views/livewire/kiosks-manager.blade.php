<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Totens" description="Dispositivos públicos de emissão de senha por unidade. Cada totem tem URL própria com token.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-3">
                <x-ui.input label="Buscar" name="kiosk_search" id="kiosk_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou código" />
                <x-ui.select label="Unidade" name="kiosk_unit_filter" id="kiosk_unit_filter" wire:model.live="unitFilter">
                    <option value="">Todas as unidades</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Status" name="kiosk_status_filter" id="kiosk_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Novo totem
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="kiosk_name" id="kiosk_name" wire:model="name" required maxlength="255">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="Código" name="kiosk_code" id="kiosk_code" wire:model="code" required maxlength="64" placeholder="Ex.: TOTEM-REC">
                    <p class="mt-1 text-xs text-text-muted">Único por clínica. Será convertido para maiúsculas.</p>
                    <x-input-error :messages="$errors->get('code')" />
                </x-ui.input>
                <div>
                    <x-ui.select label="Unidade" name="kiosk_unit_id" id="kiosk_unit_id" wire:model.live="unitId" required>
                        <option value="">Selecione</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}@unless ($unit->active) (desativada)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('unitId')" />
                </div>
                <div>
                    <x-ui.select label="Setor" name="kiosk_sector_id" id="kiosk_sector_id" wire:model="sectorId" required :disabled="$unitId === null">
                        <option value="">{{ $unitId === null ? 'Selecione a unidade' : 'Selecione' }}</option>
                        @foreach ($this->availableSectors as $sector)
                            <option value="{{ $sector->id }}">{{ $sector->name }}@unless ($sector->active) (inativo)@endunless</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('sectorId')" />
                </div>
                <div class="flex items-end md:col-span-2">
                    <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                        Ativo
                    </label>
                </div>

                @if ($editingKioskId)
                    <div
                        class="md:col-span-2 space-y-4 rounded-xl border border-border bg-surface p-4"
                        x-data
                        @kiosk-agent-list-printers.window="
                            const detail = $event.detail || {}
                            const agentUrl = detail.agentUrl || (detail[0] && detail[0].agentUrl)
                            const grant = detail.grant || (detail[0] && detail[0].grant)
                            const signature = detail.signature || (detail[0] && detail[0].signature)
                            if (!agentUrl || !grant || !signature) {
                                $wire.markAgentUnavailable('Resposta de pareamento inválida.')
                                return
                            }
                            fetch(agentUrl + '/printers', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({ grant, signature }),
                                signal: AbortSignal.timeout(4000),
                            })
                                .then(async (response) => {
                                    if (!response.ok) throw new Error('agent')
                                    return response.json()
                                })
                                .then((list) => $wire.receivePrinters(Array.isArray(list) ? list : []))
                                .catch(() => $wire.markAgentUnavailable())
                        "
                        @kiosk-agent-test-print.window="
                            const detail = $event.detail || {}
                            const agentUrl = detail.agentUrl || (detail[0] && detail[0].agentUrl)
                            const grant = detail.grant || (detail[0] && detail[0].grant)
                            const signature = detail.signature || (detail[0] && detail[0].signature)
                            if (!agentUrl || !grant || !signature) {
                                $wire.markAgentUnavailable('Não foi possível preparar o teste de impressão.')
                                return
                            }
                            fetch(agentUrl + '/print/test', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                body: JSON.stringify({ grant, signature }),
                                signal: AbortSignal.timeout(5000),
                            })
                                .then(async (response) => {
                                    if (!response.ok) throw new Error('agent')
                                    return response.json()
                                })
                                .then((result) => {
                                    if (result && result.printed) {
                                        $wire.setPrintStatusMessage('Cupom de teste enviado ao agente.')
                                    } else if (result && result.status === 'duplicate') {
                                        $wire.setPrintStatusMessage('Teste ignorado (job duplicado).')
                                    } else {
                                        $wire.setPrintStatusMessage('Agente respondeu sem confirmar impressão.')
                                    }
                                })
                                .catch(() => $wire.markAgentUnavailable())
                        "
                    >
                        <h3 class="text-sm font-semibold text-text">Impressão</h3>
                        <p class="text-xs text-text-muted">Configuração local deste Totem. O segredo do agente nunca é embutido na tela pública.</p>

                        <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                            <input type="checkbox" wire:model="printEnabled" class="size-4 rounded border-border text-accent">
                            Impressão automática ativada
                        </label>

                        <div class="grid gap-3 md:grid-cols-2">
                            <x-ui.select label="Modo do agente" name="print_agent_listen_mode" id="print_agent_listen_mode" wire:model.live="printAgentListenMode">
                                <option value="local">Local (navegador na CPU Windows)</option>
                                <option value="lan">LAN (tablet Android / outro dispositivo)</option>
                            </x-ui.select>
                            <x-ui.input label="Porta do agente" name="print_agent_port" id="print_agent_port" type="number" min="1024" max="65535" wire:model="printAgentPort">
                                <x-input-error :messages="$errors->get('printAgentPort')" />
                            </x-ui.input>
                        </div>

                        @if ($printAgentListenMode === 'lan')
                            <x-ui.input
                                label="IP ou hostname da CPU Windows"
                                name="print_agent_host"
                                id="print_agent_host"
                                type="text"
                                wire:model="printAgentHost"
                                placeholder="ex.: 192.168.1.50"
                            >
                                <x-input-error :messages="$errors->get('printAgentHost')" />
                            </x-ui.input>
                            <p class="text-xs text-text-muted">
                                No tablet Android, 127.0.0.1 não alcança a CPU do Totem. Informe o IP da máquina Windows onde o Humana Print Agent está instalado.
                                No agente, configure <code class="font-mono">Print:ListenMode=Lan</code> e <code class="font-mono">Print:BindHost</code> conscientemente.
                            </p>
                        @else
                            <p class="text-xs text-text-muted">Modo Local usa <code class="font-mono">http://127.0.0.1</code> na própria CPU Windows.</p>
                        @endif

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <p class="mb-1 text-sm font-medium text-text">Agente</p>
                                @if ($printAgentStatus === 'online')
                                    <p class="text-sm text-success">● Conectado</p>
                                @elseif ($printAgentStatus === 'offline')
                                    <p class="text-sm text-danger">● Agente não encontrado</p>
                                @elseif ($printIsPaired)
                                    <p class="text-sm text-warning">● Pareado (atualize impressoras para verificar conexão)</p>
                                @else
                                    <p class="text-sm text-warning">● Não pareado</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-ui.button type="button" variant="secondary" wire:click="pairPrintAgent" wire:confirm="Gerar novo segredo de pareamento? O anterior deixará de funcionar.">
                                {{ $printIsPaired ? 'Refazer pareamento' : 'Parear agente' }}
                            </x-ui.button>
                            @if ($printIsPaired)
                                <x-ui.button type="button" variant="secondary" wire:click="revokePrintAgent" wire:confirm="Revogar pareamento deste Totem?">
                                    Revogar pareamento
                                </x-ui.button>
                                <x-ui.button type="button" variant="secondary" wire:click="prepareLoadPrinters">
                                    Atualizar impressoras
                                </x-ui.button>
                                <x-ui.button type="button" variant="secondary" wire:click="prepareTestPrint">
                                    Testar impressão
                                </x-ui.button>
                            @endif
                        </div>

                        @if ($pairingSecretOnce !== '')
                            <div class="rounded-lg border border-warning/40 bg-amber-50 px-3 py-3 text-sm text-text">
                                <p class="font-semibold">Segredo de pareamento (exibido uma vez)</p>
                                <p class="mt-1 break-all font-mono text-xs">{{ $pairingSecretOnce }}</p>
                                <p class="mt-2 text-xs text-text-muted">Cole em Print:PairingSecret no Humana Print Agent neste computador. Não grave este valor no frontend público.</p>
                            </div>
                        @endif

                        <div>
                            <x-ui.select label="Impressora" name="print_printer_name" id="print_printer_name" wire:model="printPrinterName">
                                <option value="">Selecione explicitamente</option>
                                @if ($printPrinterName !== '' && collect($availablePrinters)->where('name', $printPrinterName)->isEmpty())
                                    <option value="{{ $printPrinterName }}">{{ $printPrinterName }} (salva)</option>
                                @endif
                                @foreach ($availablePrinters as $printer)
                                    <option value="{{ $printer['name'] }}">{{ $printer['name'] }}@if (! empty($printer['isDefault'])) (padrão do Windows)@endif</option>
                                @endforeach
                            </x-ui.select>
                            <p class="mt-1 text-xs text-text-muted">Nunca selecionamos a primeira impressora automaticamente.</p>
                            <x-input-error :messages="$errors->get('printPrinterName')" />
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <x-ui.select label="Papel" name="print_paper_width" id="print_paper_width" wire:model="printPaperWidth">
                                <option value="80">80 mm</option>
                                <option value="58">58 mm</option>
                            </x-ui.select>
                            <div class="flex flex-col justify-end gap-2">
                                <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                                    <input type="checkbox" wire:model="printAutoCut" class="size-4 rounded border-border text-accent">
                                    Corte automático (se suportado)
                                </label>
                                <label class="flex min-h-11 items-center gap-3 text-sm text-text-muted">
                                    <input type="checkbox" wire:model="printLogo" class="size-4 rounded border-border text-accent" disabled>
                                    Imprimir logo (em breve)
                                </label>
                            </div>
                        </div>

                        @if ($printStatusMessage !== '')
                            <p class="text-sm text-text-muted">{{ $printStatusMessage }}</p>
                        @endif
                    </div>
                @endif

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingKioskId ? 'Salvar totem' : 'Criar totem' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($kiosks->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Nenhum totem encontrado.' : 'Nenhum totem cadastrado.' }}"
                description="{{ $search !== '' || $unitFilter !== '' || $statusFilter !== '' ? 'Ajuste a busca ou os filtros.' : 'Cadastre o primeiro totem da clínica.' }}"
            >
                @if ($search === '' && $unitFilter === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Novo totem</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Totens da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                            <th scope="col" class="px-3 py-3 font-semibold">URL pública</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kiosks as $kiosk)
                            <tr wire:key="kiosk-{{ $kiosk->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">{{ $kiosk->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $kiosk->code }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $kiosk->unit?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $kiosk->sector?->name ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex max-w-xs flex-col gap-2">
                                        <code class="truncate text-xs text-text-muted" title="{{ $kiosk->publicUrl() }}">{{ $kiosk->publicUrl() }}</code>
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 w-fit cursor-pointer items-center justify-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-text hover:bg-background"
                                            x-data
                                            @click="navigator.clipboard.writeText(@js($kiosk->publicUrl())); $wire.markUrlCopied({{ $kiosk->id }})"
                                        >
                                            {{ (string) $copiedUrlKioskId === (string) $kiosk->id ? 'Copiado' : 'Copiar URL' }}
                                        </button>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($kiosk->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $kiosk->id }})">Editar</x-ui.button>
                                        <x-ui.button variant="secondary" wire:click="confirmTokenRegen({{ $kiosk->id }})">Regenerar token</x-ui.button>
                                        @if ($kiosk->active)
                                            <x-ui.button variant="danger" wire:click="confirmDeactivation({{ $kiosk->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $kiosk->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $kiosks->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar totem" :open="$kioskPendingDeactivationId !== null">
        <p>O totem deixará de emitir senhas. Poderá ser reativado depois.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Regenerar token de acesso" :open="$kioskPendingTokenRegenId !== null">
        <p>A URL pública atual deixará de funcionar imediatamente. Será necessário atualizar o endereço no dispositivo.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelTokenRegen">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="regenerateToken" wire:loading.attr="disabled">Regenerar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
