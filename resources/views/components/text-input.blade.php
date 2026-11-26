@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-none transition placeholder:text-slate-400 focus:border-teal-700 focus:ring-4 focus:ring-teal-700/10 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500']) }}>
