<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Feature\NodeRemoval;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\ContentRepository\EventStore\Events;
use Neos\ContentRepository\EventStore\EventsToPublish;
use Neos\ContentRepository\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Feature\ContentStreamEventStreamName;
use Neos\ContentRepository\Feature\Common\Exception\ContentStreamDoesNotExistYet;
use Neos\ContentRepository\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Feature\Common\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\ContentRepository\Feature\Common\Exception\TetheredNodeAggregateCannotBeRemoved;
use Neos\ContentRepository\Feature\Common\NodeAggregateEventPublisher;
use Neos\ContentRepository\SharedModel\Node\ReadableNodeAggregateInterface;
use Neos\ContentRepository\Service\Infrastructure\ReadSideMemoryCacheManager;
use Neos\EventStore\Model\EventStream\ExpectedVersion;

trait NodeRemoval
{
    abstract protected function getReadSideMemoryCacheManager(): ReadSideMemoryCacheManager;

    abstract protected function getNodeAggregateEventPublisher(): NodeAggregateEventPublisher;

    abstract protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph;

    abstract protected function areAncestorNodeTypeConstraintChecksEnabled(): bool;

    /**
     * @param RemoveNodeAggregate $command
     * @return EventsToPublish
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws ContentStreamDoesNotExistYet
     * @throws DimensionSpacePointNotFound
     */
    private function handleRemoveNodeAggregate(RemoveNodeAggregate $command): EventsToPublish
    {
        $this->getReadSideMemoryCacheManager()->disableCache();

        $this->requireContentStreamToExist($command->contentStreamIdentifier);
        $nodeAggregate = $this->requireProjectedNodeAggregate(
            $command->contentStreamIdentifier,
            $command->nodeAggregateIdentifier
        );
        $this->requireDimensionSpacePointToExist($command->coveredDimensionSpacePoint);
        $this->requireNodeAggregateNotToBeTethered($nodeAggregate);
        $this->requireNodeAggregateToCoverDimensionSpacePoint(
            $nodeAggregate,
            $command->coveredDimensionSpacePoint
        );
        if ($command->removalAttachmentPoint instanceof NodeAggregateIdentifier) {
            $this->requireProjectedNodeAggregate(
                $command->contentStreamIdentifier,
                $command->removalAttachmentPoint
            );
        }

        $events = Events::with(
            new NodeAggregateWasRemoved(
                $command->contentStreamIdentifier,
                $command->nodeAggregateIdentifier,
                $command->nodeVariantSelectionStrategy->resolveAffectedOriginDimensionSpacePoints(
                    $nodeAggregate->getOccupationByCovered($command->coveredDimensionSpacePoint),
                    $nodeAggregate,
                    $this->getInterDimensionalVariationGraph()
                ),
                $command->nodeVariantSelectionStrategy->resolveAffectedDimensionSpacePoints(
                    $command->coveredDimensionSpacePoint,
                    $nodeAggregate,
                    $this->getInterDimensionalVariationGraph()
                ),
                $command->initiatingUserIdentifier,
                $command->removalAttachmentPoint
            )
        );

        return new EventsToPublish(
            ContentStreamEventStreamName::fromContentStreamIdentifier($command->contentStreamIdentifier)
                ->getEventStreamName(),
            $this->getNodeAggregateEventPublisher()->enrichWithCommand(
                $command,
                $events
            ),
            ExpectedVersion::ANY()
        );
    }

    protected function requireNodeAggregateNotToBeTethered(ReadableNodeAggregateInterface $nodeAggregate): void
    {
        if ($nodeAggregate->isTethered()) {
            throw new TetheredNodeAggregateCannotBeRemoved(
                'The node aggregate "' . $nodeAggregate->getIdentifier() . '" is tethered, and thus cannot be removed.',
                1597753832
            );
        }
    }
}
