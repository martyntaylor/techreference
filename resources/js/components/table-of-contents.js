export default () => ({
    headings: [],

    init() {
        const content = document.getElementById('content');
        if (!content) return;

        // Extract headings
        const h2s = content.querySelectorAll('h2');
        const h3s = content.querySelectorAll('h3');

        const allHeadings = [...h2s, ...h3s]
            .filter(heading => heading.id) // Only include headings with IDs
            .sort((a, b) => {
                return a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
            });

        this.headings = allHeadings.map(heading => ({
            id: heading.id,
            text: heading.textContent,
            level: parseInt(heading.tagName[1]),
            active: false,
            element: heading
        }));

        // Set up IntersectionObserver to watch headings
        const observer = new IntersectionObserver((entries) => {
            // Find the first visible heading from the top
            const visibleHeadings = entries
                .filter(entry => entry.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            if (visibleHeadings.length > 0) {
                const activeId = visibleHeadings[0].target.id;
                this.headings = this.headings.map(heading => ({
                    ...heading,
                    active: heading.id === activeId
                }));
            }
        }, {
            rootMargin: '-80px 0px -80% 0px',
            threshold: [0, 1]
        });

        // Observe all heading elements
        allHeadings.forEach(heading => {
            observer.observe(heading);
        });
    }
});
