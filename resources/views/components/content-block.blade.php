@props(['title', 'content', 'escape' => true])

<section class="grid grid-cols-4 border-t border-gray-950/10 dark:border-white/10 mb-12">
    <div class="col-span-full sm:col-span-1">
        <div class="-mt-px inline-flex border-t border-gray-950 pt-px dark:border-white">
            <div class="pt-4 text-sm/7 font-semibold text-gray-950 sm:pt-10 dark:text-white">
                <h2 id="{{ Str::slug($title) }}">{{ $title }}</h2>
            </div>
        </div>
    </div>
    <div class="col-span-full pt-6 sm:col-span-3 sm:pt-10">
        @if($escape)
            {!! nl2br(e($content)) !!}
        @else
            {!! $content !!}
        @endif
    </div>
</section>
