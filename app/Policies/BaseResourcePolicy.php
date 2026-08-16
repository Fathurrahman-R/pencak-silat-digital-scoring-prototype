<?php

namespace App\Policies;

use App\Enums\ResourceAction;
use App\Support\Resources\ResourceGate;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Jembatan antara policy Laravel dan resource key.
 *
 * Turunkan kelas ini lalu sebutkan nama resource-nya sekali:
 *
 *   class TournamentPolicy extends BaseResourcePolicy
 *   {
 *       protected function resource(): string
 *       {
 *           return 'turnamen';
 *       }
 *   }
 *
 * Setelah itu $this->authorize('update', $tournament) dan @can('update', $tournament)
 * berjalan seperti biasa, tapi keputusannya tetap datang dari pemetaan
 * resource key di database.
 */
abstract class BaseResourcePolicy
{
    abstract protected function resource(): string;

    public function viewAny(Authorizable $user): bool
    {
        return $this->check($user, ResourceAction::View);
    }

    public function view(Authorizable $user, mixed $model = null): bool
    {
        return $this->check($user, ResourceAction::View);
    }

    public function create(Authorizable $user): bool
    {
        return $this->check($user, ResourceAction::Create);
    }

    public function update(Authorizable $user, mixed $model = null): bool
    {
        return $this->check($user, ResourceAction::Update);
    }

    public function delete(Authorizable $user, mixed $model = null): bool
    {
        return $this->check($user, ResourceAction::Delete);
    }

    public function restore(Authorizable $user, mixed $model = null): bool
    {
        return $this->check($user, ResourceAction::Restore);
    }

    public function forceDelete(Authorizable $user, mixed $model = null): bool
    {
        return $this->check($user, ResourceAction::ForceDelete);
    }

    public function export(Authorizable $user): bool
    {
        return $this->check($user, ResourceAction::Export);
    }

    protected function check(Authorizable $user, ResourceAction $action): bool
    {
        return app(ResourceGate::class)->allows(
            rk($this->resource(), $action),
            $user,
        );
    }
}
