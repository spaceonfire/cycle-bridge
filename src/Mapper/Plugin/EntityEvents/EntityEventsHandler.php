<?php

declare(strict_types=1);

namespace spaceonfire\Bridge\Cycle\Mapper\Plugin\EntityEvents;

use Psr\EventDispatcher\EventDispatcherInterface;
use spaceonfire\Bridge\Cycle\Mapper\Plugin\QueueAfterEvent;
use spaceonfire\DataSource\EntityEventsInterface;

final class EntityEventsHandler
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function handle(QueueAfterEvent $event): void
    {
        $entity = $event->getEntity();

        if (!$entity instanceof EntityEventsInterface) {
            return;
        }

        $events = $entity->releaseEvents();
        if (0 === \count($events)) {
            return;
        }

        $command = $event->makeSequence($event->getCommand());
        $command->addCommand(new DispatchEventsCommand($events, $this->dispatcher));

        $event->replaceCommand($command);
    }
}
