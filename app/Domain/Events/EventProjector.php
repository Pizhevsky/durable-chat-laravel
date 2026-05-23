<?php

namespace App\Domain\Events;

use App\Contracts\ChatProjectionRepositoryInterface;
use App\Domain\Shared\DomainRuleException;

final readonly class EventProjector
{
    public function __construct(
        private ChatProjectionRepositoryInterface $projection,
        private EventProjectionRules $rules,
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
        $memberIds = $this->rules->uniqueChatMemberIds($event);
        $directPairKey = $payload['type'] === 'direct'
            ? $this->rules->directPairKeyFor($memberIds)
            : null;

        $this->rules->assertDirectChatDoesNotExist($directPairKey);
        $this->rules->assertUsersExist($memberIds);

        $this->projection->createChat($event, $memberIds, $directPairKey);
    }

    private function projectMemberAdded(ChatEventDto $event): void
    {
        $this->rules->assertGroupOwner($event->payload['chatId'], $event->actorUserId);

        $memberId = (string) $event->payload['memberId'];
        $this->rules->assertUserExists($memberId);

        $this->projection->addMember($event->payload['chatId'], $memberId, $event->createdAt, false);
    }

    private function projectMemberRemoved(ChatEventDto $event): void
    {
        $chat = $this->rules->assertGroupOwner($event->payload['chatId'], $event->actorUserId);
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
        $this->rules->assertActiveMember($event->payload['chatId'], $event->actorUserId);

        $this->projection->createMessage($event);
        $this->projection->markMessageRead($event->payload['messageId'], $event->actorUserId, $event->createdAt);
    }

    private function projectMessageRead(ChatEventDto $event): void
    {
        $this->rules->assertActiveMember($event->payload['chatId'], $event->actorUserId);

        $this->projection->markMessageRead($event->payload['messageId'], $event->actorUserId, $event->createdAt);
    }
}
