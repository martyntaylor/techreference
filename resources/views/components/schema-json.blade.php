@props(['type' => 'TechArticle', 'data' => []])

@php
$baseSchema = [
    '@context' => 'https://schema.org',
    '@type' => $type,
];

$schema = match($type) {
    'TechArticle' => array_merge($baseSchema, [
        'headline' => $data['headline'] ?? '',
        'description' => $data['description'] ?? '',
        'author' => [
            '@type' => 'Organization',
            'name' => 'TechReference',
            'url' => url('/'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'TechReference',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('images/logo.png'),
            ],
        ],
        'datePublished' => $data['datePublished'] ?? now()->toIso8601String(),
        'dateModified' => $data['dateModified'] ?? now()->toIso8601String(),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => url()->current(),
        ],
    ]),

    'FAQPage' => array_merge($baseSchema, [
        'mainEntity' => collect($data['questions'] ?? [])->map(function($faq) {
            return [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        })->toArray(),
    ]),

    'BreadcrumbList' => array_merge($baseSchema, [
        'itemListElement' => collect($data['items'] ?? [])->map(function($item, $index) {
            return [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'] ?? null,
            ];
        })->toArray(),
    ]),

    default => $baseSchema,
};
@endphp

<script type="application/ld+json">
{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
