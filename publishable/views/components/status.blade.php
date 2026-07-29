<?php use Cego\RequestInsurance\Enums\State; ?>
{{-- Also rendered by EditApprovalsStatus, which provides no $requestInsurance. --}}
<span class="chip chip-{{ $statusColor }}">
    @if(isset($requestInsurance) && $requestInsurance->inOneOfStates(State::PENDING, State::PROCESSING))
        <span class="size-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none"></span>
    @endif
    {{ $statusText }}
</span>
