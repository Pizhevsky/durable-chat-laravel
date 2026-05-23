<?php

namespace Tests\Unit;

use App\Domain\Chats\DirectChatKeyFactory;
use App\Domain\Events\ChatEventDto;
use App\Domain\Events\EventPayloadFields;
use App\Domain\Events\EventPayloadValidator;
use App\Domain\Events\EventSyncStatus;
use App\Domain\Events\EventType;
use App\Domain\Events\EventValidator;
use App\Domain\Shared\DomainRuleException;
use PHPUnit\Framework\TestCase;

final class EventValidatorTest extends TestCase
{
    public function test_it_accepts_a_valid_message_created_event(): void
    {
        $event = new ChatEventDto(
            eventId: 'device-1:event-1',
            originNodeId: 'helper-demo',
            originDeviceId: 'device-1',
            actorUserId: 'u-denis',
            chatId: 'chat-1',
            type: EventType::MessageCreated,
            payload: [
                'chatId' => 'chat-1',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => 'Hello from helper',
            ],
            createdAt: '2026-05-20T10:00:00.000Z',
            logicalClock: 1,
            syncStatus: EventSyncStatus::Local,
        );

        $this->validator()->validate($event);

        self::assertTrue(true);
    }

    public function test_it_rejects_empty_message_text(): void
    {
        $this->expectException(DomainRuleException::class);

        $event = new ChatEventDto(
            eventId: 'device-1:event-1',
            originNodeId: 'helper-demo',
            originDeviceId: 'device-1',
            actorUserId: 'u-denis',
            chatId: 'chat-1',
            type: EventType::MessageCreated,
            payload: [
                'chatId' => 'chat-1',
                'messageId' => 'message-1',
                'clientMessageId' => 'client-message-1',
                'text' => '   ',
            ],
            createdAt: '2026-05-20T10:00:00.000Z',
            logicalClock: 1,
            syncStatus: EventSyncStatus::Local,
        );

        $this->validator()->validate($event);
    }

    private function validator(): EventValidator
    {
        return new EventValidator(new EventPayloadValidator(new DirectChatKeyFactory, new EventPayloadFields));
    }
}
