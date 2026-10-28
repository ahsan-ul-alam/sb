<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Selection;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Endpoints used by the admin role. */
class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'products' => Product::count(),
            'pending' => PurchaseRequest::where('status', 'pending')->count(),
            'requests' => PurchaseRequest::count(),
            'selections' => Selection::distinct('user_id')->count('user_id'),
        ]);
    }

    public function products(): JsonResponse
    {
        return response()->json(Product::latest()->get());
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $product = Product::create($this->validated($request));

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $data = $this->validated($request);
        if (isset($data['image_path']) && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->update($data);

        return response()->json($product->fresh());
    }

    public function destroyProduct(Product $product): JsonResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->delete();

        return response()->json(['ok' => true]);
    }

    public function selections(): JsonResponse
    {
        $rows = Selection::with(['user:id,name,email', 'product'])->get()
            ->filter(fn ($s) => $s->user && $s->product)
            ->groupBy('user_id')
            ->map(fn ($g) => [
                'user' => $g->first()->user->only('id', 'name', 'email'),
                'updated_at' => $g->max('updated_at'),
                'items' => $g->map(fn ($s) => ['product' => $s->product, 'quantity' => $s->quantity])->values(),
                'total' => $g->sum(fn ($s) => $s->product->price * $s->quantity),
            ])->sortByDesc('updated_at')->values();

        return response()->json($rows);
    }

    public function requests(Request $request): JsonResponse
    {
        $list = PurchaseRequest::with(['items', 'user'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->get()
            ->map(fn ($r) => app(ShopController::class)->payload($r));

        return response()->json($list);
    }

    public function updateRequest(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PurchaseRequest::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $changed = $purchaseRequest->status !== $data['status'];
        $purchaseRequest->update($data);

        if ($changed) {
            $purchaseRequest->user->notify(new ActivityNotification(
                'Request #'.$purchaseRequest->id.' is now '.$data['status'],
                $data['admin_note'] ?? 'Your purchase request status was updated.',
                $purchaseRequest->id,
            ));
        }

        return response()->json(app(ShopController::class)->payload($purchaseRequest->load(['items', 'user'])));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        unset($data['image']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
