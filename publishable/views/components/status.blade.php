<?php use Cego\RequestInsurance\Enums\State; ?>
<span class="chip chip-{{ $statusColor }}">
    @if($requestInsurance->inOneOfStates(State::PENDING, State::PROCESSING))
        <span class="size-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none"></span>
    @endif
    {{ $statusText }}
</span>
