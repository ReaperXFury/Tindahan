<?php

use App\Models\GcashTransaction;
use App\Models\Load;
use App\Models\Sale;
use App\Models\SaleItem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layout.main'), Title('Dashboard')] class extends Component
{
    public function render()
    {
        $today = today();
        $yesterday = today()->subDay();

        // ---------- Sales (completed only) ----------
        $salesToday = (float) Sale::where('status', 'completed')
            ->whereDate('created_at', $today)->sum('total_amount');

        $salesYesterday = (float) Sale::where('status', 'completed')
            ->whereDate('created_at', $yesterday)->sum('total_amount');

        $salesChange = $salesYesterday > 0
            ? round((($salesToday - $salesYesterday) / $salesYesterday) * 100, 1)
            : null;

        $ordersToday = Sale::where('status', 'completed')
            ->whereDate('created_at', $today)->count();

        // ---------- Profit today = subtotal - (unit_cost * quantity) ----------
        $profitToday = (float) SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))
            ->whereDate('created_at', $today)
            ->selectRaw('COALESCE(SUM(subtotal - (unit_cost * quantity)), 0) as profit')
            ->value('profit');

        // ---------- GCash today ----------
        $gcash = GcashTransaction::whereDate('created_at', $today)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'cash_in'  THEN amount END), 0) as cash_in,
                COALESCE(SUM(CASE WHEN type = 'cash_out' THEN amount END), 0) as cash_out,
                COALESCE(SUM(fee), 0) as fees,
                COUNT(*) as total
            ")
            ->first();

        // ---------- Load today ----------
        $loadToday = (float) Load::whereDate('created_at', $today)->sum('amount');
        $loadCount = Load::whereDate('created_at', $today)->count();

        // ---------- Last 7 days chart ----------
        $start = today()->subDays(6);

        $totals = Sale::where('status', 'completed')
            ->where('created_at', '>=', $start->copy()->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $weekly = collect(range(0, 6))->map(function ($i) use ($start, $totals) {
            $date = $start->copy()->addDays($i);

            return [
                'label' => $date->format('D'),
                'date' => $date->format('M d'),
                'total' => (float) ($totals[$date->toDateString()] ?? 0),
            ];
        });

        $weeklyMax = max($weekly->max('total'), 1);
        $weeklyTotal = $weekly->sum('total');

        // ---------- Top products this month ----------
        $topProducts = SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('product_name, SUM(quantity) as qty, SUM(subtotal) as revenue')
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // ---------- Recent sales ----------
        $recentSales = Sale::latest()->limit(5)->get();

        return view('components.⚡dashboard', [
            'salesToday' => $salesToday,
            'salesChange' => $salesChange,
            'ordersToday' => $ordersToday,
            'profitToday' => $profitToday,
            'gcash' => $gcash,
            'loadToday' => $loadToday,
            'loadCount' => $loadCount,
            'weekly' => $weekly,
            'weeklyMax' => $weeklyMax,
            'weeklyTotal' => $weeklyTotal,
            'topProducts' => $topProducts,
            'recentSales' => $recentSales,
        ]);
    }
};
?>

