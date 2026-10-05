<?php

use App\Models\GcashTransaction;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('components.layout.main'), Title('GCash Management')]
    class extends Component {
    use WithPagination;

    // Filters
    public string $search = '';
    public string $typeFilter = 'all';
    public string $dateRange = 'this_month';

    // Form inputs
    public string $type = 'cash_in';
    public string $phoneNumber = '';
    public string $amount = '';
    public string $fee = '';
    public string $referenceNumber = '';

    // Modal
    public bool $showModal = false;

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedTypeFilter()
    {
        $this->resetPage();
    }
    public function updatedDateRange()
    {
        $this->resetPage();
    }

    /** Suggested service fee: ₱5 for every ₱500 (rounded up). Change to match your shop's rates. */
    protected function suggestFee(): void
    {
        if (is_numeric($this->amount) && $this->amount > 0) {
            $this->fee = (string) (ceil($this->amount / 500) * 5);
        } else {
            $this->fee = '';
        }
    }

    // Runs when the user types in the amount input
    public function updatedAmount()
    {
        $this->suggestFee();
    }

    public function selectAmount(int $val)
    {
        $this->amount = (string) $val;
        $this->suggestFee();
    }

    public function openModal(string $type = 'cash_in')
    {
        $this->resetValidation();
        $this->reset(['phoneNumber', 'amount', 'fee', 'referenceNumber']);
        $this->type = $type;
        $this->showModal = true;
    }

    public function processTransaction()
    {
        $this->validate([
            'type' => ['required', 'in:cash_in,cash_out'],
            'phoneNumber' => ['required', 'regex:/^(09|\+639)\d{9}$/'],
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'referenceNumber' => ['required', 'string', 'max:50'],
        ]);

        try {
            GcashTransaction::create([
                'type' => $this->type,
                'number' => $this->phoneNumber,
                'amount' => $this->amount,
                'fee' => $this->fee !== '' ? $this->fee : 0,
                'reference' => $this->referenceNumber,
            ]);

            $this->reset(['phoneNumber', 'amount', 'fee', 'referenceNumber']);
            $this->showModal = false;

            $this->dispatch('notify', type: 'success', message: 'GCash transaction saved successfully!');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Failed to save transaction. Please try again.');
        }
    }

    public function deleteTransaction(int $id)
    {
        try {
            GcashTransaction::findOrFail($id)->delete();

            $this->dispatch('notify', type: 'success', message: 'Transaction was deleted.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Could not delete this transaction.');
        }
    }

    protected function dateBounds(): ?array
    {
        $now = now();

        return match ($this->dateRange) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => null, // 'all'
        };
    }

    public function render()
    {
        $bounds = $this->dateBounds();

        // Table
        $transactions = GcashTransaction::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('number', 'like', '%' . $this->search . '%')
                        ->orWhere('reference', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter !== 'all', fn($q) => $q->where('type', $this->typeFilter))
            ->when($bounds, fn($q) => $q->whereBetween('created_at', $bounds))
            ->latest()
            ->paginate(20);

        // Summary cards (follow the date range, not the page or search)
        $stats = GcashTransaction::query()
            ->when($bounds, fn($q) => $q->whereBetween('created_at', $bounds))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'cash_in'  THEN amount END), 0) as cash_in_total,
                COALESCE(SUM(CASE WHEN type = 'cash_out' THEN amount END), 0) as cash_out_total,
                COALESCE(SUM(CASE WHEN type = 'cash_in'  THEN 1 END), 0)      as cash_in_count,
                COALESCE(SUM(CASE WHEN type = 'cash_out' THEN 1 END), 0)      as cash_out_count,
                COALESCE(SUM(fee), 0)                                         as fees_total,
                COUNT(*)                                                      as total_count
            ")
            ->first();

        return view('components.⚡gcash', [
            'transactions' => $transactions,
            'cashInTotal' => (float) $stats->cash_in_total,
            'cashOutTotal' => (float) $stats->cash_out_total,
            'cashInCount' => (int) $stats->cash_in_count,
            'cashOutCount' => (int) $stats->cash_out_count,
            'feesTotal' => (float) $stats->fees_total,
            'totalCount' => (int) $stats->total_count,
        ]);
    }
};
?>

