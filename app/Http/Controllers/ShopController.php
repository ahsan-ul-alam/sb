<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Selection;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Endpoints used by the simple "user" role. */
class ShopController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->when($request->query('q'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()->get();

        $selected = Selection::where('user_id', $request->user()->id)->pluck('quantity', 'product_id');

        return response()->json($products->map(fn ($p) => $p->toArray() + ['selected_qty' => $selected[$p->id] ?? 0]));
    }

    public function product(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json($product);
    }

    public function selections(Request $request): JsonResponse
    {
        return response()->json($this->selectionList($request));
    }

    public function select(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);

        if ($data['quantity'] === 0) {
            Selection::where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();
        } else {
            Selection::updateOrCreate(
                ['user_id' => $request->user()->id, 'product_id' => $product->id],
                ['quantity' => $data['quantity']],
            );
        }

        return response()->json($this->selectionList($request));
    }

    public function requests(Request $request): JsonResponse
    {
        return response()->json(
            PurchaseRequest::with('items')->where('user_id', $request->user()->id)->latest()->get()
                ->map(fn ($r) => $this->payload($r))
        );
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $user = $request->user();

        $selections = Selection::with('product')->where('user_id', $user->id)->get()
            ->filter(fn ($s) => $s->product?->is_active);

        if ($selections->isEmpty()) {
            return response()->json(['message' => 'Select at least one product first.'], 422);
        }

        $purchase = DB::transaction(function () use ($user, $data, $selections) {
            $purchase = PurchaseRequest::create(['user_id' => $user->id, 'note' => $data['note'] ?? null]);
            foreach ($selections as $s) {
                $purchase->items()->create([
                    'product_id' => $s->product_id,
                    'product_name' => $s->product->name,
                    'price' => $s->product->price,
                    'quantity' => $s->quantity,
                ]);
            }
            Selection::where('user_id', $user->id)->delete();

            return $purchase->load('items');
        });

        Notification::send(
            User::where('role', 'admin')->get(),
            new ActivityNotification('New purchase request #'.$purchase->id, $user->name.' requested '.$purchase->items->count().' product(s).', $purchase->id),
        );

        return response()->json($this->payload($purchase), 201);
    }

    public function cancelRequest(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        abort_unless($purchaseRequest->user_id === $request->user()->id, 403);

        if ($purchaseRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $purchaseRequest->update(['status' => 'cancelled']);
        Notification::send(
            User::where('role', 'admin')->get(),
            new ActivityNotification('Request #'.$purchaseRequest->id.' cancelled', $request->user()->name.' cancelled their request.', $purchaseRequest->id),
        );

        return response()->json($this->payload($purchaseRequest->load('items')));
    }

    private function selectionList(Request $request): array
    {
        $rows = Selection::with('product')->where('user_id', $request->user()->id)->get()
            ->filter(fn ($s) => $s->product)
            ->map(fn ($s) => ['product' => $s->product, 'quantity' => $s->quantity])->values();

        return [
            'items' => $rows,
            'total' => $rows->sum(fn ($r) => $r['product']->price * $r['quantity']),
        ];
    }

    public function payload(PurchaseRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'note' => $r->note,
            'admin_note' => $r->admin_note,
            'created_at' => $r->created_at->toIso8601String(),
            'items' => $r->items,
            'total' => $r->items->sum(fn ($i) => $i->price * $i->quantity),
            'user' => $r->relationLoaded('user') ? $r->user->only('id', 'name', 'email') : null,
        ];
    }
}
