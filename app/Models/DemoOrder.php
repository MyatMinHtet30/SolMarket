<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DemoOrder extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $attributes = ['status' => 'pending'];
    protected $hidden = ['session_hash', 'signed_transaction'];

    protected function casts(): array
    {
        return ['lamports' => 'integer', 'last_valid_block_height' => 'integer',
            'expires_at' => 'datetime', 'verified_at' => 'datetime', 'signed_transaction' => 'encrypted'];
    }

    public function memo(): string { return 'SolMarket demo '.$this->id; }
}
