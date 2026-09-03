<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                <flux:navbar.item icon="building-office-2" :href="route('companies.index')" :current="request()->routeIs('companies.*')" wire:navigate>
                    {{ __('Companies') }}
                </flux:navbar.item>
                <flux:navbar.item icon="users" :href="route('contacts.index')" :current="request()->routeIs('contacts.*')" wire:navigate>
                    {{ __('Contacts') }}
                </flux:navbar.item>
                <flux:navbar.item icon="rectangle-stack" :href="route('leads.index')" :current="request()->routeIs('leads.*') || request()->routeIs('pipelines.*')" wire:navigate>
                    {{ __('Pipeline') }}
                </flux:navbar.item>
                <flux:navbar.item icon="check-circle" :href="route('tasks.index')" :current="request()->routeIs('tasks.*')" wire:navigate>
                    {{ __('Tasks') }}
                </flux:navbar.item>
                @can('viewReports')
                    <flux:navbar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:navbar.item>
                @endcan
                @can('create', \App\Models\Integration::class)
                    <flux:navbar.item icon="link" :href="route('integrations.meta.index')" :current="request()->routeIs('integrations.*')" wire:navigate>
                        {{ __('Integrations') }}
                    </flux:navbar.item>
                @endcan
                @can('viewAny', \App\Models\Invitation::class)
                    <flux:navbar.item icon="user-group" :href="route('team.index')" :current="request()->routeIs('team.*')" wire:navigate>
                        {{ __('Team') }}
                    </flux:navbar.item>
                @endcan
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
                <flux:tooltip :content="__('Search')" position="bottom">
                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />
                </flux:tooltip>
            </flux:navbar>

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-office-2" :href="route('companies.index')" :current="request()->routeIs('companies.*')" wire:navigate>
                        {{ __('Companies') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('contacts.index')" :current="request()->routeIs('contacts.*')" wire:navigate>
                        {{ __('Contacts') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="rectangle-stack" :href="route('leads.index')" :current="request()->routeIs('leads.*') || request()->routeIs('pipelines.*')" wire:navigate>
                        {{ __('Pipeline') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="check-circle" :href="route('tasks.index')" :current="request()->routeIs('tasks.*')" wire:navigate>
                        {{ __('Tasks') }}
                    </flux:sidebar.item>
                    @can('viewReports')
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('create', \App\Models\Integration::class)
                        <flux:sidebar.item icon="link" :href="route('integrations.meta.index')" :current="request()->routeIs('integrations.*')" wire:navigate>
                            {{ __('Integrations') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Invitation::class)
                        <flux:sidebar.item icon="user-group" :href="route('team.index')" :current="request()->routeIs('team.*')" wire:navigate>
                            {{ __('Team') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
