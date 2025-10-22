<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryObserver
{
    /**
     * Handle the Category "creating" event.
     * Note: Slug generation is also handled in the model boot method.
     */
    public function creating(Category $category): void
    {
        // Ensure slug is generated if not set
        if (empty($category->slug) && ! empty($category->name)) {
            $category->slug = Str::slug($category->name);
        }
    }

    /**
     * Handle the Category "updating" event.
     */
    public function updating(Category $category): void
    {
        // Regenerate slug if name changed
        if ($category->isDirty('name') && ! $category->isDirty('slug')) {
            $category->slug = Str::slug($category->name);
        }
    }
}
