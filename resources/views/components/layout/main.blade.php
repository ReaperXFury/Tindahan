<x-layout.app>
    <div class="flex min-h-screen bg-slate-50">
        <x-layout.sidebar />
        <main class="flex-1 min-w-0 p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>

    {{-- Toast notifications --}}
    <div id="toast-container" aria-live="polite"
        class="fixed top-4 right-4 z-[60] flex flex-col gap-2 w-[calc(100%-2rem)] sm:w-96 pointer-events-none"></div>

    <script data-navigate-once>
        (function () {
            if (window.__toastBound) return;
            window.__toastBound = true;

            const styles = {
                success: {
                    box: 'border-green-300 bg-green-100 text-green-800',
                    icon: '<svg class="w-5 h-5 shrink-0 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
                },
                error: {
                    box: 'border-red-200 bg-red-50 text-red-800',
                    icon: '<svg class="w-5 h-5 shrink-0 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
                },
            };

            window.addEventListener('notify', (e) => {
                const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
                const type = d && d.type === 'error' ? 'error' : 'success';
                const container = document.getElementById('toast-container');
                if (!container) return;

                const toast = document.createElement('div');
                toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
                toast.className = 'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm font-medium transition duration-200 opacity-0 translate-x-4 ' + styles[type].box;
                toast.innerHTML = styles[type].icon;

                const text = document.createElement('p');
                text.className = 'flex-1';
                text.textContent = (d && d.message) || '';   // textContent, so no HTML injection
                toast.appendChild(text);

                const close = document.createElement('button');
                close.type = 'button';
                close.setAttribute('aria-label', 'Dismiss');
                close.className = 'opacity-60 hover:opacity-100 cursor-pointer';
                close.textContent = '✕';
                toast.appendChild(close);

                const remove = () => {
                    toast.classList.add('opacity-0', 'translate-x-4');
                    setTimeout(() => toast.remove(), 200);
                };
                close.addEventListener('click', remove);

                container.appendChild(toast);
                requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-x-4'));
                setTimeout(remove, 4000);
            });
        })();
    </script>
</x-layout.app>