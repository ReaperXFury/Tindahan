<?php

use App\Models\Load;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layout.main'), Title('Load Management')]
    class extends Component {
    use WithPagination;

    // Filters
    public string $search = '';
    public string $networkFilter = 'all';
    public string $statusFilter = 'all';

    // Top-Up Form Inputs
    public string $phoneNumber = '';
    public string $network = 'Globe';
    public string $amount = '';
    public string $referenceNumber = '';

    // Modals & Notices
    public bool $showLoadModal = false;

    public function updatedSearch()
    {
        $this->resetPage();

    }
    public function updatedNetworkFilter()
    {
        $this->resetPage();
    }
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function selectAmount(int $val)
    {
        $this->amount = $val;
    }

    public function openLoadModal()
    {
        $this->resetPage();
        $this->showLoadModal = true;

    }

    public function processLoad()
    {
        $this->validate([
            'phoneNumber' => ['required', 'regex:/^(09|\+639)\d{9}$/'],
            'network' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:10', 'max:10000'],
            'referenceNumber' => ['required', 'string'],
        ]);

        try {
            Load::create([
                'network' => $this->network,
                'number' => $this->phoneNumber,
                'amount' => $this->amount,
                'reference' => $this->referenceNumber,
            ]);

            $this->reset(['phoneNumber', 'amount', 'referenceNumber']);
            $this->showLoadModal = false;

            $this->dispatch('notify', type: 'success', message: 'Load transaction processed successfully!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Failed to process load. Please try again.');
        }
    }

    public function deleteLoad(int $loadId)
    {
        try {
            $data = Load::findOrFail($loadId);
            $data->delete();

            $this->dispatch('notify', type: 'success', message: "Load record was deleted.");
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Could not delete this loads record.');
        }


    }



    public function render()
    {
        $loads = Load::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('number', 'like', '%' . $this->search . '%')
                        ->orWhere('reference', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->networkFilter !== 'all', fn($q) => $q->where('network', $this->networkFilter))
            ->latest()
            ->paginate(20);

        // Overall totals (all records, not just the current page)
        $totalLoadDispended = Load::sum('amount');
        $totalTransactions = Load::count();

        // Today
        $todayAmount = Load::whereDate('created_at', today())->sum('amount');
        $todayCount = Load::whereDate('created_at', today())->count();

        // Most used network
        $topNetwork = Load::select('network', DB::raw('COUNT(*) as total'))
            ->groupBy('network')
            ->orderByDesc('total')
            ->first();

        return view('components.⚡load', [
            'loads' => $loads,
            'totalLoadDispended' => $totalLoadDispended,
            'totalTransactions' => $totalTransactions,
            'todayAmount' => $todayAmount,
            'todayCount' => $todayCount,
            'topNetwork' => $topNetwork,
        ]);
    }
};
?>

<div class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Load & Top-Up Management</h1>
            <p class="text-sm text-gray-500">Process e-load, mobile credits, and track top-up transaction logs.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="openLoadModal"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Load / Top-Up
            </button>
        </div>
    </div>

    <!-- Summary Metrics Grid (static values) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Load Dispensed</span>
                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Volume</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{number_format($totalLoadDispended, 2)}}</div>
            <p class="mt-1 text-xs text-gray-500">Total processed value</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Transactions</span>
                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Count</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">{{ $totalTransactions }}</div>
            <p class="mt-1 text-xs text-gray-500">Completed top-ups</p>
        </div>

        <!-- Today's Load -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Today's Load</span>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Today</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($todayAmount, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $todayCount }} {{ Str::plural('transaction', $todayCount) }} today
            </p>
        </div>

        <!-- Top Network -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Top Network</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Popular</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">{{ $topNetwork->network ?? '—' }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $topNetwork ? $topNetwork->total . ' ' . Str::plural('transaction', $topNetwork->total) : 'No data yet' }}
            </p>
        </div>
    </div>

    <!-- Filters & Table Container -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Filter Bar -->
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center ">
            <div class="relative flex-1 max-w-full">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Search mobile number or reference..."
                    class="w-full rounded-lg border border-gray-300 bg-white pl-9 pr-4 py-2 text-sm placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="networkFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="all">All Networks / Providers</option>
                    <option value="Globe">Globe</option>
                    <option value="Tm">TM</option>
                    <option value="Smart">Smart</option>
                    <option value="Tnt">TNT</option>
                    <option value="DITO">DITO</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead
                    class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3">Reference / ID</th>
                        <th class="px-6 py-3">Mobile / Recipient</th>
                        <th class="px-6 py-3 text-center">Provider / Network</th>
                        <th class="px-6 py-3">Date & Time</th>
                        <th class="px-6 py-3 text-left">Amount</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <!-- Sample row (static) -->
                    @forelse ($loads as $load)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-6 py-4 font-mono text-xs font-medium text-indigo-600">
                                #{{ $load->reference }}
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $load->number }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                    {{ $load->network }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $load->updated_at }}
                            </td>
                            <td class="px-6 py-4 text-left font-bold text-gray-900">
                                ₱{{ number_format($load->amount, 2)}}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" wire:click="deleteLoad({{ $load->id }})"
                                    wire:confirm="Are you sure you want to delete this product?"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition cursor-pointer">
                                    <x-lucide-trash class="w-4 h-4" />
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                No loads records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination (static placeholder) -->
        @if ($loads->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">
                {{ $loads->links() }}
            </div>
        @endif
    </div>

    @if ($showLoadModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">Process Load / Top-Up</h3>
                    <button wire:click="$set('showLoadModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="processLoad" class="mt-4 space-y-4">
                    <!-- Network / Wallet -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Provider /
                            Network</label>
                        <select wire:model="network" required
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            <option value="Globe">Globe</option>
                            <option value="Tm">TM</option>
                            <option value="Smart">Smart</option>
                            <option value="Tnt">TNT</option>
                            <option value="DITO">DITO</option>
                        </select>
                        @error('network') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Mobile Number -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Mobile / Account
                            Number</label>
                        <input type="text" wire:model="phoneNumber" placeholder="e.g. 09171234567" required
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('phoneNumber') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Load Denomination Quick Pick -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1">Select
                            Denomination</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button type="button" wire:click="selectAmount(20)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱20</button>
                            <button type="button" wire:click="selectAmount(50)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱50</button>
                            <button type="button" wire:click="selectAmount(100)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱100</button>
                            <button type="button" wire:click="selectAmount(200)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱200</button>
                            <button type="button" wire:click="selectAmount(300)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱300</button>
                            <button type="button" wire:click="selectAmount(500)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱500</button>
                            <button type="button" wire:click="selectAmount(1000)"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-100">₱1000</button>
                        </div>
                    </div>

                    <!-- Custom Amount Input -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Custom Amount
                            (₱)</label>
                        <input type="number" wire:model="amount" placeholder="0.00" step="1" required
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Optional Reference No -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Reference
                            Number</label>
                        <input type="text" wire:model="referenceNumber" placeholder="e.g. Ref #123456" required
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('referenceNumber') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror

                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" wire:click="$set('showLoadModal', false)"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Confirm & Send Load
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>