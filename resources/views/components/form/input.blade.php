@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="flex flex-col gap-1.5">
    <label for="{{ $name }}" class="text-sm font-medium text-stone-700 dark:text-stone-300">{{ $label }}</label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        {{ $attributes->class([
            'rounded-lg border bg-white px-3.5 py-2.5 text-sm shadow-xs outline-none transition placeholder:text-stone-400 focus:ring-2 dark:bg-stone-900',
            'border-red-500 focus:ring-red-500/30' => $errors->has($name),
            'border-stone-300 focus:border-red-500 focus:ring-red-500/20 dark:border-stone-700' => ! $errors->has($name),
        ]) }}
    >

    @error($name)
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
