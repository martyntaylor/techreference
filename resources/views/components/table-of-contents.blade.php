<!-- Table of Contents Component -->
<nav x-data="tableOfContents">
    <h2 class="text-sm/6 font-semibold text-gray-950 dark:text-white">
        On this page
    </h2>
    <ul class="mt-3 flex flex-col gap-3 border-l border-gray-950/10 text-sm/6 text-gray-700 dark:border-white/10 dark:text-gray-400">
        <template x-for="heading in headings" :key="heading.id">
            <li class="-ml-px border-l border-transparent pl-4 hover:text-gray-950 hover:border-gray-400 dark:hover:text-white"
                :class="{ 'border-gray-950 dark:border-white': heading.active }">
                <a :href="'#' + heading.id"
                   :aria-current="heading.active ? 'location' : undefined"
                   :class="{ 'pl-4': heading.level === 3, 'font-medium text-gray-950 dark:text-white': heading.active }"
                   class="block"
                   x-text="heading.text"></a>
            </li>
        </template>
    </ul>
</nav>