<div class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">GCash Cash In / Cash Out</h1>
            <p class="text-sm text-gray-500">Record GCash transactions, track service fees, and monitor your cash flow.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" wire:click="openModal('cash_out')"
                class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 shadow-sm hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                Cash Out
            </button>
            <button type="button" wire:click="openModal('cash_in')"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                Cash In
            </button>
        </div>
    </div>

    @php
        $periodLabel = match ($dateRange) {
            'today' => 'today',
            'this_week' => 'this week',
            'this_month' => 'this month',
            'this_year' => 'this year',
            default => 'all time',
        };
    @endphp

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Cash In -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Cash In</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">In</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($cashInTotal, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $cashInCount }} {{ Str::plural('transaction', $cashInCount) }} {{ $periodLabel }}
            </p>
        </div>

        <!-- Cash Out -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Cash Out</span>
                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Out</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($cashOutTotal, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $cashOutCount }} {{ Str::plural('transaction', $cashOutCount) }} {{ $periodLabel }}
            </p>
        </div>

        <!-- Fees Earned -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Service Fees Earned</span>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Income</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">₱{{ number_format($feesTotal, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Your earnings {{ $periodLabel }}</p>
        </div>

        <!-- Total Transactions -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Transactions</span>
                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Count</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($totalCount) }}</div>
            <p class="mt-1 text-xs text-gray-500">Cash in + cash out {{ $periodLabel }}</p>
        </div>
    </div>

    <!-- Filters & Table Container -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Filter Bar -->
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center">
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
                <select wire:model.live="typeFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="all">All Types</option>
                    <option value="cash_in">Cash In</option>
                    <option value="cash_out">Cash Out</option>
                </select>

                <select wire:model.live="dateRange"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="today">Today</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="this_year">This Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead
                    class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3">Reference</th>
                        <th class="px-6 py-3">GCash Number</th>
                        <th class="px-6 py-3 text-center">Type</th>
                        <th class="px-6 py-3">Date & Time</th>
                        <th class="px-6 py-3 text-left">Amount</th>
                        <th class="px-6 py-3 text-left">Fee</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($transactions as $transaction)
                        <tr class="hover:bg-gray-50/50" wire:key="gcash-{{ $transaction->id }}">
                            <td class="px-6 py-4 font-mono text-xs font-medium text-indigo-600">
                                #{{ $transaction->reference }}
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $transaction->number }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if ($transaction->type === 'cash_in')
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Cash In
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        Cash Out
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $transaction->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="px-6 py-4 text-left font-bold text-gray-900">
                                ₱{{ number_format($transaction->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-left text-gray-700">
                                ₱{{ number_format($transaction->fee, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" wire:click="deleteTransaction({{ $transaction->id }})"
                                    wire:confirm="Are you sure you want to delete this transaction?"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition cursor-pointer">
                                    <x-lucide-trash class="w-4 h-4" />
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                No GCash transactions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($transactions->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Cash In / Cash Out -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ $type === 'cash_in' ? 'GCash Cash In' : 'GCash Cash Out' }}
                    </h3>
                    <button wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="processTransaction" class="mt-4 space-y-4">
                    <!-- Transaction Type Toggle -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1">
                            Transaction Type
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" wire:click="$set('type', 'cash_in')"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold transition-colors {{ $type === 'cash_in' ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' }}">
                                Cash In
                            </button>
                            <button type="button" wire:click="$set('type', 'cash_out')"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold transition-colors {{ $type === 'cash_out' ? 'border-rose-600 bg-rose-50 text-rose-700' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' }}">
                                Cash Out
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $type === 'cash_in' ? 'Customer gives you cash, you send GCash.' : 'Customer sends you GCash, you give cash.' }}
                        </p>
                        @error('type') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- GCash Number -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Customer GCash
                            Number</label>
                        <input type="text" wire:model="phoneNumber" placeholder="e.g. 09171234567"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('phoneNumber') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Quick Pick Amounts -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600 mb-1">Quick
                            Amount</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ([100, 200, 500, 1000, 2000, 5000] as $preset)
                                <button type="button" wire:click="selectAmount({{ $preset }})"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors {{ $amount == $preset ? 'border-indigo-600 bg-indigo-50 text-indigo-600' : 'border-gray-200 bg-gray-50 text-gray-700 hover:bg-gray-100' }}">
                                    ₱{{ number_format($preset) }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Amount & Fee -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Amount (₱)</label>
                            <input type="number" wire:model.live.debounce.400ms="amount" placeholder="0.00" step="1"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            @error('amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">Service Fee
                                (₱)</label>
                            <input type="number" wire:model="fee" placeholder="0.00" step="1"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                            @error('fee') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Reference Number -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-600">GCash Reference
                            No.</label>
                        <input type="text" wire:model="referenceNumber" placeholder="e.g. 1234 567 890123"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        @error('referenceNumber') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>