@props(['text'])

<span class="group relative inline-flex align-middle">
    <button type="button" aria-label="Informações do campo: {{ $text }}" class="ml-0.5 inline-flex h-3 w-3 shrink-0 items-center justify-center rounded-full border border-gray-400 text-[6px] font-semibold leading-none text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">i</button>
    <span role="tooltip" class="absolute left-0 top-full z-50 mt-1 hidden w-max max-w-[min(32rem,80vw)] whitespace-normal rounded border border-gray-300 bg-white px-2 py-1 text-xs font-normal leading-snug text-gray-700 shadow-md group-hover:block group-focus-within:block">
        {{ $text }}
    </span>
</span>
