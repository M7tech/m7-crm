<x-layouts::auth :title="__('Accept invitation')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Join :company', ['company' => $invitation->tenant->name])"
            :description="__('Create your account to accept the :role invitation.', ['role' => $invitation->role->label()])"
        />

        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
            Invited email: <span class="font-medium">{{ $invitation->email }}</span>
        </div>

        <form method="POST" action="{{ route('invitations.accept.store', ['token' => $token]) }}" class="flex flex-col gap-5">
            @csrf
            <flux:input name="name" :label="__('Full name')" :value="old('name')" required autofocus autocomplete="name" />
            <flux:input name="password" type="password" :label="__('Password')" required autocomplete="new-password" viewable />
            <flux:input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" viewable />
            <flux:button type="submit" variant="primary" class="w-full">Accept invitation</flux:button>
        </form>
    </div>
</x-layouts::auth>
