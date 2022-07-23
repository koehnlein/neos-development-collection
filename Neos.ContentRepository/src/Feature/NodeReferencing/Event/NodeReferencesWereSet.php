<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Feature\NodeReferencing\Event;

use Neos\ContentRepository\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Feature\Common\EmbedsContentStreamAndNodeAggregateIdentifier;
use Neos\ContentRepository\Feature\Common\PublishableToOtherContentStreamsInterface;
use Neos\ContentRepository\SharedModel\Node\NodeAggregateIdentifiers;
use Neos\ContentRepository\SharedModel\Node\OriginDimensionSpacePoint;
use Neos\ContentRepository\SharedModel\Node\PropertyName;
use Neos\ContentRepository\SharedModel\User\UserIdentifier;
use Neos\ContentRepository\EventStore\EventInterface;

/**
 * A named reference from source to destination node was created
 */
final class NodeReferencesWereSet implements
    EventInterface,
    PublishableToOtherContentStreamsInterface,
    EmbedsContentStreamAndNodeAggregateIdentifier
{
    public function __construct(
        public readonly ContentStreamIdentifier $contentStreamIdentifier,
        public readonly NodeAggregateIdentifier $sourceNodeAggregateIdentifier,
        public readonly OriginDimensionSpacePoint $sourceOriginDimensionSpacePoint,
        public readonly NodeAggregateIdentifiers $destinationNodeAggregateIdentifiers,
        public readonly PropertyName $referenceName,
        public readonly UserIdentifier $initiatingUserIdentifier
    ) {
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): self
    {
        return new self(
            $targetContentStreamIdentifier,
            $this->sourceNodeAggregateIdentifier,
            $this->sourceOriginDimensionSpacePoint,
            $this->destinationNodeAggregateIdentifiers,
            $this->referenceName,
            $this->initiatingUserIdentifier
        );
    }

    /**
     * this method is implemented for fulfilling the {@see EmbedsContentStreamAndNodeAggregateIdentifier} interface,
     * needed for proper content cache flushing in Neos.
     *
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->sourceNodeAggregateIdentifier;
    }

    public static function fromArray(array $values): self
    {
        return new self(
            ContentStreamIdentifier::fromString($values['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($values['sourceNodeAggregateIdentifier']),
            OriginDimensionSpacePoint::fromArray($values['sourceOriginDimensionSpacePoint']),
            NodeAggregateIdentifiers::fromArray($values['destinationNodeAggregateIdentifiers']),
            PropertyName::fromString($values['referenceName']),
            UserIdentifier::fromString($values['initiatingUserIdentifier'])
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'sourceNodeAggregateIdentifier' => $this->sourceNodeAggregateIdentifier,
            'sourceOriginDimensionSpacePoint' => $this->sourceOriginDimensionSpacePoint,
            'destinationNodeAggregateIdentifiers' => $this->destinationNodeAggregateIdentifiers,
            'referenceName' => $this->referenceName,
            'initiatingUserIdentifier' => $this->initiatingUserIdentifier
        ];
    }
}
