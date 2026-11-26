@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold leading-5 text-slate-700']) }}>
    {{ $value ?? $slot }}
</label>
