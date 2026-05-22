<?php

namespace App\Domain\Events;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Shared\DomainRuleException;

final readonly class EventProjector
{
    public function __construct(
        private ChatProjectionRepositoryInterface $projection,
        private DirectChatKeyFactory $directChatKeyFactory,
    ) {}

    public function project(ChatEventDto $event): void
    {
        match ($event->type) {
            EventType::ChatCreated => $this->projectChatCreated($event),
            EventType::MemberAdded => $this->projectMemberAdded($event),
            EventType::MemberRemoved => $this->projectMemberRemoved($event),
            EventType::MessageCreated => $this->projectMessageCreated($event),
            EventType::MessageRead => $this->projectMessageRead($event),
        };
    }

    private function projectChatCreated(ChatEventDto $event): void
    {
        $payload = $event->payload;
        $memberIds = array_values(array_unique([$event->actorUserId, ...$payload['memberIds']]));
        $directPairKey = $payload['type'] === 'direct'
            ? $this->directChatKeyFactory->make($memberIds)
            : null;

        if ($directPairKey !== null && $this->projection->directChatIdByPairKey($directPairKey) !== null) {
            throw new DomainRuleException('A direct chat for this pair already exists.', 409, 'DIRECT_CHAT_EXISTS');
        }

        foreach ($memberIds as $memberId) {
            if (! $this->projection->userExists($memberId)) {
                throw new DomainRuleException("Unknown user: {$memberId}", 404, 'USER_NOT_FOUND');
            }
        }

        $this->projection->createChat($event, $memberIds, $directPairKey);
    }

    private function projectMemberAdded(ChatEventDto $event): void
    {
        $this->assertGroupOwner($event->payload['chatId'], $event->actorUserId);

        $memberId = (string) $event->payload['memberId'];
        if (! $this->projection->userExists($memberId)) {
            throw new DomainRuleException("Unknown user: {$memberId}", 404, 'USER_NOT_FOUND');
        }

        $this->projection->addMember($event->payload['chatId'], $memberId, $event->createdAt, false);
    }

    private function projectMemberRemoved(ChatEventDto $event): void
    {
        $chat = $this->assertGroupOwner($event->payload['chatId'], $event->actorUserId);
        $memberId = (string) $event->payload['memberId'];

        if ($chat['type'] === 'direct') {
            throw new DomainRuleException('Direct chats cannot be left or reduced in outage mode.', 422, 'DIRECT_CHAT_LOCKED');
        }

        if ($memberId === $event->actorUserId) {
            throw new DomainRuleException('Owners cannot remove themselves in this demo.', 422, 'OWNER_CANNOT_LEAVE');
        }

        if (! $this->projection->removeMember($event->payload['chatId'], $memberId, $event->createdAt)) {
            throw new DomainRuleException('Member not found or is the group owner.', 404, 'MEMBER_NOT_FOUND');
        }
    }

    private function projectMessageCreated(ChatEventDto $event): void
    {
        if (! $this->projection->isActiveMember($event->payload['chatId'], $event->actorUserId)) {
            throw new DomainRuleException('User is not an active chat member.', 403, 'NOT_CHAT_MEMBER');
        }

        $this->projection->createMessage($event);
        $this->projection->markMessageRead($event->payload['messageId'], $event->actorUserId, $event->createdAt);
    }

    private function projectMessageRead(ChatEventDto $event): void
    {
        if (! $this->projection->isActiveMember($event->payload['chatId'], $event->actorUserId)) {
            throw new DomainRuleException('User is not an active chat member.', 403, 'NOT_CHAT_MEMBER');
        }

        $this->projection->markMessageRead($event->payload['messageId'], $event->actorUserId, $event->createdAt);
    }

    /** @return array{id: string, type: string} */
    private function assertGroupOwner(string $chatId, string $userId): array
    {
        $chat = $this->projection->findChat($chatId);
        if ($chat === null) {
            throw new DomainRuleException('Chat not found.', 404, 'CHAT_NOT_FOUND');
        }

        if ($chat['type'] !== 'group') {
            throw new DomainRuleException('Membership changes are only supported for group chats.', 422, 'NOT_GROUP_CHAT');
        }

        if (! $this->projection->isGroupOwner($chatId, $userId)) {
            throw new DomainRuleException('Only the group owner can change members.', 403, 'NOT_GROUP_OWNER');
        }

        return $chat;
    }
}
