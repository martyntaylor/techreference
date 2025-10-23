<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortRelation extends Model
{
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key for the model.
     *
     * @var string|array
     */
    protected $primaryKey = ['port_id', 'related_port_id', 'relation_type'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'port_id',
        'related_port_id',
        'relation_type',
        'description',
    ];

    /**
     * Relation types available.
     */
    const TYPE_ALTERNATIVE = 'alternative';
    const TYPE_SECURE_VERSION = 'secure_version';
    const TYPE_DEPRECATED_BY = 'deprecated_by';
    const TYPE_PART_OF_SUITE = 'part_of_suite';
    const TYPE_CONFLICTS_WITH = 'conflicts_with';
    const TYPE_COMPLEMENTARY = 'complementary';
    const TYPE_ASSOCIATED_WITH = 'associated_with';

    /**
     * Get the port that owns this relation.
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'port_id');
    }

    /**
     * Get the related port.
     */
    public function relatedPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'related_port_id');
    }
}
