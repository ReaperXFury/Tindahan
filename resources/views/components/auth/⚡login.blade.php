<?php

use Livewire\Component;

new class extends Component {
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $credentials = $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        $this->addError('email', 'The provided credentials do not match our records.');
    }
};
?>

<div class="min-h-[calc(100vh-4rem)] flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-2xl font-bold text-emerald-600">
            <div class="p-2 bg-emerald-100 rounded-xl text-emerald-600">
                <x-lucide-store class="w-6 h-6" />
            </div>
            <span>Tindahan<span class="text-emerald-500 font-light">OS</span></span>
        </a>
        <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">
            Log in to your terminal
        </h2>
        <p class="mt-2 text-sm text-slate-600">
            Don't have a store account?
            <a href="{{ route('signup') }}" class="font-semibold text-emerald-600 hover:text-emerald-500 transition">
                Register here
            </a>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow-xs border border-slate-200 sm:rounded-2xl sm:px-10">
            <form wire:submit.prevent="login" method="POST" class="space-y-5">
                @csrf

                {{-- Email Address --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email Address</label>
                    <div class="mt-1 relative rounded-md shadow-xs">
                        <div
                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <x-lucide-mail class="w-5 h-5" />
                        </div>
                        <input wire:model="email" type="email" name="email" id="email" placeholder="owner@tindahan.ph" required
                            class="block w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>
                    @error('email')
                        <div
                            class="mt-2 flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                            <svg class="mr-2 h-4 w-4 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                        <a href="#" class="text-xs font-semibold text-emerald-600 hover:text-emerald-500">
                            Forgot password?
                        </a>
                    </div>
                    <div class="mt-1 relative rounded-md shadow-xs">
                        <div
                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <x-lucide-lock class="w-5 h-5" />
                        </div>
                        <input wire:model="password" type="password" name="password" id="password" placeholder="••••••••" required
                            class="block w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                    </div>
                    @error('password')
                        <div
                            class="mt-2 flex items-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                            <svg class="mr-2 h-4 w-4 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input wire:model="remember" id="remember" name="remember" type="checkbox"
                            class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300 rounded">
                        <label for="remember" class="ml-2 block text-sm text-slate-600">
                            Remember me
                        </label>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div>
                    <button type="submit"
                        class="w-full flex justify-center items-center gap-2 py-3 px-4 border border-transparent rounded-xl shadow-xs text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition cursor-pointer">
                        <x-lucide-log-in class="w-4 h-4" />
                        <span>Log In</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>