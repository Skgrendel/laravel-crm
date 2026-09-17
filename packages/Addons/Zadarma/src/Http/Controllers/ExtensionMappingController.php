<?php

namespace Addons\Zadarma\Http\Controllers;

use Addons\Zadarma\Repositories\ZadarmaExtensionMappingRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

class ExtensionMappingController extends Controller
{
    public function __construct(
        protected ZadarmaExtensionMappingRepository $zadarmaExtensionMappingRepository,
    ) {}

    /**
     * Save the extension → user mapping shown on the settings screen.
     *
     * @param  array<int, array{user_id: int, extension: ?string}>  $mappings
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mappings'              => 'required|array',
            'mappings.*.user_id'    => 'required|integer',
            'mappings.*.extension'  => 'nullable|string|max:20',
        ]);

        $extensionsByUserId = collect($data['mappings'])
            ->pluck('extension', 'user_id')
            ->map(fn ($extension) => trim((string) $extension))
            ->all();

        $duplicates = collect($extensionsByUserId)
            ->filter(fn ($extension) => $extension !== '')
            ->duplicates();

        if ($duplicates->isNotEmpty()) {
            return response()->json([
                'message' => trans('zadarma::app.settings.index.duplicate-extension', ['extension' => $duplicates->first()]),
            ], 422);
        }

        $this->zadarmaExtensionMappingRepository->saveMappings($extensionsByUserId);

        return response()->json([
            'message' => trans('zadarma::app.settings.index.extensions-update-success'),
        ]);
    }
}
