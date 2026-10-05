<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Products;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layout.main'), Title('POS')]
    class extends Component {

    public string $search = '';
    public string $category = 'All';
    public string $paymentMethod = 'cash';   // cash | gcash 
    public string $amountReceived = '';

    // TODO: replace the sample data below with your real products and cart
    public array $categories = ['All', 'Snacks', 'Beverages', 'Noodles', 'Canned Goods', 'Condiments', 'Toiletries', 'Others'];

    #[Computed]
    public function products()
    {
        return Products::query()
            ->when($this->search, fn($q) => $q->where(fn($q) => $q
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('sku', 'like', '%' . $this->search . '%')))
            ->when($this->category !== 'All', fn($q) => $q->where('category', $this->category))
            ->orderBy('name')
            ->limit(60)
            ->get();
    }

    public array $cart = [];

    // TODO: your logic goes in these methods
    public function addToCart(int $productId)
    {
        $product = Products::findOrFail($productId);
        $qty = $this->cart[$productId] ?? 0;

        if ($qty + 1 > $product->stock) {
            $this->dispatch('notify', type: 'error', message: 'Not enough stock for ' . $product->name);
            return;
        }

        $this->cart[$productId] = $qty + 1;
    }

    public function increment(int $productId)
    {
        $this->addToCart($productId);
    }

    public function decrement(int $productId)
    {
        if (!isset($this->cart[$productId]))
            return;

        $this->cart[$productId]--;
        if ($this->cart[$productId] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function removeItem(int $productId)
    {
        unset($this->cart[$productId]);
    }

    public function clearCart()
    {
        $this->reset(['cart', 'amountReceived']);
    }

    #[Computed]
    public function cartItems()
    {
        if (empty($this->cart))
            return collect();

        $products = Products::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)
            ->filter(fn($qty, $id) => $products->has($id))
            ->map(fn($qty, $id) => [
                'id' => $id,
                'name' => $products[$id]->name,
                'price' => (float) $products[$id]->selling_price,
                'qty' => $qty,
                'image_path' => $products[$id]->image_path,
            ])
            ->values();
    }

    #[Computed]
    public function total(): float
    {
        return round($this->cartItems->sum(fn($i) => $i['price'] * $i['qty']), 2);
    }

    public function checkout()
    {
        if (empty($this->cart))
            return;

        try {
            $sale = DB::transaction(function () {
                $products = Products::whereIn('id', array_keys($this->cart))
                    ->lockForUpdate()->get()->keyBy('id');

                $lines = [];
                $total = 0;

                foreach ($this->cart as $id => $qty) {
                    $p = $products[$id] ?? null;

                    if (!$p) {
                        throw new \RuntimeException('An item in the cart no longer exists.');
                    }
                    if ($p->stock < $qty) {
                        throw new \RuntimeException("Not enough stock for {$p->name}.");
                    }

                    $lineTotal = round($p->selling_price * $qty, 2);
                    $total += $lineTotal;
                    $lines[] = [$p, $qty, $lineTotal];
                }

                $total = round($total, 2);
                $paid = $this->paymentMethod === 'cash' ? (float) $this->amountReceived : $total;

                if ($paid < $total) {
                    throw new \RuntimeException('Amount received is less than the total.');
                }

                $sale = Sale::create([
                    'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                    'user_id' => auth()->id(),
                    'subtotal' => $total,
                    'total_amount' => $total,
                    'payment_method' => $this->paymentMethod,
                    'amount_paid' => $paid,
                    'change_amount' => round($paid - $total, 2),
                ]);

                foreach ($lines as [$p, $qty, $lineTotal]) {
                    $sale->items()->create([
                        'product_id' => $p->id,
                        'product_name' => $p->name,           // snapshot
                        'quantity' => $qty,
                        'unit_price' => $p->selling_price,  // snapshot
                        'unit_cost' => $p->cost_price,     // snapshot, for profit later
                        'subtotal' => $lineTotal,
                    ]);

                    $p->decrement('stock', $qty);
                }

                return $sale;

            });
        } catch (\RuntimeException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
            return;
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Could not complete the sale.');
            return;
        }

        $this->reset(['cart', 'amountReceived']);
        $this->dispatch('notify', type: 'success', message: "Sale {$sale->invoice_number} completed.");
    }
};
?>

@php
    $total = $this->total;
    $subtotal = $total;
    $itemCount = $this->cartItems->sum('qty');
    $received = (float) ($amountReceived ?: 0);
    $change = $received - $total;
    $canCharge = $itemCount > 0 && ($paymentMethod !== 'cash' || $received >= $total);
@endphp

<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Point of Sale</h1>
            <p class="text-sm text-slate-500">Pick items, then take payment.</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem] items-start">

        {{-- ============ LEFT: product browser ============ --}}
        <section class="space-y-4 min-w-0" aria-label="Products">

            {{-- Search --}}
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <x-lucide-search class="w-5 h-5" />
                </div>
                <input wire:model.live.debounce.300ms="search" type="search"
                    placeholder="Search by name or scan a barcode..."
                    class="block w-full pl-10 pr-3 py-3 border border-slate-300 rounded-xl text-sm placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
            </div>

            {{-- Category chips --}}
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($categories as $cat)
                    <button type="button" wire:click="$set('category', '{{ $cat }}')" @class([
                        'shrink-0 px-4 py-2 rounded-full text-sm font-semibold border transition cursor-pointer',
                        'bg-emerald-600 text-white border-emerald-600' => $category === $cat,
                        'bg-white text-slate-600 border-slate-300 hover:bg-slate-100' => $category !== $cat,
                    ])>
                        {{ $cat }}
                    </button>
                @endforeach
            </div>

            {{-- Product grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach ($this->products as $p)
                    @php $out = $p['stock'] <= 0; @endphp
                    <button type="button" wire:key="p-{{ $p['id'] }}" wire:click="addToCart({{ $p['id'] }})" @disabled($out)
                        class="group relative text-left bg-white border border-slate-200 rounded-2xl p-3 transition enabled:hover:border-emerald-500 enabled:hover:shadow-md enabled:active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-600">

                        <div class="grid place-items-center h-24 rounded-xl bg-slate-100 text-slate-400 overflow-hidden">
                            @if ($p['image_path'])
                                <img src="{{ asset('storage/' . $p['image_path']) }}" alt="" class="h-full w-full object-cover">
                            @else
                                <x-lucide-package class="w-8 h-8" />
                            @endif
                        </div>

                        <p class="mt-3 text-sm font-semibold text-slate-900 line-clamp-2 min-h-[2.5rem]">{{ $p['name'] }}
                        </p>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span
                                class="font-bold text-emerald-700 tabular-nums">₱{{ number_format($p['selling_price'], 2) }}</span>
                            @if ($out)
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700">Out</span>
                            @elseif ($p['stock'] <= 5)
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">{{ $p['stock'] }}
                                    left</span>
                            @else
                                <span class="text-[11px] text-slate-400 tabular-nums">{{ $p['stock'] }}</span>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            {{-- Empty state (show when your filtered product list is empty) --}}
            @if ($this->products->isEmpty())
                <div class="bg-white border border-slate-200 rounded-2xl py-14 text-center">
                    <div class="mx-auto grid place-items-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600">
                        <x-lucide-search-x class="w-6 h-6" />
                    </div>
                    <h2 class="mt-4 font-semibold text-slate-900">No products found</h2>
                    <p class="mt-1 text-sm text-slate-500">Try a different search or category.</p>
                </div>
            @endif
        </section>

        {{-- ============ RIGHT: cart & payment ============ --}}
        <aside aria-label="Current sale"
            class="bg-white border border-slate-200 rounded-2xl shadow-xs flex flex-col overflow-hidden lg:sticky lg:top-6 lg:h-[calc(100vh-3rem)]">

            {{-- Header --}}
            <div class="shrink-0 flex items-center justify-between px-5 py-3.5 border-b border-slate-200">
                <h2 class="font-bold text-slate-900 flex items-center gap-2">
                    <x-lucide-shopping-cart class="w-5 h-5 text-emerald-600" />
                    Current sale
                    <span
                        class="px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 tabular-nums">{{ $itemCount }}</span>
                </h2>
                @if ($itemCount > 0)
                    <button type="button" wire:click="clearCart"
                        class="text-xs font-semibold text-slate-500 hover:text-red-600 transition cursor-pointer">Clear</button>
                @endif
            </div>

            {{-- Items: the only part that scrolls --}}
            <div class="flex-1 min-h-0 max-h-80 lg:max-h-none overflow-y-auto divide-y divide-slate-100">
                @forelse ($this->cartItems as $item)
                    <div wire:key="cart-{{ $item['id'] }}" class="flex items-center gap-3 px-5 py-2.5">
                        <div
                            class="grid place-items-center w-10 h-10 shrink-0 rounded-lg bg-slate-100 text-slate-400 overflow-hidden">
                            @if ($item['image_path'])
                                <img src="{{ asset('storage/' . $item['image_path']) }}" alt=""
                                    class="h-full w-full object-cover">
                            @else
                                <x-lucide-package class="w-5 h-5" />
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $item['name'] }}</p>
                            <p class="text-xs text-slate-500 tabular-nums">₱{{ number_format($item['price'], 2) }} each</p>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <span
                                class="text-sm font-bold text-slate-900 tabular-nums">₱{{ number_format($item['price'] * $item['qty'], 2) }}</span>

                            <div class="flex items-center gap-1.5">
                                <div class="inline-flex items-center rounded-lg border border-slate-300">
                                    <button type="button" wire:click="decrement({{ $item['id'] }})"
                                        aria-label="Decrease quantity"
                                        class="grid place-items-center w-6 h-6 text-slate-600 hover:bg-slate-100 rounded-l-lg cursor-pointer">
                                        <x-lucide-minus class="w-3 h-3" />
                                    </button>
                                    <span
                                        class="w-7 text-center text-xs font-semibold tabular-nums">{{ $item['qty'] }}</span>
                                    <button type="button" wire:click="increment({{ $item['id'] }})"
                                        aria-label="Increase quantity"
                                        class="grid place-items-center w-6 h-6 text-slate-600 hover:bg-slate-100 rounded-r-lg cursor-pointer">
                                        <x-lucide-plus class="w-3 h-3" />
                                    </button>
                                </div>
                                <button type="button" wire:click="removeItem({{ $item['id'] }})"
                                    aria-label="Remove {{ $item['name'] }}"
                                    class="p-1 rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 transition cursor-pointer">
                                    <x-lucide-trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="h-full min-h-[7rem] grid place-content-center px-5 py-6 text-center">
                        <div class="mx-auto grid place-items-center w-11 h-11 rounded-xl bg-slate-100 text-slate-400">
                            <x-lucide-shopping-basket class="w-5 h-5" />
                        </div>
                        <p class="mt-2 text-sm font-semibold text-slate-700">No items yet</p>
                        <p class="text-xs text-slate-500">Tap a product to add it.</p>
                    </div>
                @endforelse
            </div>

            {{-- Payment footer: always fully visible --}}
            <div class="shrink-0 border-t border-slate-200 bg-slate-50/70 p-4 space-y-3">

                {{-- Total --}}
                <div class="flex items-baseline justify-between">
                    <span class="text-sm font-semibold text-slate-600">Total</span>
                    <span
                        class="text-3xl font-extrabold text-slate-900 tabular-nums">₱{{ number_format($total, 2) }}</span>
                </div>

                {{-- Payment method: one compact row --}}
                <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-slate-200/70" role="radiogroup"
                    aria-label="Payment method">
                    @foreach ([['cash', 'Cash', 'lucide-banknote'], ['gcash', 'GCash', 'lucide-wallet']] as [$key, $label, $icon])
                                <button type="button" role="radio" aria-checked="{{ $paymentMethod === $key ? 'true' : 'false' }}"
                                    wire:click="$set('paymentMethod', '{{ $key }}')" @class([
                                        'flex items-center justify-center gap-1.5 py-2 rounded-lg text-sm font-semibold transition cursor-pointer',
                                        'bg-white text-emerald-700 shadow-sm' => $paymentMethod === $key,
                                        'text-slate-600 hover:text-slate-900' => $paymentMethod !== $key,
                                    ])>
                          <x-dynamic-component :component="$icon" class="w-4 h-4" />
                                    {{ $label }}
                                </button>
                    @endforeach
                </div>

                {{-- Cash: amount, quick amounts, change --}}
                @if ($paymentMethod === 'cash')
                    <div class="space-y-2">
                        <div class="relative">
                            <span
                                class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 font-medium">₱</span>
                            <input wire:model.live.debounce.300ms="amountReceived" id="received" type="number"
                                inputmode="decimal" min="0" step="0.01" placeholder="Amount received"
                                aria-label="Amount received"
                                class="block w-full pl-8 pr-3 py-2 border border-slate-300 rounded-xl bg-white text-base font-semibold tabular-nums placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
                        </div>

                        <div class="grid grid-cols-6 gap-1.5">
                            <button type="button" wire:click="$set('amountReceived', '{{ $total }}')"
                                class="py-1.5 rounded-lg bg-emerald-100 text-emerald-800 text-[11px] font-bold hover:bg-emerald-200 transition cursor-pointer">Exact</button>
                            @foreach ([50, 100, 200, 500, 1000] as $amt)
                                <button type="button" wire:click="$set('amountReceived', '{{ $amt }}')"
                                    class="py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 text-[11px] font-semibold hover:bg-slate-100 transition tabular-nums cursor-pointer">{{ number_format($amt) }}</button>
                            @endforeach
                        </div>

                        <div @class([
                            'flex items-center justify-between rounded-xl px-4 py-2 text-sm font-semibold',
                            'bg-emerald-100 text-emerald-800' => $change >= 0 && $received > 0,
                            'bg-slate-200/70 text-slate-500' => $received <= 0,
                            'bg-red-100 text-red-700' => $change < 0 && $received > 0,
                        ])>
                        <span>{{ $change < 0 && $received > 0 ? 'Short by' : 'Change' }}</span>
                            <span
                                class="text-lg font-extrabold tabular-nums">₱{{ number_format(abs($received > 0 ? $change : 0), 2) }}</span>
                        </div>
                    </div>
                @endif

                {{-- Charge --}}
                <button type="button" wire:click="checkout" wire:loading.attr="disabled" wire:target="checkout"
                    @disabled(!$canCharge)
                    class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl text-base font-bold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:text-slate-500 disabled:cursor-not-allowed transition cursor-pointer">
                    <x-lucide-circle-check class="w-5 h-5" />
                    <span wire:loading.remove wire:target="checkout">Charge ₱{{ number_format($total, 2) }}</span>
                    <span wire:loading wire:target="checkout">Processing...</span>
                </button>
            </div>
        </aside>
    </div>
</div>