<?php

namespace OpenDominion\Listeners\Subscribers;

use Illuminate\Events\Dispatcher;
use OpenDominion\Events\UserActivatedEvent;
use OpenDominion\Events\UserLoggedInEvent;
use OpenDominion\Events\UserLoggedOutEvent;
use OpenDominion\Events\UserRegisteredEvent;

class AnalyticsSubscriber implements SubscriberInterface
{
    /** @var AnalyticsService */
    protected $analyticsService;

    /** @var string[] */
    protected $events = [
        UserActivatedEvent::class,
        UserLoggedInEvent::class,
        UserRegisteredEvent::class,
        UserLoggedOutEvent::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function subscribe(Dispatcher $events): void
    {
        // Nothiing
    }
}
