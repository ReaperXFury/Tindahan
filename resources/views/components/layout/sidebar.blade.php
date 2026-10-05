@php
    $links = [
        ['Dashboard', 'dashboard', 'lucide-layout-dashboard'],
        ['Point of Sale', 'pos', 'lucide-shopping-cart'],
        ['Products', 'products', 'lucide-boxes'],
        ['Sales History', 'sales', 'lucide-receipt-text'],
        ['Load Management', 'load', 'lucide-smartphone'],
        ['GCash', 'gcash', 'lucide-wallet'],
    ];
@endphp

<aside id="sidebar" data-collapsed="false"
    class="group/sb relative shrink-0 h-screen sticky top-0 flex flex-col w-64 data-[collapsed=true]:w-[4.5rem] bg-white border-r border-slate-200">

    {{-- Edge toggle --}}
    <button id="sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-expanded="true"
        class="absolute -right-3 top-5 z-10 grid place-items-center w-6 h-6 rounded-full bg-white border border-slate-300 text-slate-500 hover:text-emerald-600 hover:border-emerald-500 shadow-xs cursor-pointer">
        <x-lucide-chevron-left class="w-4 h-4 group-data-[collapsed=true]/sb:rotate-180" />
    </button>

    {{-- Brand --}}
    <a href="{{ route('dashboard') }}" wire:navigate.hover
        class="h-16 shrink-0 flex items-center gap-2.5 px-5 border-b border-slate-200 group-data-[collapsed=true]/sb:justify-center group-data-[collapsed=true]/sb:px-0">
        <span class="grid place-items-center w-9 h-9 shrink-0 rounded-xl bg-emerald-600 text-white">
            <x-lucide-store class="w-5 h-5" />
        </span>
        <span class="text-xl font-bold text-emerald-700 whitespace-nowrap group-data-[collapsed=true]/sb:hidden">
            Tindahan<span class="text-emerald-500 font-light">OS</span>
        </span>
    </a>

    {{-- Links --}}
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto overflow-x-hidden">
        <p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 group-data-[collapsed=true]/sb:hidden">
            Menu
        </p>

        @foreach ($links as [$label, $name, $icon])
            @php $active = request()->routeIs($name . '*'); @endphp
            <a href="{{ Route::has($name) ? route($name) : '#' }}" data-label="{{ $label }}"
                @if (Route::has($name)) wire:navigate.hover @endif
                @if ($active) aria-current="page" @endif
                @class([
                    'relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold transition',
                    'group-data-[collapsed=true]/sb:justify-center group-data-[collapsed=true]/sb:px-0',
                    'bg-emerald-50 text-emerald-700 before:absolute before:left-0 before:top-2 before:bottom-2 before:w-1 before:rounded-r before:bg-emerald-600' => $active,
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !$active,
                ])>
                <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0" />
                <span class="whitespace-nowrap group-data-[collapsed=true]/sb:hidden">{{ $label }}</span>
            </a>
        @endforeach
    </nav>

    {{-- User + logout --}}
    <div class="p-3 border-t border-slate-200 space-y-1">
        <div data-label="{{ auth()->user()->name }}"
            class="flex items-center gap-2.5 px-3 py-2 group-data-[collapsed=true]/sb:justify-center group-data-[collapsed=true]/sb:px-0">
            <span class="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-emerald-600 text-white text-xs font-bold">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </span>
            <p class="text-sm font-medium text-slate-700 truncate group-data-[collapsed=true]/sb:hidden">
                {{ auth()->user()->name }}
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" data-label="Log out"
                class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-red-600 hover:bg-red-50 transition cursor-pointer group-data-[collapsed=true]/sb:justify-center group-data-[collapsed=true]/sb:px-0">
                <x-lucide-log-out class="w-4 h-4 shrink-0" />
                <span class="whitespace-nowrap group-data-[collapsed=true]/sb:hidden">Log out</span>
            </button>
        </form>
    </div>
</aside>

<script>
    (function () {
        const sb = document.getElementById('sidebar');
        const btn = document.getElementById('sidebar-toggle');
        const KEY = 'sidebar-collapsed';

        function apply(collapsed) {
            sb.dataset.collapsed = collapsed;
            btn.setAttribute('aria-expanded', !collapsed);
            btn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            // tooltips only while collapsed
            sb.querySelectorAll('[data-label]').forEach(el =>
                collapsed ? el.setAttribute('title', el.dataset.label) : el.removeAttribute('title'));
        }

        apply(localStorage.getItem(KEY) === 'true');

        // turn on the width animation after the first paint, so the page doesn't animate on load
        requestAnimationFrame(() => sb.classList.add('transition-[width]', 'duration-200'));

        btn.addEventListener('click', () => {
            const next = sb.dataset.collapsed !== 'true';
            localStorage.setItem(KEY, next);
            apply(next);
        });
    })();
</script>