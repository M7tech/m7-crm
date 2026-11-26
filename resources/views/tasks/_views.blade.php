{{-- One task list, three views. Every view reads the same task records. --}}
<nav class="flex flex-wrap gap-2" aria-label="{{ __('Task views') }}">
    @foreach ([
        ['label' => __('My tasks'), 'href' => route('tasks.index', ['scope' => 'mine']), 'active' => request()->routeIs('tasks.index') && $scope === 'mine'],
        ['label' => __('Team tasks'), 'href' => route('tasks.index', ['scope' => 'team']), 'active' => request()->routeIs('tasks.index') && $scope === 'team'],
        ['label' => __('Board'), 'href' => route('tasks.board', ['scope' => $scope]), 'active' => request()->routeIs('tasks.board')],
    ] as $view)
        <a href="{{ $view['href'] }}" @if ($view['active']) aria-current="page" @endif @class([
            'rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $view['active'],
            'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => ! $view['active'],
        ]) wire:navigate>{{ $view['label'] }}</a>
    @endforeach
</nav>
