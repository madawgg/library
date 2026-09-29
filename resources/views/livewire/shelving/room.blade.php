<?php

use App\Models\Room;
use App\Services\RoomService;
use App\Services\ShelfViewService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * A room with a card for each of its bookcases (spec 005, M-06).
 */
new class extends Component {
    #[Locked]
    public Room $room;

    public function mount(Room $room): void
    {
        $this->authorize('update', $room);

        $this->room = $room;
    }

    public function rendering($view): void
    {
        $view->title($this->room->name);
    }

    public function with(RoomService $rooms, ShelfViewService $shelfView): array
    {
        $owner = $rooms->ownerOf($this->room);

        return [
            'bookcases' => $rooms->bookcasesWithStructure($this->room),
            'shelfView' => $shelfView,
            'backUrl' => $owner->is(Auth::user()) ? route('rooms.index') : route('admin.users.rooms', $owner),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <a href="{{ $backUrl }}" class="text-sm text-leather underline underline-offset-2" wire:navigate>← {{ __('Salas y estanterías') }}</a>
        <flux:heading size="xl" level="1">{{ $room->name }}</flux:heading>
    </div>

    @if ($bookcases->isEmpty())
        <p class="text-ink-muted">{{ __('Esta sala todavía no tiene estanterías.') }}</p>
    @else
        <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" role="list">
            @foreach ($bookcases as $bookcase)
                <li wire:key="bookcase-card-{{ $bookcase->id }}">
                    <a href="{{ $shelfView->linkFor($bookcase, auth()->user()) }}" class="group block rounded-lg border border-zinc-200 bg-surface p-4 shadow-sm hover:shadow-md dark:border-zinc-700" wire:navigate>
                        {{-- Small drawing: one row per shelf, one cell per compartment. --}}
                        <div data-mini-shelf class="mb-3 space-y-1 rounded-sm border-4 border-[#6b4a2f] bg-[#3b2a1c] p-1" aria-hidden="true">
                            @foreach ($bookcase->shelves as $shelf)
                                <div class="flex h-6 gap-1 border-b-2 border-[#8a6440]">
                                    @for ($compartment = 0; $compartment < $shelf->compartments_count; $compartment++)
                                        <span class="flex-1 rounded-t-sm bg-[#f6efe0]/15"></span>
                                    @endfor
                                </div>
                            @endforeach
                        </div>

                        <span class="block font-semibold text-leather group-hover:underline">{{ $bookcase->name }}</span>
                        <span class="block text-sm text-ink-muted">{{ trans_choice(':count libro|:count libros', $bookcase->books_count) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
