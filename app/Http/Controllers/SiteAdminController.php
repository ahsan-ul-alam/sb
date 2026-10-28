<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Support\SiteSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Admin CRUD for landing-page settings and repeatable content, driven by SiteSchema. */
class SiteAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $stored = Setting::all()->keyBy('key');
        $settings = [];
        foreach (SiteSchema::groups() as $key => $group) {
            $values = [];
            foreach ($group['fields'] as $field) {
                $values[$field['key']] = ($stored[$key]->value ?? [])[$field['key']] ?? $field['default'];
            }
            $settings[$key] = $values;
        }

        $items = ContentItem::orderBy('sort')->orderBy('id')->get()->groupBy('type')
            ->map(fn ($g) => $g->map(fn ($i) => ['id' => $i->id, 'sort' => $i->sort, 'is_active' => $i->is_active] + $i->data)->values());

        return response()->json([
            'groups' => SiteSchema::groups(),
            'types' => SiteSchema::items(),
            'settings' => $settings,
            'items' => $items,
            'image_base' => '/storage/',
        ]);
    }

    public function saveSettings(Request $request, string $group): JsonResponse
    {
        $fields = SiteSchema::groups()[$group]['fields'] ?? abort(404);
        $row = Setting::firstOrNew(['key' => $group]);
        $current = $row->value ?? [];
        $new = $current;

        $request->validate($this->rules($fields));

        foreach ($fields as $field) {
            $k = $field['key'];
            if ($field['type'] === 'image') {
                if ($request->hasFile($k)) {
                    $this->deleteImage($current[$k] ?? null);
                    $new[$k] = $request->file($k)->store('site', 'public');
                } elseif ($request->boolean($k.'_remove')) {
                    $this->deleteImage($current[$k] ?? null);
                    $new[$k] = null;
                }
            } elseif ($field['type'] === 'bool') {
                $new[$k] = $request->boolean($k);
            } else {
                $new[$k] = $request->input($k);
            }
        }

        $row->value = $new;
        $row->save();

        return $this->index();
    }

    public function saveItem(Request $request, string $type, ?ContentItem $item = null): JsonResponse
    {
        $schema = SiteSchema::items()[$type] ?? abort(404);
        abort_if($item && $item->type !== $type, 404);

        $request->validate($this->rules($schema['fields']) + ['sort' => ['nullable', 'integer', 'min:0', 'max:9999']]);

        $data = [];
        foreach ($schema['fields'] as $field) {
            $data[$field['key']] = $field['type'] === 'bool' ? $request->boolean($field['key']) : $request->input($field['key']);
        }

        $attrs = [
            'type' => $type,
            'data' => $data,
            'sort' => (int) $request->input('sort', $item?->sort ?? (ContentItem::where('type', $type)->max('sort') + 1)),
            'is_active' => $request->boolean('is_active', true),
        ];
        $item ? $item->update($attrs) : ContentItem::create($attrs);

        return $this->index();
    }

    public function deleteItem(string $type, ContentItem $item): JsonResponse
    {
        abort_if($item->type !== $type, 404);
        $item->delete();

        return $this->index();
    }

    public function subscribers(): JsonResponse
    {
        return response()->json(Subscriber::latest()->get());
    }

    public function deleteSubscriber(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();

        return response()->json(['ok' => true]);
    }

    private function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $f) {
            $rules[$f['key']] = match ($f['type']) {
                'image' => ['nullable', 'image', 'max:4096'],
                'bool' => ['nullable', 'boolean'],
                'number' => [empty($f['required']) ? 'nullable' : 'required', 'numeric', 'min:0', 'max:100000000'],
                'select' => ['nullable', Rule::in(array_map('strval', array_keys($f['options'])))],
                'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'textarea' => [empty($f['required']) ? 'nullable' : 'required', 'string', 'max:'.($f['max'] ?? 3000)],
                default => [empty($f['required']) ? 'nullable' : 'required', 'string', 'max:'.($f['max'] ?? 500)],
            };
        }

        return $rules;
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