<div class="space-y-6" wire:poll.30s>
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500">{{ now()->format('l, F j, Y') }} · Here's how your store is doing today.
            </p>
        </div>
        <a href="{{ route('pos') }}" wire:navigate.hover
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            New Sale
        </a>
    </div>

    @php
        $changeClass = $salesChange === null
            ? 'bg-gray-100 text-gray-600'
            : ($salesChange >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
        $changeText = $salesChange === null
            ? '—'
            : ($salesChange >= 0 ? '+' . $salesChange . '%' : $salesChange . '%');
    @endphp

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Sales Today -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Sales Today</span>
                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $changeClass }}">{{ $changeText }}</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($salesToday, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $ordersToday }} {{ Str::plural('order', $ordersToday) }} · vs. yesterday
            </p>
        </div>

        <!-- Profit Today -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Profit Today</span>
                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Net</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">₱{{ number_format($profitToday, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Selling price minus cost</p>
        </div>

        <!-- GCash Fees -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">GCash Fees Today</span>
                <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">GCash</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($gcash->fees, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $gcash->total }} {{ Str::plural('transaction', $gcash->total) }} today
            </p>
        </div>

        <!-- Load Today -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Load Today</span>
                <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">E-load</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">₱{{ number_format($loadToday, 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">
                {{ $loadCount }} {{ Str::plural('transaction', $loadCount) }} today
            </p>
        </div>
    </div>

    <!-- Chart + Top Products -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Last 7 Days -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Sales — Last 7 Days</h2>
                    <p class="text-xs text-gray-500">Completed sales per day</p>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-gray-900">₱{{ number_format($weeklyTotal, 2) }}</div>
                    <p class="text-xs text-gray-500">7-day total</p>
                </div>
            </div>

            <div class="mt-6 flex h-48 items-end gap-3">
                @foreach ($weekly as $day)
                    @php
                        $height = $day['total'] > 0 ? max(($day['total'] / $weeklyMax) * 100, 4) : 2;
                        $isToday = $loop->last;
                    @endphp
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-2"
                        title="{{ $day['date'] }}: ₱{{ number_format($day['total'], 2) }}">
                        <span class="text-[10px] font-medium text-gray-500">
                            {{ $day['total'] > 0 ? '₱' . number_format($day['total']) : '' }}
                        </span>
                        <div class="w-full rounded-t-md {{ $isToday ? 'bg-indigo-600' : 'bg-indigo-200' }}"
                            style="height: {{ $height }}%"></div>
                        <span class="text-xs {{ $isToday ? 'font-semibold text-indigo-600' : 'text-gray-500' }}">
                            {{ $day['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Top Products -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Top Products</h2>
            <p class="text-xs text-gray-500">Best sellers this month</p>

            <div class="mt-4 divide-y divide-gray-100">
                @forelse ($topProducts as $product)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600">
                                {{ $loop->iteration }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $product->product_name }}</p>
                                <p class="text-xs text-gray-500">{{ $product->qty }} sold</p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-gray-900">₱{{ number_format($product->revenue, 2) }}</span>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500">No sales yet this month.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Sales + GCash Today -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <!-- Recent Sales -->
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">Recent Sales</h2>
                <p class="text-xs text-gray-500">Latest 5 transactions</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Invoice</th>
                            <th class="px-5 py-3">Payment</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($recentSales as $sale)
                            @php
                                $badge = match ($sale->status) {
                                    'completed' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
                                    'pending' => ['bg-amber-50 text-amber-700', 'bg-amber-500'],
                                    'refunded' => ['bg-gray-100 text-gray-600', 'bg-gray-400'],
                                    default => ['bg-gray-100 text-gray-600', 'bg-gray-400'],
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/50" wire:key="recent-{{ $sale->id }}">
                                <td class="px-5 py-3 font-medium text-indigo-600">{{ $sale->invoice_number }}</td>
                                <td class="px-5 py-3 uppercase">{{ $sale->payment_method }}</td>
                                <td class="px-5 py-3">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge[0] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $badge[1] }}"></span>
                                        {{ ucfirst($sale->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-gray-500">{{ $sale->created_at->diffForHumans() }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                    ₱{{ number_format($sale->total_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">
                                    No sales recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- GCash Today -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">GCash Today</h2>
            <p class="text-xs text-gray-500">Cash in vs. cash out</p>

            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3">
                    <span class="text-sm font-medium text-emerald-700">Cash In</span>
                    <span class="text-sm font-bold text-emerald-700">₱{{ number_format($gcash->cash_in, 2) }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-rose-50 px-4 py-3">
                    <span class="text-sm font-medium text-rose-700">Cash Out</span>
                    <span class="text-sm font-bold text-rose-700">₱{{ number_format($gcash->cash_out, 2) }}</span>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
                    <span class="text-sm font-medium text-gray-700">Fees Earned</span>
                    <span class="text-sm font-bold text-gray-900">₱{{ number_format($gcash->fees, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>