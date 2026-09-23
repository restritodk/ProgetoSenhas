<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Central de configurações" description="Personalize identidade, Painel da TV e Totem desta clínica. Sem HTML, CSS ou JavaScript arbitrário.">
        <div class="mb-6 flex flex-wrap gap-2 border-b border-border pb-4">
            @foreach ([
                'geral' => 'Geral',
                'identidade' => 'Identidade visual',
                'tv' => 'Painel da TV',
                'totem' => 'Totem',
                'audio' => 'Áudio e chamadas',
                'atendimento' => 'Atendimento',
            ] as $tab => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $tab }}')"
                    @class([
                        'rounded-lg px-3 py-2 text-sm font-semibold transition',
                        'bg-primary text-white' => $activeTab === $tab,
                        'bg-background text-text hover:bg-primary/10' => $activeTab !== $tab,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($activeTab === 'geral')
            <form wire:submit="saveGeneral" class="grid max-w-3xl gap-4">
                <x-ui.input label="Nome de exibição" name="display_name" id="display_name" wire:model="display_name" maxlength="120" placeholder="Ex.: Clínica Principal">
                    <p class="mt-1 text-xs text-text-muted">Se vazio, usa o nome cadastrado da clínica ({{ auth()->user()?->clinic?->name }}).</p>
                    <x-input-error :messages="$errors->get('display_name')" />
                </x-ui.input>
                <x-ui.input label="Subtítulo / slogan" name="slogan" id="slogan" wire:model="slogan" maxlength="160" placeholder="Opcional">
                    <x-input-error :messages="$errors->get('slogan')" />
                </x-ui.input>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit">Salvar gerais</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="resetSection('geral')" wire:confirm="Restaurar padrões de gerais?">Restaurar padrão</x-ui.button>
                </div>
            </form>
        @endif

        @if ($activeTab === 'identidade')
            <div class="mx-auto max-w-4xl space-y-5">
                <form wire:submit="saveColors" class="rounded-xl border border-border bg-surface p-4 sm:p-5">
                    <h3 class="text-sm font-semibold text-text">Cores da identidade</h3>
                    <p class="mt-0.5 text-xs text-text-muted">Aplicadas ao Painel da TV, Totem e superfícies do sistema.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text" for="primary_color">Cor principal</label>
                            <div class="flex items-center gap-3">
                                <input id="primary_color" type="color" wire:model="primary_color" class="h-11 w-14 cursor-pointer rounded border border-border bg-surface p-1">
                                <input type="text" wire:model="primary_color" maxlength="7" class="min-h-11 w-full max-w-[9rem] rounded-lg border border-border bg-background px-3 text-sm uppercase">
                            </div>
                            <x-input-error :messages="$errors->get('primary_color')" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-text" for="accent_color">Cor de destaque</label>
                            <div class="flex items-center gap-3">
                                <input id="accent_color" type="color" wire:model="accent_color" class="h-11 w-14 cursor-pointer rounded border border-border bg-surface p-1">
                                <input type="text" wire:model="accent_color" maxlength="7" class="min-h-11 w-full max-w-[9rem] rounded-lg border border-border bg-background px-3 text-sm uppercase">
                            </div>
                            <x-input-error :messages="$errors->get('accent_color')" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveColors">
                            <span wire:loading.remove wire:target="saveColors">Salvar cores</span>
                            <span wire:loading wire:target="saveColors">Salvando...</span>
                        </x-ui.button>
                        <x-ui.button type="button" variant="secondary" wire:click="resetSection('cores')" wire:confirm="Restaurar cores padrão?">Restaurar padrão</x-ui.button>
                    </div>
                </form>

                @php
                    $hasMain = (bool) ($brandingPreview['has_main_logo'] ?? false);
                    $hasTv = (bool) ($brandingPreview['has_tv_logo'] ?? false);
                    $hasKiosk = (bool) ($brandingPreview['has_kiosk_logo'] ?? false);
                    $mainUrl = $brandingPreview['main_logo_url'] ?? null;
                    $tvUrl = $brandingPreview['tv_logo_url'] ?? null;
                    $kioskUrl = $brandingPreview['kiosk_logo_url'] ?? null;
                @endphp

                <x-branding.logo-config-card
                    context="main"
                    title="Logo principal"
                    description="Identidade padrão utilizada quando não houver uma logo específica."
                    upload-property="mainLogoUpload"
                    input-id="main_logo_upload"
                    mode-property="main_logo_background"
                    color-property="main_logo_background_color"
                    :mode="$main_logo_background"
                    :color="$main_logo_background_color"
                    :stage-color="$primary_color"
                    :upload="$mainLogoUpload"
                    :persisted-url="$hasMain ? $mainUrl : null"
                    :has-own-logo="$hasMain"
                    :inherited-url="null"
                    :is-inherited="false"
                    save-upload-method="saveMainLogo"
                    remove-method="removeMainLogo"
                    remove-confirm="Remover a logo principal?"
                    appearance-context="main"
                />

                <x-branding.logo-config-card
                    context="tv"
                    title="Logo do Painel da TV"
                    description="Usada no cabeçalho do painel de chamadas."
                    upload-property="tvLogoUpload"
                    input-id="tv_logo_upload"
                    mode-property="tv_logo_background"
                    color-property="tv_logo_background_color"
                    :mode="$tv_logo_background"
                    :color="$tv_logo_background_color"
                    :stage-color="$primary_color"
                    :upload="$tvLogoUpload"
                    :persisted-url="$hasTv ? $tvUrl : null"
                    :has-own-logo="$hasTv"
                    :inherited-url="(! $hasTv && $hasMain) ? $mainUrl : null"
                    :is-inherited="! $hasTv && $hasMain"
                    save-upload-method="saveTvLogo"
                    remove-method="removeTvLogo"
                    remove-confirm="Remover a logo específica da TV?"
                    appearance-context="tv"
                />

                <x-branding.logo-config-card
                    context="kiosk"
                    title="Logo do Totem"
                    description="Exibida na tela pública de retirada de senha."
                    upload-property="kioskLogoUpload"
                    input-id="kiosk_logo_upload"
                    mode-property="kiosk_logo_background"
                    color-property="kiosk_logo_background_color"
                    :mode="$kiosk_logo_background"
                    :color="$kiosk_logo_background_color"
                    stage-color="#f4f7fb"
                    :upload="$kioskLogoUpload"
                    :persisted-url="$hasKiosk ? $kioskUrl : null"
                    :has-own-logo="$hasKiosk"
                    :inherited-url="(! $hasKiosk && $hasMain) ? $mainUrl : null"
                    :is-inherited="! $hasKiosk && $hasMain"
                    save-upload-method="saveKioskLogo"
                    remove-method="removeKioskLogo"
                    remove-confirm="Remover a logo específica do Totem?"
                    appearance-context="kiosk"
                />
            </div>
        @endif

        @if ($activeTab === 'tv')
            <form wire:submit="saveTv" class="grid max-w-4xl gap-4 md:grid-cols-2">
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_show_date" class="size-4 rounded border-border text-accent"> Exibir data</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_show_time" class="size-4 rounded border-border text-accent"> Exibir hora</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_show_connection_status" class="size-4 rounded border-border text-accent"> Exibir status de conexão</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_show_ticket_type" class="size-4 rounded border-border text-accent"> Exibir tipo na senha atual</label>
                <label class="flex items-center gap-3 text-sm text-text md:col-span-2"><input type="checkbox" wire:model="tv_footer_enabled" class="size-4 rounded border-border text-accent"> Exibir rodapé institucional</label>

                <x-ui.input label="Quantidade de últimas chamadas" name="tv_recent_calls_count" id="tv_recent_calls_count" type="number" min="3" max="8" wire:model="tv_recent_calls_count">
                    <x-input-error :messages="$errors->get('tv_recent_calls_count')" />
                </x-ui.input>

                <div class="md:col-span-2 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-3">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Bloco 1</p>
                        <x-ui.input label="Título" name="tv_footer_1_title" id="tv_footer_1_title" wire:model="tv_footer_1_title" maxlength="60" />
                        <x-ui.input class="mt-2" label="Texto" name="tv_footer_1_text" id="tv_footer_1_text" wire:model="tv_footer_1_text" maxlength="120" />
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Bloco 2</p>
                        <x-ui.input label="Título" name="tv_footer_2_title" id="tv_footer_2_title" wire:model="tv_footer_2_title" maxlength="60" />
                        <x-ui.input class="mt-2" label="Texto" name="tv_footer_2_text" id="tv_footer_2_text" wire:model="tv_footer_2_text" maxlength="120" />
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Bloco 3</p>
                        <x-ui.input label="Título" name="tv_footer_3_title" id="tv_footer_3_title" wire:model="tv_footer_3_title" maxlength="60" />
                        <x-ui.input class="mt-2" label="Texto" name="tv_footer_3_text" id="tv_footer_3_text" wire:model="tv_footer_3_text" maxlength="120" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit">Salvar Painel da TV</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="resetSection('tv')" wire:confirm="Restaurar padrões da TV?">Restaurar padrão</x-ui.button>
                    @if ($previewTvUrl)
                        <a href="{{ $previewTvUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-primary hover:bg-background">Abrir painel da TV</a>
                    @endif
                </div>
            </form>
        @endif

        @if ($activeTab === 'audio')
            <form wire:submit="saveTv" class="grid max-w-3xl gap-4">
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_chime_enabled" class="size-4 rounded border-border text-accent"> Tocar sinal antes da chamada</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_speech_enabled" class="size-4 rounded border-border text-accent"> Anunciar senha por voz</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_speak_ticket_type" class="size-4 rounded border-border text-accent"> Falar tipo da senha</label>
                <label class="flex items-center gap-3 text-sm text-text"><input type="checkbox" wire:model="tv_speak_desk" class="size-4 rounded border-border text-accent"> Falar mesa</label>
                <x-ui.input label="Volume do sinal (0–100%)" name="tv_chime_volume" id="tv_chime_volume" type="number" min="0" max="100" wire:model="tv_chime_volume">
                    <p class="mt-1 text-xs text-text-muted">Aplica-se ao chime Web Audio. SpeechSynthesis depende do navegador e não é forçado aqui.</p>
                </x-ui.input>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit">Salvar áudio</x-ui.button>
                </div>
            </form>
        @endif

        @if ($activeTab === 'totem')
            <form wire:submit="saveKiosk" class="grid max-w-3xl gap-4">
                <x-ui.input label="Título da tela inicial" name="kiosk_title" id="kiosk_title" wire:model="kiosk_title" maxlength="80" />
                <x-ui.input label="Subtítulo" name="kiosk_subtitle" id="kiosk_subtitle" wire:model="kiosk_subtitle" maxlength="120" />
                <x-ui.input label="Texto de orientação nos botões" name="kiosk_instruction_text" id="kiosk_instruction_text" wire:model="kiosk_instruction_text" maxlength="80" />
                <x-ui.input label="Mensagem após emissão" name="kiosk_issued_message" id="kiosk_issued_message" wire:model="kiosk_issued_message" maxlength="160" />
                <x-ui.input label="Texto do botão Finalizar" name="kiosk_finish_button_text" id="kiosk_finish_button_text" wire:model="kiosk_finish_button_text" maxlength="40" />
                <x-ui.input label="Retorno automático (segundos)" name="kiosk_auto_return_seconds" id="kiosk_auto_return_seconds" type="number" min="3" max="60" wire:model="kiosk_auto_return_seconds">
                    <p class="mt-1 text-xs text-text-muted">Entre 5 e 60. Padrão: 12.</p>
                </x-ui.input>
                <div class="rounded-xl border border-border bg-background p-4 text-sm text-text-muted">
                    Tipos disponíveis no Totem continuam em
                    <a href="{{ route('unit-ticket-types.index') }}" class="font-semibold text-accent hover:underline">Tipos por Unidade</a>.
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-ui.button type="submit">Salvar Totem</x-ui.button>
                    <x-ui.button type="button" variant="secondary" wire:click="resetSection('totem')" wire:confirm="Restaurar padrões do Totem?">Restaurar padrão</x-ui.button>
                    @if ($previewKioskUrl)
                        <a href="{{ $previewKioskUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-primary hover:bg-background">Abrir Totem</a>
                    @endif
                </div>
            </form>
        @endif

        @if ($activeTab === 'atendimento')
            <div class="max-w-3xl rounded-xl border border-border bg-background p-5 text-sm text-text">
                <p class="font-semibold text-primary">Regras de fila e aging</p>
                <p class="mt-2 text-text-muted">
                    O aging atual permanece no código (<code class="text-xs">NextTicketSelector</code>):
                    a cada 60 segundos de espera, +5 de prioridade efetiva.
                </p>
                <p class="mt-3 text-text-muted">
                    Nesta fase essas regras <strong>não</strong> são editáveis pelo painel, para evitar regressão operacional.
                    A UI está preparada para uma próxima fase com validação e cobertura de testes dedicada.
                </p>
            </div>
        @endif
    </x-ui.card>
</div>
