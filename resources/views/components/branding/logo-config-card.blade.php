@props([
    'context' => 'main',
    'title' => 'Logo',
    'description' => '',
    'uploadProperty' => 'mainLogoUpload',
    'inputId' => 'main_logo_upload',
    'modeProperty' => 'main_logo_background',
    'colorProperty' => 'main_logo_background_color',
    'mode' => 'transparent',
    'color' => '#FFFFFF',
    'stageColor' => '#1e3a5f',
    'upload' => null,
    'persistedUrl' => null,
    'hasOwnLogo' => false,
    'inheritedUrl' => null,
    'isInherited' => false,
    'saveUploadMethod' => 'saveMainLogo',
    'removeMethod' => 'removeMainLogo',
    'removeConfirm' => 'Remover esta logo?',
    'appearanceContext' => 'main',
])

@php
    $mode = \App\Support\LogoSurface::normalizeMode($mode);
    $color = \App\Support\LogoSurface::normalizeColor($color);
    $surfaceCss = \App\Support\LogoSurface::cssBackground($mode, $color);
    $previewUrl = $upload ? $upload->temporaryUrl() : ($persistedUrl ?: $inheritedUrl);
    $hasPendingUpload = $upload !== null;
    $showEmptyUpload = ! $hasPendingUpload && $previewUrl === null;
@endphp

<section class="rounded-xl border border-border bg-surface p-4 sm:p-5" wire:key="logo-card-{{ $context }}">
    <div class="mb-3">
        <h3 class="text-sm font-semibold text-text">{{ $title }}</h3>
        @if ($description !== '')
            <p class="mt-0.5 text-xs text-text-muted">{{ $description }}</p>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-[minmax(0,11rem)_minmax(0,1fr)] md:items-start">
        <div class="space-y-2">
            @if ($showEmptyUpload)
                <label
                    for="{{ $inputId }}"
                    class="flex min-h-[7.5rem] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-border bg-background px-3 text-center transition hover:border-accent hover:bg-accent/5"
                >
                    <span class="text-sm font-semibold text-primary">+ Adicionar imagem</span>
                    <span class="mt-1 text-[0.7rem] text-text-muted">PNG, JPEG ou WebP · máx. 2 MB</span>
                </label>
            @else
                <div
                    class="flex min-h-[7.5rem] items-center justify-center rounded-xl border border-border px-3 py-3"
                    style="background: {{ $stageColor }};"
                >
                    <div
                        @class([
                            'inline-flex max-w-full items-center leading-none',
                            'rounded-lg px-3 py-1.5' => $surfaceCss !== null,
                        ])
                        @if ($surfaceCss !== null)
                            style="background: {{ $surfaceCss }};"
                        @endif
                    >
                        <img
                            src="{{ $previewUrl }}"
                            alt="Preview {{ $title }}"
                            class="max-h-16 max-w-[9.5rem] bg-transparent object-contain"
                        >
                    </div>
                </div>
                @if ($hasPendingUpload)
                    <p class="text-[0.7rem] font-medium text-accent">Nova imagem selecionada (ainda não salva)</p>
                    <p class="truncate text-[0.7rem] text-text-muted">{{ $upload->getClientOriginalName() }}</p>
                @elseif ($isInherited)
                    <p class="text-[0.7rem] font-medium text-text-muted">Usando a logo principal</p>
                @elseif ($hasOwnLogo)
                    <p class="text-[0.7rem] text-text-muted">Logo atual</p>
                @endif
            @endif

            <input
                id="{{ $inputId }}"
                type="file"
                wire:model="{{ $uploadProperty }}"
                wire:key="{{ $inputId }}-input"
                accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                class="absolute left-[-9999px] h-px w-px opacity-0"
                tabindex="-1"
            >
            <div wire:loading wire:target="{{ $uploadProperty }}" class="text-xs font-medium text-accent">Carregando imagem...</div>
            <x-input-error :messages="$errors->get($uploadProperty)" />
        </div>

        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                @if ($hasPendingUpload)
                    <x-ui.button
                        type="button"
                        wire:click="{{ $saveUploadMethod }}"
                        wire:loading.attr="disabled"
                        wire:target="{{ $saveUploadMethod }},{{ $uploadProperty }}"
                    >
                        <span wire:loading.remove wire:target="{{ $saveUploadMethod }}">Salvar nova imagem</span>
                        <span wire:loading wire:target="{{ $saveUploadMethod }}">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="cancelLogoUpload('{{ $uploadProperty }}')"
                        wire:loading.attr="disabled"
                        wire:target="{{ $saveUploadMethod }}"
                    >
                        Cancelar
                    </x-ui.button>
                @elseif ($showEmptyUpload)
                    <label for="{{ $inputId }}" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:bg-primary-dark">
                        Escolher imagem
                    </label>
                @else
                    <label for="{{ $inputId }}" class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-border bg-background px-4 text-sm font-semibold text-text transition hover:bg-surface">
                        {{ $isInherited ? 'Usar imagem específica' : 'Alterar imagem' }}
                    </label>
                    @if ($hasOwnLogo)
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            wire:click="{{ $removeMethod }}"
                            wire:confirm="{{ $removeConfirm }}"
                            wire:loading.attr="disabled"
                            wire:target="{{ $removeMethod }}"
                        >
                            Remover
                        </x-ui.button>
                    @endif
                @endif
            </div>

            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Fundo da logo</p>
                <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Fundo da logo — {{ $title }}">
                    @foreach (\App\Support\LogoSurface::labels() as $value => $label)
                        <label @class([
                            'inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition',
                            'border-accent bg-accent/10 text-text' => $mode === $value,
                            'border-border bg-background text-text-muted hover:bg-surface' => $mode !== $value,
                        ])>
                            <input
                                type="radio"
                                wire:model.live="{{ $modeProperty }}"
                                value="{{ $value }}"
                                class="size-3.5 border-border text-accent"
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get($modeProperty)" />

                @if ($mode === \App\Support\LogoSurface::CUSTOM)
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <input
                            type="color"
                            wire:model.live="{{ $colorProperty }}"
                            class="h-9 w-12 cursor-pointer rounded border border-border bg-surface p-1"
                            aria-label="Cor personalizada"
                        >
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="{{ $colorProperty }}"
                            maxlength="7"
                            class="min-h-9 w-28 rounded-lg border border-border bg-background px-2 text-xs uppercase"
                        >
                        <x-input-error :messages="$errors->get($colorProperty)" />
                    </div>
                @endif

                <div class="mt-3">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="saveLogoAppearance('{{ $appearanceContext }}')"
                        wire:loading.attr="disabled"
                        wire:target="saveLogoAppearance"
                    >
                        <span wire:loading.remove wire:target="saveLogoAppearance">Salvar aparência</span>
                        <span wire:loading wire:target="saveLogoAppearance">Salvando...</span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
</section>
