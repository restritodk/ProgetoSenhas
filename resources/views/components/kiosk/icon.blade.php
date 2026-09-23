@props([
    'name' => 'person',
    'class' => 'size-12',
])

@php
    $class = trim('shrink-0 '.$class);
@endphp

@switch($name)
    @case('person')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
            <circle cx="32" cy="24" r="8" stroke="currentColor" stroke-width="3"/>
            <path d="M18 46c3.5-8 10-12 14-12s10.5 4 14 12" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        @break

    @case('priority')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
            <circle cx="24" cy="26" r="5.5" stroke="currentColor" stroke-width="2.5"/>
            <circle cx="40" cy="26" r="5.5" stroke="currentColor" stroke-width="2.5"/>
            <path d="M16 46c2.5-6.5 7-10 12-10h8c5 0 9.5 3.5 12 10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M32 14c1.2 2.2 3.4 3.6 5.8 3.6-1.5 1.2-2.2 3.2-1.8 5.1C34.2 21.5 32.4 20 30.4 20c-.8 0-1.5.2-2.1.5.8-2.2 2.1-4.1 3.7-6.5z" fill="currentColor"/>
        </svg>
        @break

    @case('urgent')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
            <path d="M32 18v20" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
            <circle cx="32" cy="46" r="2.5" fill="currentColor"/>
        </svg>
        @break

    @case('exam')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <rect x="16" y="12" width="32" height="40" rx="6" stroke="currentColor" stroke-width="3"/>
            <path d="M24 24h16M24 32h16M24 40h10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        @break

    @case('return')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
            <path d="M38 22a12 12 0 1 1-6.5-2.2" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            <path d="M38 14v10h-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('ticket')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <rect x="8" y="14" width="32" height="20" rx="4" stroke="currentColor" stroke-width="2.5"/>
            <path d="M16 14v20M28 22h6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        @break

    @case('monitor')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <rect x="8" y="10" width="32" height="22" rx="3" stroke="currentColor" stroke-width="2.5"/>
            <path d="M18 40h12M24 32v8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        @break

    @case('heart')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <path d="M24 38s-12-7.5-12-16a7 7 0 0 1 12-4.8A7 7 0 0 1 36 22c0 8.5-12 16-12 16z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
        </svg>
        @break

    @case('check')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
            <path d="M20 33l8 8 16-18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @case('arrow')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 12h12M13 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 64 64" fill="none" aria-hidden="true">
            <circle cx="32" cy="32" r="28" stroke="currentColor" stroke-width="3"/>
        </svg>
@endswitch
