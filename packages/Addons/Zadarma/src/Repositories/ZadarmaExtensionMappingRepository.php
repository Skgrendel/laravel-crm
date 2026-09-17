<?php

namespace Addons\Zadarma\Repositories;

use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;

class ZadarmaExtensionMappingRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Addons\Zadarma\Contracts\ZadarmaExtensionMapping';
    }

    /**
     * All mappings keyed by user id, for prefilling the settings form.
     */
    public function getAllKeyedByUserId()
    {
        return $this->model->newQuery()->get()->keyBy('user_id');
    }

    /**
     * Resolve the Krayin user id mapped to a SIP extension, if any.
     */
    public function findUserIdByExtension(?string $extension): ?int
    {
        if (empty($extension)) {
            return null;
        }

        return $this->model->newQuery()
            ->where('extension', $extension)
            ->value('user_id');
    }

    /**
     * Resolve the SIP extension mapped to a Krayin user id, if any.
     */
    public function findExtensionByUserId(int $userId): ?string
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->value('extension');
    }

    /**
     * Replace the extension mappings with the given `user_id => extension`
     * pairs. Users left out, or given a blank extension, have their mapping
     * removed.
     */
    public function saveMappings(array $extensionsByUserId): void
    {
        DB::transaction(function () use ($extensionsByUserId) {
            foreach ($extensionsByUserId as $userId => $extension) {
                $extension = trim((string) $extension);

                if ($extension === '') {
                    $this->model->newQuery()->where('user_id', $userId)->delete();

                    continue;
                }

                $this->model->newQuery()->updateOrCreate(
                    ['user_id' => $userId],
                    ['extension' => $extension]
                );
            }
        });
    }
}
