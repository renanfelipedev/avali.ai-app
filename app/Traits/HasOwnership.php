<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasOwnership
{
    /**
     * Ensure the current user owns the given model.
     */
    protected function authorizeOwnership(Model $model): void
    {
        if ($model->user_id !== auth()->id()) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }
    }
}
