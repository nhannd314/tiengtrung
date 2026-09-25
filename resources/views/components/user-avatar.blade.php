@props(['user'])

@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" {{ $attributes->class('shrink-0 rounded-full object-cover') }}>
@else
    <span {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-red-100 font-semibold text-red-700 dark:bg-red-950 dark:text-red-300') }} aria-hidden="true">
        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
    </span>
@endif
