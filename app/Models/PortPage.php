<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PortPage extends Model
{
    protected $fillable = [
        'port_number',
        'page_title',
        'meta_description',
        'heading',
        'content_blocks',
        'faqs',
        'video_urls',
    ];

    protected $casts = [
        'port_number' => 'integer',
        'content_blocks' => 'array',
        'faqs' => 'array',
        'video_urls' => 'array',
    ];

    /**
     * Get all port protocol entries for this port number.
     */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class, 'port_number', 'port_number');
    }
}
