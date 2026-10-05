<x-layout.app>
    <div class="min-h-screen flex flex-col bg-slate-50 text-slate-800">

        {{-- Header Navigation --}}
        <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between">
                <a href="{{ route('home') }}"
                    class="flex items-center gap-2.5 text-xl font-bold text-emerald-600 tracking-tight">
                    <div class="p-1.5 bg-emerald-100 rounded-lg text-emerald-600">
                        <x-lucide-store class="w-5 h-5" />
                    </div>
                    <span>Tindahan<span class="text-emerald-500 font-light">OS</span></span>
                </a>

                <nav class="flex items-center gap-3" aria-label="Account">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
                            Go to Dashboard <x-lucide-arrow-right class="w-4 h-4 inline-block ml-1" />
                        </a>
                    @endauth
                    @guest
                        <a href="{{ route('login') }}"
                            class="px-4 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition">
                            Log in
                        </a>
                        <a href="{{ route('signup') }}"
                            class="px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-600 text-white shadow-xs hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                            Sign up
                        </a>
                    @endguest
                </nav>

                </nav>
            </div>
        </header>

        {{-- Hero Section --}}
        <main class="flex-1">
            <section class="max-w-5xl mx-auto px-5 pt-20 pb-16 text-center">
                {{-- Status Badge --}}
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Smart POS & Inventory System
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold tracking-tight text-slate-900 leading-tight">
                    Run your store from <br class="hidden sm:block" />
                    <span class="text-emerald-600">one simple place</span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto leading-relaxed">
                    All-in-one POS to sell products, manage stock levels, track daily profit, and process mobile load &
                    GCash cash in/out.
                </p>

                {{-- Action Buttons --}}
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}"
                            class="px-6 py-3.5 rounded-xl font-semibold bg-emerald-600 text-white shadow-md hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                            Open Dashboard
                        </a>
                    @endauth
                    @guest
                        <a href="{{ route('signup') }}"
                            class="px-6 py-3.5 rounded-xl font-semibold bg-emerald-600 text-white shadow-md hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                            Get Started Free
                        </a>
                        <a href="{{ route('login') }}"
                            class="px-6 py-3.5 rounded-xl font-semibold bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 hover:border-slate-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 transition">
                            Open Terminal
                        </a>
                    @endguest

                </div>
            </section>

            {{-- Feature Cards Grid --}}
            <section class="max-w-6xl mx-auto px-5 py-12">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    {{-- Card 1: Fast Point of Sale --}}
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                        <div
                            class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 mb-5">
                            <x-lucide-shopping-cart class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Fast Checkout POS</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Record daily sales quickly with item lookup, quick-add buttons, and automated change
                            calculation.
                        </p>
                    </div>

                    {{-- Card 2: Inventory & Stock --}}
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                        <div
                            class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 mb-5">
                            <x-lucide-boxes class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Stock & Utang Ledger</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Keep inventory quantities accurate with low-stock alerts and track customer credit balance
                            cleanly.
                        </p>
                    </div>

                    {{-- Card 3: E-Services (GCash/Load) --}}
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                        <div
                            class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 mb-5">
                            <x-lucide-smartphone class="w-6 h-6" />
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 mb-2">Load & Digital Wallet</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Log e-loading and GCash cash-in / cash-out transactions with built-in service fee
                            calculation.
                        </p>
                    </div>

                </div>
            </section>
        </main>

        {{-- Simple Footer --}}
        <footer class="border-t border-slate-200 bg-white py-6">
            <div class="max-w-6xl mx-auto px-5 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} TindahanOS. Built for local sari-sari stores & retail shops.
            </div>
        </footer>
    </div>
</x-layout.app>