<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleTest extends TestCase
{
    /** @test */
    public function it_has_auto_scheduling_of_cleanup_and_unlock_commands(): void
    {
        // Assert
        $this->assertScheduleHasCommand('unlock:request-insurances');
        $this->assertScheduleHasCommand('clean:request-insurances');
    }

    /**
     * Utility method to assert that a given command is within the scheduler
     *
     * @param string $command
     */
    protected function assertScheduleHasCommand(string $command): void
    {
        /** @var Event[] $events */
        $events = resolve(Schedule::class)->events();

        $hasCommand = false;

        foreach ($events as $event) {
            $hasCommand = $hasCommand || Str::endsWith($event->command, $command);
        }

        $this->assertTrue($hasCommand, sprintf('Schedule event list does not contain the given command [%s]', $command));
    }
}
