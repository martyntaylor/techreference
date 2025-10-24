<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-16 font-sans antialiased dark:bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle ?? 'TechReference' }} - Technical Reference for Developers</title>

    <!-- CSS -->
    @vite(['resources/css/app.css'])

    <!-- Additional Head Content -->
    {{ $head ?? '' }}
</head>
<body class="dark:text-white" x-data="{
  sidebarOpen: window.innerWidth >= 1280,
  mobileDialogOpen: false,
  mobileNavOpen: false,
  accountDropdownOpen: false,
  darkMode: localStorage.getItem('darkMode') === 'dark' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches),
  toggleDarkMode() {
    this.darkMode = !this.darkMode;
    if (this.darkMode) {
      document.documentElement.classList.add('dark');
      document.documentElement.classList.remove('light');
      localStorage.setItem('darkMode', 'dark');
    } else {
      document.documentElement.classList.add('light');
      document.documentElement.classList.remove('dark');
      localStorage.setItem('darkMode', 'light');
    }
  }
}"
x-init="if (darkMode) { document.documentElement.classList.add('dark'); } else if (localStorage.getItem('darkMode') === 'light') { document.documentElement.classList.add('light'); }"
@resize.window="sidebarOpen = window.innerWidth >= 1280">
    <div class="isolate">
        
        <!-- Navigation -->
        <div class="sticky top-0 z-10 bg-white/90 backdrop-blur-sm dark:bg-gray-950/90 flex items-center justify-between gap-x-8 px-4 py-4 sm:px-6 border-b border-gray-950/10 dark:border-white/10">
            <div class="flex min-w-0 shrink items-center gap-x-4">
            <div class="min-w-0">
                <a href="{{ route('home') }}" class="text-xl font-bold text-gray-900 dark:text-white">
                    TechReference
                </a>
                {{--<nav aria-label="Breadcrumb" class="flex items-center gap-x-2 text-sm/6">
                <a href="index.html" class="min-w-0 shrink-0 text-gray-950 dark:text-white">Compass</a>
                <span class="text-gray-950/25 dark:text-white/25">/</span>
                <span class="min-w-0 truncate text-gray-950 last:text-gray-600 dark:last:text-gray-400">Resources</span>
                </nav> --}}
            </div>
            </div>
    
            <!-- Site Navigation -->
            <nav class="flex items-center">
            <!-- Mobile hamburger -->
            <button @click="mobileNavOpen = true" type="button" class="lg:hidden relative *:relative before:absolute before:top-1/2 before:left-1/2 before:size-8 before:-translate-1/2 before:rounded-md before:bg-white/75 before:backdrop-blur-sm dark:before:bg-gray-950/75 hover:before:bg-gray-950/5 dark:hover:before:bg-white/5">
                <svg viewBox="0 0 16 16" fill="none" class="h-4 shrink-0 fill-gray-950 dark:fill-white">
                <circle cx="8" cy="3" r="1" />
                <circle cx="8" cy="8" r="1" />
                <circle cx="8" cy="13" r="1" />
                </svg>
            </button>
    
            <!-- Mobile Navigation Dialog -->
            <div x-show="mobileNavOpen" x-cloak class="lg:hidden">
                <div @click="mobileNavOpen = false" class="fixed inset-0 bg-gray-950/25"></div>
                <div class="fixed inset-0 flex justify-end pl-11">
                <div class="w-full max-w-2xs bg-white px-4 py-5 ring ring-gray-950/10 sm:px-6 dark:bg-gray-950 dark:ring-white/10">
                    <div class="flex justify-end">
                    <button @click="mobileNavOpen = false" type="button" class="relative *:relative before:absolute before:top-1/2 before:left-1/2 before:size-8 before:-translate-1/2 before:rounded-md before:bg-white/75 before:backdrop-blur-sm dark:before:bg-gray-950/75">
                        <svg viewBox="0 0 16 16" fill="none" class="h-4 shrink-0 stroke-gray-950 dark:stroke-white">
                        <path d="M5 5L11 11M11 5L5 11" stroke-linecap="square" />
                        </svg>
                    </button>
                    </div>
                    <div class="mt-4">
                    <div class="flex flex-col gap-y-2">
                        <a href="index.html" @click="mobileNavOpen = false" class="block rounded-md px-4 py-1.5 text-lg/7 font-medium tracking-tight text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Course</a>
                        <a href="interviews.html" @click="mobileNavOpen = false" class="block rounded-md px-4 py-1.5 text-lg/7 font-medium tracking-tight text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Interviews</a>
                        <a href="resources.html" @click="mobileNavOpen = false" class="block rounded-md px-4 py-1.5 text-lg/7 font-medium tracking-tight text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Resources</a>
                    </div>
                    <div class="mt-6 flex flex-col gap-y-2">
                        <h3 class="px-4 py-1 text-sm/7 text-gray-500">Appearance</h3>
                        <button @click="toggleDarkMode()" class="rounded-md px-4 py-1 text-sm/7 font-semibold text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5 flex items-center gap-x-2">
                        <svg x-show="!darkMode" viewBox="0 0 16 16" fill="none" class="h-4 w-4 stroke-gray-950 dark:stroke-white" >
                            <path d="M8 14a6 6 0 0 1-5.5-8.5 6 6 0 0 0 11-3 6 6 0 0 1-5.5 11.5z" />
                        </svg>
                        <svg x-show="darkMode" viewBox="0 0 16 16" fill="none" class="h-4 w-4 stroke-gray-950 dark:stroke-white">
                            <circle cx="8" cy="8" r="3.5" />
                            <path d="M8 1v2M8 13v2M15 8h-2M3 8H1M13.071 2.929l-1.414 1.414M4.343 11.657l-1.414 1.414M13.071 13.071l-1.414-1.414M4.343 4.343L2.929 2.929" stroke-linecap="round" />
                        </svg>
                        <span x-text="darkMode ? 'Light mode' : 'Dark mode'"></span>
                        </button>
                    </div>
                    <div class="mt-6 flex flex-col gap-y-2">
                        <h3 class="px-4 py-1 text-sm/7 text-gray-500">Account</h3>
                        <a href="#" @click="mobileNavOpen = false" class="rounded-md px-4 py-1 text-sm/7 font-semibold text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Settings</a>
                        <a href="#" @click="mobileNavOpen = false" class="rounded-md px-4 py-1 text-sm/7 font-semibold text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Support</a>
                        <a href="login.html" @click="mobileNavOpen = false" class="rounded-md px-4 py-1 text-sm/7 font-semibold text-gray-950 hover:bg-gray-950/5 dark:text-white dark:hover:bg-white/5">Sign out</a>
                    </div>
                    </div>
                </div>
                </div>
            </div>
    
            <!-- Desktop Navigation -->
            <div class="flex gap-x-6 items-center text-sm/6 text-gray-950 max-lg:hidden dark:text-white">
                <a href="{{ route('ports.index') }}">Ports</a>
                <a href="interviews.html">Interviews</a>
                <a href="resources.html">Resources</a>
    
                <!-- Dark Mode Toggle -->
                <button @click="toggleDarkMode()" type="button" class="relative p-1 rounded-md bg-white/75 backdrop-blur-sm dark:bg-gray-950/75 hover:bg-gray-950/5 dark:hover:bg-white/5" aria-label="Toggle dark mode">
                <svg x-show="darkMode" viewBox="0 0 16 16" fill="none" class="h-4 w-4 stroke-gray-950 dark:stroke-white">
                    <circle cx="8" cy="8" r="3.5" />
                    <path d="M8 1v2M8 13v2M15 8h-2M3 8H1M13.071 2.929l-1.414 1.414M4.343 11.657l-1.414 1.414M13.071 13.071l-1.414-1.414M4.343 4.343L2.929 2.929" stroke-linecap="round" />
                </svg>
                <svg x-show="!darkMode" viewBox="0 0 16 16" fill="none" class="h-4 w-4 stroke-gray-950 dark:stroke-white">
                    <path d="M8 14a6 6 0 0 1-5.5-8.5 6 6 0 0 0 11-3 6 6 0 0 1-5.5 11.5z" />
                </svg>
                </button>
    
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                <button @click="open = !open" class="inline-flex items-center gap-x-2">
                    Account
                    <svg viewBox="0 0 8 4" fill="none" class="h-1 shrink-0 stroke-gray-950 dark:stroke-white">
                    <path d="M1 0.5L4 3.5L7 0.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 min-w-38 rounded-lg bg-white/75 p-0.5 shadow-sm outline outline-gray-950/5 backdrop-blur-sm dark:bg-gray-950/75 dark:outline-white/10">
                    <a href="#" @click="open = false" class="block rounded-md px-3 py-0.5 text-sm/7 text-gray-950 hover:bg-blue-500 hover:text-white dark:text-white">Settings</a>
                    <a href="#" @click="open = false" class="block rounded-md px-3 py-0.5 text-sm/7 text-gray-950 hover:bg-blue-500 hover:text-white dark:text-white">Support</a>
                    <a href="login.html" @click="open = false" class="block rounded-md px-3 py-0.5 text-sm/7 text-gray-950 hover:bg-blue-500 hover:text-white dark:text-white">Sign out</a>
                </div>
                </div>
            </div>
            </nav>
        </div>



            <!-- Page Content -->
            <div class="mx-auto max-w-2xl py-8 lg:max-w-7xl">
                <!-- Breadcrumbs -->
                @if(isset($breadcrumbs))
                    {{ $breadcrumbs }}
                @endif

                <div class="flex gap-x-10">
                    <!-- Main Content -->
                    <main id="content" class="w-full flex-1 prose">
                        {{ $slot }}
                    </main>

                <!-- Right Sidebar -->
                @if(isset($sidebar))
                <aside class="hidden w-66 lg:block">
                    <div class="sticky top-16">
                        <!-- Ad Block Placeholder -->
                        <div class="mb-8 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm text-gray-500">
                            Ad space
                        </div>
                        
                        {{ $sidebar }}

                        <!-- Ad Block Placeholder -->
                        <div class="mt-8 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-center text-sm text-gray-500">
                            Ad space
                        </div>
                    </div>
                </aside>
                @endif
                </div>
            </div>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
                <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                        &copy; {{ date('Y') }} TechReference. Technical reference for developers.
                    </div>
                </div>
            </footer>
        </div>

    <!-- Additional Scripts -->
    @stack('scripts')
    {{ $scripts ?? '' }}
    @vite(['resources/js/app.js'])
</body>
</html>
