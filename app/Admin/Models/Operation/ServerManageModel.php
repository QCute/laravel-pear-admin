<?php

namespace App\Admin\Models\Operation;

use App\Admin\Models\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServerManageModel extends Model 
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'admin';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'server_manage';

    public static function getPage(int $page, int $perPage, array $input = []): LengthAwarePaginator
    {
        return (new static())
            ->withInput($input)
            ->paginate($perPage, ['*'], 'page', $page);
    }
}