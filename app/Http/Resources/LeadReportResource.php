<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Attribute\Repositories\AttributeRepository;

/**
 * A Lead as the sales reports need it.
 *
 * Krayin's own `LeadResource` whitelists columns and therefore drops every
 * custom field, which is precisely what these reports are built on. Here the
 * user-defined attributes are emitted under `custom_fields`, keyed by the
 * attribute `code` — the same code shown in Settings > Attributes, and the
 * stable identifier to write report queries against. Native fields stay at
 * the top level so a report never has to guess where a value lives.
 */
class LeadReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'value' => $this->lead_value,
            'status' => $this->status,
            'expected_close_date' => $this->expected_close_date,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'source' => $this->source?->name,
            'type' => $this->type?->name,
            'pipeline' => $this->pipeline?->name,
            'stage' => $this->stage?->name,

            'owner' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,

            'contact' => $this->person ? [
                'id' => $this->person->id,
                'name' => $this->person->name,
                'emails' => $this->person->emails,
                'phones' => $this->person->contact_numbers,
            ] : null,

            'products' => $this->products->map(fn ($product) => [
                'name' => $product->name,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'total' => $product->total,
            ])->values(),

            'custom_fields' => $this->customFields(),
        ];
    }

    /**
     * Only user-defined attributes: the system ones (title, lead_value,
     * user_id…) are already represented above, and repeating them under a
     * second key invites reports to drift apart depending on which copy the
     * author happened to use.
     */
    protected function customFields(): array
    {
        $attributes = app(AttributeRepository::class)
            ->findWhere(['entity_type' => 'leads', 'is_user_defined' => 1]);

        $values = [];

        foreach ($attributes as $attribute) {
            $values[$attribute->code] = $this->readable($attribute, $this->resource->{$attribute->code});
        }

        return $values;
    }

    /**
     * Dropdowns store the option *id*, so a raw read gives a report
     * `nivel_intencion: 3`, which means nothing outside this database and
     * breaks the moment options are re-created. The label is what a report
     * groups and filters on, so that is what goes out.
     */
    protected function readable($attribute, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($attribute->type, ['select', 'multiselect', 'checkbox'], true)) {
            $ids = array_filter(explode(',', (string) $value), fn ($id) => $id !== '');

            $labels = $attribute->options
                ->whereIn('id', $ids)
                ->pluck('name')
                ->values()
                ->all();

            return $attribute->type === 'select' ? ($labels[0] ?? null) : $labels;
        }

        return $value;
    }
}
