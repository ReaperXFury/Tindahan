<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Component;
use App\Models\Products;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new #[Layout('components.layout.main'), Title('Products')]
    class extends Component {
    use WithFileUploads, WithPagination;

    public bool $showModal = false;
    public string $search = '';

    public string $sku = '';
    public string $name = '';
    public string $category = '';
    public string $cost_price = '';
    public string $selling_price = '';
    public string $stock = '';
    public ?string $expiration_date = null;
    public $image = null;
    public ?int $editingId = null;
    public ?string $existingImage = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function closeModal()
    {
        $this->resetValidation();
        $this->reset(['showModal', 'editingId', 'existingImage', 'sku', 'name', 'category', 'cost_price', 'selling_price', 'stock', 'expiration_date', 'image']);
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->editingId)],
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'expiration_date' => 'nullable|date',
            'image' => 'nullable|image|max:5120',
        ];
    }


    public function editProduct(int $productId)
    {
        $this->resetValidation();
        $product = Products::findOrFail($productId);

        $this->editingId = $product->id;
        $this->sku = $product->sku;
        $this->name = $product->name;
        $this->category = $product->category ?? '';
        $this->cost_price = (string) $product->cost_price;
        $this->selling_price = (string) $product->selling_price;

        $this->stock = (string) $product->stock;
        $this->expiration_date = $product->expiration_date
            ? \Carbon\Carbon::parse($product->expiration_date)->format('Y-m-d')
            : null;
        $this->existingImage = $product->image_path;
        $this->image = null;

        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();   // outside the try, so validation errors still show inline

        $isEditing = (bool) $this->editingId;
        $newPath = null;

        try {
            $data = [
                'sku' => $this->sku,
                'name' => $this->name,
                'category' => $this->category ?: null,
                'cost_price' => $this->cost_price,
                'selling_price' => $this->selling_price,
                'stock' => $this->stock,
                'expiration_date' => $this->expiration_date ?: null,
            ];

            if ($this->image) {
                $newPath = $this->image->store('product_images', 'public');
                $data['image_path'] = $newPath;
            }

            if ($isEditing) {
                Products::findOrFail($this->editingId)->update($data);

                // delete the old photo only after the update succeeded
                if ($newPath && $this->existingImage) {
                    Storage::disk('public')->delete($this->existingImage);
                }
            } else {
                Products::create($data);
            }
        } catch (\Throwable $e) {
            report($e);

            if ($newPath) {
                Storage::disk('public')->delete($newPath);   // don't leave an orphan file
            }

            $this->dispatch('notify', type: 'error', message: 'Could not save the product. Please try again.');
            return;
        }

        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: $isEditing ? 'Product updated.' : 'Product added.');
    }

    public function deleteProduct(int $productId)
    {
        try {
            $product = Products::findOrFail($productId);
            $name = $product->name;

            $product->delete();

            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $this->dispatch('notify', type: 'success', message: "{$name} was deleted.");
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: 'Could not delete this product.');
        }
    }

    public function render()
    {
        $base = Products::query()
            ->when($this->search, fn($q) => $q->where(fn($q) => $q
                ->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('sku', 'like', '%' . $this->search . '%')));

        // One aggregate query over the whole filtered set (not just the current page)
        $totals = (clone $base)
            ->selectRaw('
                COALESCE(SUM(selling_price * stock), 0) as total_value,
                COALESCE(SUM(cost_price * stock), 0) as total_cost,
                COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock
            ')
            ->first();

        $totalValue = (float) $totals->total_value;
        $totalCost = (float) $totals->total_cost;
        $netProfit = $totalValue - $totalCost;
        $margin = $totalValue > 0 ? ($netProfit / $totalValue) * 100 : 0;

        $stats = [
            'total_value' => $totalValue,
            'total_cost' => $totalCost,
            'net_profit' => $netProfit,
            'margin' => $margin,
            'out_of_stock' => (int) $totals->out_of_stock,
        ];

        $products = $base->latest()->paginate(10);

        return view('components.⚡products', compact('products', 'stats'));
    }
};
?>
<div class="space-y-6">

    {{-- Page header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Products</h1>
            <p class="text-sm text-slate-500">Manage your items, prices, and stock levels.</p>
        </div>

        <button type="button" wire:click="$set('showModal', true)"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition cursor-pointer">
            <x-lucide-plus class="w-4 h-4" />
            Add product
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Card 1: Total Value -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Value</span>
                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Gross</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 tabular-nums">
                ₱{{ number_format($stats['total_value'], 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Selling price × stock</p>
        </div>

        <!-- Card 2: COGS -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Cost (COGS)</span>
                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Expenses</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 tabular-nums">
                ₱{{ number_format($stats['total_cost'], 2) }}</div>
            <p class="mt-1 text-xs text-gray-500">Cost price × stock</p>
        </div>

        <!-- Card 3: Out of stock -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Out of stock</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-medium',
                    'bg-red-50 text-red-700' => $stats['out_of_stock'] > 0,
                    'bg-emerald-50 text-emerald-700' => $stats['out_of_stock'] === 0,
                ])>
      {{ $stats['out_of_stock'] > 0 ? 'Restock' : 'All good' }}
                </span>
            </div>
            <div @class([
                'mt-2 text-2xl font-bold tabular-nums',
                'text-red-600' => $stats['out_of_stock'] > 0,
                'text-gray-900' => $stats['out_of_stock'] === 0,
            ])>
     {{ number_format($stats['out_of_stock']) }}
            </div>
            <p class="mt-1 text-xs text-gray-500">
                {{ \Illuminate\Support\Str::plural('Product', $stats['out_of_stock']) }} with 0 stock
            </p>
        </div>

        <!-- Card 4: Net Profit & Margin -->
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-wider text-gray-500">Net Profit</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-medium tabular-nums',
                    'bg-emerald-50 text-emerald-700' => $stats['net_profit'] >= 0,
                    'bg-red-50 text-red-700' => $stats['net_profit'] < 0,
                ])>
                    {{ number_format($stats['margin'], 1) }}% Margin
                </span>
            </div>
            <div @class([
                'mt-2 text-2xl font-bold tabular-nums',
                'text-emerald-600' => $stats['net_profit'] >= 0,
                'text-red-600' => $stats['net_profit'] < 0,
            ])>
       {{ $stats['net_profit'] < 0 ? '-' : '' }}₱{{ number_format(abs($stats['net_profit']), 2) }}
            </div>
            <p class="mt-1 text-xs text-gray-500">Value minus cost, if all stock sells</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative max-w-sm">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <x-lucide-search class="w-5 h-5" />
        </div>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search products..."
            class="block w-full pl-10 pr-3 py-2.5 border border-slate-300 rounded-xl text-sm placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600">
    </div>

    {{-- Table --}}
    @if ($products->isEmpty())
        <div class="bg-white border border-slate-200 rounded-2xl py-16 text-center">
            <div class="mx-auto grid place-items-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600">
                <x-lucide-boxes class="w-6 h-6" />
            </div>
            <h2 class="mt-4 font-semibold text-slate-900">
                {{ $search ? 'No matching products' : 'No products yet' }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ $search ? 'Try a different search.' : 'Add your first product to start selling.' }}
            </p>
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">

            {{-- Card header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200">
                <p class="text-sm font-semibold text-slate-900">
                    All products
                    <span
                        class="ml-1.5 px-2 py-0.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 tabular-nums">
                        {{ $products->total() }}
                    </span>
                </p>
                <p class="hidden sm:block text-xs text-slate-500 tabular-nums">
                    Showing {{ $products->firstItem() }}–{{ $products->lastItem() }}
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr
                            class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <th scope="col" class="px-5 py-3 font-semibold">Product</th>
                            <th scope="col" class="px-5 py-3 font-semibold hidden md:table-cell">Category</th>
                            <th scope="col" class="px-5 py-3 font-semibold text-right">Cost Price</th>
                            <th scope="col" class="px-5 py-3 font-semibold text-right">Selling Price</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Stock</th>
                            <th scope="col" class="px-5 py-3 font-semibold hidden lg:table-cell">Expires</th>
                            <th scope="col" class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($products as $product)
                            @php
                                $days = $product->expiration_date
                                    ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($product->expiration_date)->startOfDay(), false)
                                    : null;
                            @endphp

                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-emerald-50/40 transition-colors">

                                {{-- Product: photo, name, SKU --}}
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3.5">
                                        @if ($product->image_path)
                                            <img src="{{ asset('storage/' . $product->image_path) }}" alt=""
                                                class="w-11 h-11 shrink-0 rounded-xl object-cover border border-slate-200">
                                        @else
                                            <span
                                                class="grid place-items-center w-11 h-11 shrink-0 rounded-xl bg-slate-100 text-slate-400">
                                                <x-lucide-package class="w-5 h-5" />
                                            </span>
                                        @endif

                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900 truncate max-w-[16rem]">{{ $product->name }}
                                            </p>
                                            <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500 font-mono">
                                                <x-lucide-scan-barcode class="w-3.5 h-3.5 shrink-0" />
                                                {{ $product->sku }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Category --}}
                                <td class="px-5 py-3.5 hidden md:table-cell">
                                    @if ($product->category)
                                        <span
                                            class="inline-block px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-medium text-slate-700">
                                            {{ $product->category }}
                                        </span>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>

                                {{-- Cost Price --}}
                                <td class="px-5 py-3.5 text-center font-semibold text-slate-900 tabular-nums whitespace-nowrap">
                                    ₱{{ number_format($product->cost_price, 2) }}
                                </td>

                                {{-- Selling Price --}}
                                <td class="px-5 py-3.5 text-center font-semibold text-slate-900 tabular-nums whitespace-nowrap">
                                    ₱{{ number_format($product->selling_price, 2) }}
                                </td>

                                {{-- Stock --}}
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    @if ($product->stock <= 0)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-50 text-xs font-semibold text-red-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Out of stock
                                        </span>
                                    @elseif ($product->stock <= 5)
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-xs font-semibold text-amber-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Low · {{ $product->stock }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {{ $product->stock }} in
                                            stock
                                        </span>
                                    @endif
                                </td>

                                {{-- Expiration --}}
                                <td class="px-5 py-3.5 hidden lg:table-cell whitespace-nowrap">
                                    @if ($days === null)
                                        <span class="text-slate-300">—</span>
                                    @else
                                        <p @class([
                                            'text-sm',
                                            'text-red-600 font-semibold' => $days < 0,
                                            'text-amber-600 font-semibold' => $days >= 0 && $days <= 30,
                                            'text-slate-600' => $days > 30,
                                        ])>
                                            {{ \Carbon\Carbon::parse($product->expiration_date)->format('M d, Y') }}
                                        </p>
                                        @if ($days < 0)
                                            <p class="text-xs text-red-500">Expired</p>
                                        @elseif ($days <= 30)
                                            <p class="text-xs text-amber-600">{{ $days === 0 ? 'Expires today' : "In {$days} days" }}
                                            </p>
                                        @endif
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <button type="button" wire:click="editProduct({{ $product->id }})"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition cursor-pointer">
                                        <x-lucide-edit class="w-4 h-4" />
                                        Edit
                                    </button>

                                    <button type="button" wire:click="deleteProduct({{ $product->id }})"
                                        wire:confirm="Are you sure you want to delete this product?"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition cursor-pointer">
                                        <x-lucide-trash class="w-4 h-4" />
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50/60">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Add Product Modal --}}
    @php
        $input = 'block w-full py-2.5 border border-slate-300 rounded-xl text-sm placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600';
        $label = 'block text-sm font-medium text-slate-700';
    @endphp

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" role="dialog"
            aria-modal="true" aria-labelledby="add-product-title" wire:keydown.escape.window="closeModal">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-slate-900/50" wire:click="closeModal" aria-hidden="true"></div>

            {{-- Panel --}}
            <div
                class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-2xl sm:rounded-2xl shadow-xl">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-slate-200">
                    <div>
                        <h2 id="add-product-title" class="text-lg font-bold text-slate-900">
                            {{ $editingId ? 'Edit product' : 'Add product' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ $editingId ? 'Update the details of this item.' : 'Fill in the details of the new item.' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" aria-label="Close"
                        class="p-1.5 -mr-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                        <x-lucide-x class="w-5 h-5" />
                    </button>
                </div>

                <form wire:submit.prevent="save" class="flex flex-col min-h-0">

                    {{-- Body (scrolls on small screens) --}}
                    <div class="px-6 py-5 space-y-5 overflow-y-auto">

                        {{-- Image --}}
                        <div>
                            <span class="{{ $label }}">Product photo <span
                                    class="text-slate-400 font-normal">(optional)</span></span>

                            <label for="image"
                                class="mt-1 flex flex-col items-center justify-center gap-2 h-36 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 hover:border-emerald-500 hover:bg-emerald-50/40 text-slate-500 transition cursor-pointer overflow-hidden">
                                @if ($image)
                                    <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-full w-full object-contain">
                                @elseif ($existingImage)
                                    <img src="{{ asset('storage/' . $existingImage) }}" alt="Current photo"
                                        class="h-full w-full object-contain">
                                @else
                                    <x-lucide-image-plus class="w-7 h-7" />
                                    <span class="text-sm"><span class="font-semibold text-emerald-600">Click to
                                            upload</span></span>
                                    <span class="text-xs text-slate-400">PNG or JPG, up to 5 MB</span>
                                @endif
                            </label>
                            <input wire:model="image" id="image" type="file" accept="image/*" class="sr-only">

                            <div wire:loading wire:target="image" class="mt-1.5 text-xs text-slate-500">Uploading...
                            </div>
                            @error('image')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Name --}}
                        <div>
                            <label for="name" class="{{ $label }}">Product name</label>
                            <div class="mt-1 relative">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <x-lucide-package class="w-5 h-5" />
                                </div>
                                <input wire:model="name" id="name" type="text" placeholder="e.g. Lucky Me Pancit Canton"
                                    required class="{{ $input }} pl-10 pr-3">
                            </div>
                            @error('name')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- SKU + Category --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="sku" class="{{ $label }}">SKU / Barcode</label>
                                <div class="mt-1 relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <x-lucide-scan-barcode class="w-5 h-5" />
                                    </div>
                                    <input wire:model="sku" id="sku" type="text" placeholder="Scan or type" required
                                        class="{{ $input }} pl-10 pr-3">
                                </div>
                                @error('sku')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="category" class="{{ $label }}">Category</label>
                                <div class="mt-1 relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                        <x-lucide-tag class="w-5 h-5" />
                                    </div>
                                    <input wire:model="category" id="category" type="text" list="category-options"
                                        placeholder="Pick or type" class="{{ $input }} pl-10 pr-3">
                                    <datalist id="category-options">
                                        <option value="Snacks"></option>
                                        <option value="Beverages"></option>
                                        <option value="Noodles"></option>
                                        <option value="Canned Goods"></option>
                                        <option value="Condiments"></option>
                                        <option value="Toiletries"></option>
                                        <option value="Others"></option>
                                    </datalist>
                                </div>
                                @error('category')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Price + Stock --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <div>
                                <label for="cost_price" class="{{ $label }}">Cost price</label>
                                <div class="mt-1 relative">
                                    <span
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm font-medium">₱</span>
                                    <input wire:model="cost_price" id="cost_price" type="number" inputmode="decimal" min="0"
                                        step="0.01" placeholder="0.00" required class="{{ $input }} pl-8 pr-3 tabular-nums">
                                </div>
                                @error('cost_price')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="selling_price" class="{{ $label }}">Selling price</label>
                                <div class="mt-1 relative">
                                    <span
                                        class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm font-medium">₱</span>
                                    <input wire:model="selling_price" id="selling_price" type="number" inputmode="decimal"
                                        min="0" step="0.01" placeholder="0.00" required
                                        class="{{ $input }} pl-8 pr-3 tabular-nums">
                                </div>
                                @error('selling_price')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="stock" class="{{ $label }}">Stock on hand</label>
                                <div class="mt-1 relative">
                                    <input wire:model="stock" id="stock" type="number" inputmode="numeric" min="0" step="1"
                                        placeholder="0" required class="{{ $input }} px-3 tabular-nums">
                                </div>
                                @error('stock')
                                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Expiration --}}
                        <div>
                            <label for="expiration_date" class="{{ $label }}">Expiration date <span
                                    class="text-slate-400 font-normal">(optional)</span></label>
                            <div class="mt-1">
                                <input wire:model="expiration_date" id="expiration_date" type="date"
                                    class="{{ $input }} px-3">
                            </div>
                            @error('expiration_date')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div
                        class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 px-6 py-4 border-t border-slate-200 bg-slate-50 sm:rounded-b-2xl">
                        <button type="button" wire:click="closeModal"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 transition cursor-pointer">
                            <x-lucide-check class="w-4 h-4" />
                            <span wire:loading.remove wire:target="save">Save product</span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>