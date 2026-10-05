<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\SaleItem;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layout.main'), Title('Sales')]
    class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $dateRange = 'this_month';

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedStatusFilter()
    {
        $this->resetPage();
    }
    public function updatedDateRange()
    {
        $this->resetPage();
    }

    public function deleteSale(int $saleId)
    {
        try {
            $product = SaleItem::findOrFail($saleId);
            $product->delete();

            $this->dispatch('notify', type: 'success', message: "Sales record was deleted.");
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Could not delete this sales record.');
        }
    }

    public function render()
    {
        // 1. Eager load relationships to prevent N+1 queries
        $query = SaleItem::query()->with(['sale', 'product']);

        // 2. Search across product names AND invoice numbers
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('product_name', 'like', '%' . $this->search . '%')
                    ->orWhereHas('sale', function ($sq) {
                        $sq->where('invoice_number', 'like', '%' . $this->search . '%');
                    });
            });
        }

        // 3. Filter status on parent sale relation
        if ($this->statusFilter !== 'all') {
            $query->whereHas('sale', fn($q) => $q->where('status', $this->statusFilter));
        }

        // 4. Apply Date Filter
        match ($this->dateRange) {
            'today' => $query->whereDate('created_at', now()->today()),
            'this_week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'this_month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'this_year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        // 5. Line Item Level Metrics
        $totalSalesAmount = (clone $query)->sum('subtotal');
        $totalUnitsSold = (clone $query)->sum('quantity');
        $totalCost = (clone $query)->sum(DB::raw('quantity * unit_cost'));
        $netProfit = $totalSalesAmount - $totalCost;
        $profitMargin = $totalSalesAmount > 0 ? ($netProfit / $totalSalesAmount) * 100 : 0;

        return view('components.⚡sales', [
            'sales' => $query->latest()->paginate(20),
            'totalSalesAmount' => $totalSalesAmount,
            'totalUnitsSold' => $totalUnitsSold,
            'totalCost' => $totalCost,
            'netProfit' => $netProfit,
            'profitMargin' => $profitMargin,
        ]);
    }
};
?>

<div class="space-y-6">
    <!-- Header & Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Sales Record Management</h1>
            <p class="text-sm text-gray-500">Monitor transactions, track revenue, and manage customer orders.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export CSV
            </button>
            <a href="{{ route('pos') }}" wire:navigate.hover
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Sale
            </a>
        </div>
    </div>

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Card 1: Total Revenue -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Revenue</span>
                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Gross</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($totalSalesAmount, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Filtered item revenue</p>
        </div>

        <!-- Card 2: Units Sold -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Items Sold</span>
                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Qty</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">{{ number_format($totalUnitsSold) }}</div>
            <p class="mt-1 text-xs text-gray-500">Total quantity moved</p>
        </div>

        <!-- Card 3: Cost of Goods (COGS) -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Cost (COGS)</span>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Expenses</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($totalCost, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Based on unit cost</p>
        </div>

        <!-- Card 4: Net Profit & Margin -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Net Profit</span>
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                    {{ number_format($profitMargin, 1) }}% Margin
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">₱{{ number_format($netProfit, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Revenue minus unit cost</p>
        </div>
    </div>

    <!-- Filters & Table Section -->
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">

        <!-- Filter Bar -->
        <div class="flex flex-col gap-3 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative flex-1 max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search product or invoice..."
                    class="w-full rounded-lg border border-gray-300 bg-white pl-9 pr-4 py-2 text-sm placeholder-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="statusFilter"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="all">All Statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="refunded">Refunded</option>
                </select>

                <select wire:model.live="dateRange"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <option value="today">Today</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="this_year">This Year</option>
                </select>
            </div>
        </div>

        <!-- Sales Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead
                    class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3">Invoice</th>
                        <th class="px-6 py-3">Product</th>
                        <th class="px-6 py-3">Date & Time</th>
                        <th class="px-6 py-3 text-center">Payment Method</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-left ">Subtotal</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($sales as $sale)
                        <tr class="hover:bg-gray-50/50" wire:key="item-{{ $sale->id }}">
                            <td class="px-6 py-4 font-medium text-indigo-600">
                                #{{ $sale->sale?->invoice_number ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    @if (!empty($sale->product?->image_path))
                                        <img src="{{ asset('storage/' . $sale->product->image_path) }}"
                                            alt="{{ $sale->product_name }}"
                                            class="h-10 w-10 shrink-0 rounded-lg border border-slate-200 object-cover shadow-sm">
                                    @else
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-400">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif

                                    <div class="flex flex-col min-w-0">
                                        <span class="truncate font-medium text-slate-900 text-sm"
                                            title="{{ $sale->product_name }}">
                                            {{ $sale->product_name }}
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            {{ $sale->quantity }} x ₱{{ number_format($sale->unit_price, 2) }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                                {{ $sale->created_at->format('M d, Y h:i A') }}
                            </td>
                            <td class="px-6 py-4 text-center uppercase text-xs font-semibold text-gray-700 ">
                                {{ $sale->sale?->payment_method ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $status = $sale->sale?->status ?? 'completed';
                                @endphp
                                @if ($status === 'completed')
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Completed
                                    </span>
                                @elseif ($status === 'pending')
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Pending
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> {{ ucfirst($status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-left font-medium text-gray-900">
                                ₱{{ number_format($sale->subtotal, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" wire:click="deleteSale({{ $sale->id }})"
                                    wire:confirm="Are you sure you want to delete this product?"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition cursor-pointer">
                                    <x-lucide-trash class="w-4 h-4" />
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                No sales records found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Dynamic Livewire Pagination -->
        <div class="border-t border-gray-200 px-6 py-4">
            {{ $sales->links() }}
        </div>

    </div>
</div>