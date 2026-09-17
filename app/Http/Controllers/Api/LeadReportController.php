<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\LeadReportResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Webkul\Lead\Models\Lead;

/**
 * Read-only feed of Leads for building sales reports outside the CRM.
 *
 * Krayin ships no Lead API in this installation (only `api/user`), and its
 * internal `LeadResource` drops custom fields, so reports had no way to read
 * the Bitrix24 fields that were migrated across. This endpoint exists for
 * that: every Lead with its custom fields keyed by attribute code.
 */
class LeadReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'date_field' => 'nullable|in:created_at,updated_at,closed_at,expected_close_date',
            'pipeline_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'status' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        /**
         * Defaults to `created_at` but any date column can drive the window:
         * a revenue report keys off the close date, an activity report off
         * creation, and guessing wrong silently reports the wrong period.
         */
        $dateField = $filters['date_field'] ?? 'created_at';

        $query = Lead::query()
            ->with(['user', 'person', 'source', 'type', 'pipeline', 'stage', 'products', 'attribute_values'])
            ->when(isset($filters['from']), fn ($q) => $q->whereDate($dateField, '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate($dateField, '<=', $filters['to']))
            ->when(isset($filters['pipeline_id']), fn ($q) => $q->where('lead_pipeline_id', $filters['pipeline_id']))
            ->when(isset($filters['stage_id']), fn ($q) => $q->where('lead_pipeline_stage_id', $filters['stage_id']))
            ->when(isset($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id');

        return LeadReportResource::collection(
            $query->paginate($filters['per_page'] ?? 50)
        );
    }

    /**
     * The field catalogue: what each custom field is called, its code and
     * its type, plus the options of every dropdown. A report generator can
     * read this instead of hardcoding a list that goes stale the moment
     * somebody adds a field in Settings.
     */
    public function fields(): array
    {
        $attributes = app(\Webkul\Attribute\Repositories\AttributeRepository::class)
            ->findWhere(['entity_type' => 'leads', 'is_user_defined' => 1]);

        return [
            'data' => $attributes->map(fn ($attribute) => [
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'options' => $attribute->options->pluck('name')->values(),
            ])->values()->toArray(),
        ];
    }
}
