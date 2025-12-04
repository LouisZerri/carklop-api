<?php

namespace App\Scheduler;

use App\Message\ProcessTransfersMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        $schedule->add(
            RecurringMessage::every('15 minutes', new ProcessTransfersMessage())
        );

        return $schedule;
    }
}