<?php

namespace App\Observers;

use App\Models\Software;
use Illuminate\Support\Str;

class SoftwareObserver
{
    /**
     * Handle the Software "creating" event.
     * Note: Slug generation is also handled in the model boot method.
     */
    public function creating(Software $software): void
    {
        // Ensure slug is generated if not set
        if (empty($software->slug) && ! empty($software->name)) {
            $software->slug = Str::slug($software->name);
        }
    }

    /**
     * Handle the Software "updating" event.
     */
    public function updating(Software $software): void
    {
        // Regenerate slug if name changed
        if ($software->isDirty('name') && ! $software->isDirty('slug')) {
            $software->slug = Str::slug($software->name);
        }
    }
}
