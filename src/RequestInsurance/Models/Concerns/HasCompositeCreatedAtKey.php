<?php

namespace Cego\RequestInsurance\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasCompositeCreatedAtKey
{
    /**
     * Add created_at to the save/update/delete predicate so writes prune to a
     * single partition. On unpartitioned tables this is a harmless extra filter.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        parent::setKeysForSaveQuery($query);

        $createdAtColumn = $this->getCreatedAtColumn();
        $createdAt = $this->getRawOriginal($createdAtColumn);

        if ($createdAt !== null) {
            $query->where($createdAtColumn, '=', $createdAt);
        }

        return $query;
    }
}
