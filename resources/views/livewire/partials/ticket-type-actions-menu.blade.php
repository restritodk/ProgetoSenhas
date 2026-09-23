@php
    /** @var \App\Models\TicketType $ticketType */
@endphp
<div
    class="relative inline-flex justify-end"
    x-data="{
        open: false,
        focusIndex: -1,
        items: [],
        init() {
            this.$watch('open', (value) => {
                if (value) {
                    this.$nextTick(() => {
                        this.items = Array.from(this.$refs.menu.querySelectorAll('[data-menu-item]'))
                        this.focusIndex = 0
                        this.items[0]?.focus()
                    })
                } else {
                    this.focusIndex = -1
                }
            })
        },
        close() { this.open = false },
        toggle() { this.open = ! this.open },
        onKeydown(event) {
            if (! this.open) return
            if (event.key === 'Escape') {
                event.preventDefault()
                this.close()
                this.$refs.trigger?.focus()
                return
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault()
                this.focusIndex = (this.focusIndex + 1) % this.items.length
                this.items[this.focusIndex]?.focus()
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault()
                this.focusIndex = (this.focusIndex - 1 + this.items.length) % this.items.length
                this.items[this.focusIndex]?.focus()
            }
            if (event.key === 'Home') {
                event.preventDefault()
                this.focusIndex = 0
                this.items[0]?.focus()
            }
            if (event.key === 'End') {
                event.preventDefault()
                this.focusIndex = this.items.length - 1
                this.items[this.focusIndex]?.focus()
            }
        }
    }"
    @keydown="onKeydown($event)"
    @click.outside="close()"
    @keydown.escape.window="if (open) { close(); $refs.trigger?.focus() }"
>
    <button
        type="button"
        x-ref="trigger"
        @click="toggle()"
        class="inline-flex size-10 cursor-pointer items-center justify-center rounded-xl border border-border bg-surface text-text transition hover:bg-background focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
        :aria-expanded="open.toString()"
        aria-haspopup="menu"
        aria-label="Ações do tipo {{ $ticketType->name }}"
    >
        <x-admin.icon name="dots" class="size-5" />
    </button>

    <div
        x-ref="menu"
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        role="menu"
        aria-label="Ações"
        class="absolute right-0 z-40 mt-2 w-48 overflow-hidden rounded-xl border border-border bg-surface py-1 shadow-lg"
    >
        <button
            type="button"
            role="menuitem"
            data-menu-item
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2.5 text-left text-sm text-text hover:bg-background focus-visible:bg-background focus-visible:outline-none"
            wire:click="edit({{ $ticketType->id }})"
            @click="close()"
        >
            <x-admin.icon name="pencil" class="size-4 text-text-muted" />
            Editar
        </button>
        <div class="my-1 border-t border-border" role="separator"></div>
        @if ($ticketType->active)
            <button
                type="button"
                role="menuitem"
                data-menu-item
                class="flex w-full cursor-pointer items-center gap-2 px-3 py-2.5 text-left text-sm text-text hover:bg-background focus-visible:bg-background focus-visible:outline-none"
                wire:click="confirmDeactivation({{ $ticketType->id }})"
                @click="close()"
            >
                <x-admin.icon name="ban" class="size-4 text-text-muted" />
                Desativar
            </button>
        @else
            <button
                type="button"
                role="menuitem"
                data-menu-item
                class="flex w-full cursor-pointer items-center gap-2 px-3 py-2.5 text-left text-sm text-text hover:bg-background focus-visible:bg-background focus-visible:outline-none"
                wire:click="confirmActivation({{ $ticketType->id }})"
                @click="close()"
            >
                <x-admin.icon name="power" class="size-4 text-text-muted" />
                Ativar
            </button>
        @endif
        <button
            type="button"
            role="menuitem"
            data-menu-item
            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2.5 text-left text-sm text-danger hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none"
            wire:click="confirmDeletion({{ $ticketType->id }})"
            @click="close()"
        >
            <x-admin.icon name="trash" class="size-4" />
            Excluir
        </button>
    </div>
</div>
