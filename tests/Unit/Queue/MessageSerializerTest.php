<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Queue;

use DateTimeImmutable;
use Lemonade\Framework\Queue\Exception\InvalidQueuePayloadException;
use Lemonade\Framework\Queue\MessageCodecInterface;
use Lemonade\Framework\Queue\MessageCodecRegistry;
use Lemonade\Framework\Queue\MessageSerializer;
use PHPUnit\Framework\TestCase;

final class MessageSerializerTest extends TestCase
{
    private const string SECRET = 'queue-test-secret-with-sufficient-entropy';
    private const string KEY_INFO = 'lemonade.queue.message-envelope.v1';

    public function testExplicitCodecRoundTripsReadonlyMessageValueObjectEnumAndDate(): void
    {
        $serializer = $this->serializer();
        $message = new SerializerMessage(
            id: new SerializerMessageId('job-42'),
            state: SerializerMessageState::Ready,
            scheduledAt: new DateTimeImmutable('2026-10-23T12:34:56+00:00'),
        );

        $decoded = $serializer->decode($serializer->encode($message));

        self::assertEquals($message, $decoded);
        self::assertNotSame($message, $decoded);
    }

    public function testUnregisteredMessageCannotBePersisted(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no registered codec');

        $this->serializer()->encode(new \stdClass());
    }

    public function testLegacyPhpSerializationNeverInvokesMagicMethods(): void
    {
        SerializerWakeupPayload::$wakeups = 0;
        SerializerWakeupPayload::$destructs = 0;
        $legacy = sprintf(
            'O:%d:"%s":0:{}',
            strlen(SerializerWakeupPayload::class),
            SerializerWakeupPayload::class,
        );

        try {
            $this->serializer()->decode(base64_encode($legacy));
            self::fail('Expected legacy payload rejection.');
        } catch (InvalidQueuePayloadException) {
            self::assertSame(0, SerializerWakeupPayload::$wakeups);
            self::assertSame(0, SerializerWakeupPayload::$destructs);
        }
    }

    public function testChangedBodyIsRejectedBeforeCodecHydration(): void
    {
        SerializerMessageCodec::$decodeCalls = 0;
        $serializer = $this->serializer();
        $envelope = $this->jsonObject($serializer->encode($this->message()));
        self::assertIsString($envelope['body']);
        $envelope['body'] .= 'A';

        try {
            $serializer->decode($this->json($envelope));
            self::fail('Expected body integrity failure.');
        } catch (InvalidQueuePayloadException $exception) {
            self::assertStringContainsString('integrity', $exception->getMessage());
            self::assertSame(0, SerializerMessageCodec::$decodeCalls);
        }
    }

    public function testChangedMacIsRejectedBeforeCodecHydration(): void
    {
        SerializerMessageCodec::$decodeCalls = 0;
        $serializer = $this->serializer();
        $envelope = $this->jsonObject($serializer->encode($this->message()));
        $envelope['mac'] = str_repeat('0', 64);

        $this->expectException(InvalidQueuePayloadException::class);

        try {
            $serializer->decode($this->json($envelope));
        } finally {
            self::assertSame(0, SerializerMessageCodec::$decodeCalls);
        }
    }

    public function testAuthenticatedUnknownTypeIsRejectedWithoutHydration(): void
    {
        SerializerMessageCodec::$decodeCalls = 0;
        $payload = $this->signedEnvelope([
            'version' => 1,
            'type' => 'unknown.message',
            'payload' => [],
        ]);

        $this->expectException(InvalidQueuePayloadException::class);
        $this->expectExceptionMessage('type is not registered');

        try {
            $this->serializer()->decode($payload);
        } finally {
            self::assertSame(0, SerializerMessageCodec::$decodeCalls);
        }
    }

    public function testAuthenticatedUnsupportedCodecVersionIsRejectedWithoutHydration(): void
    {
        SerializerMessageCodec::$decodeCalls = 0;
        $payload = $this->signedEnvelope([
            'version' => 2,
            'type' => 'test.message',
            'payload' => [],
        ]);

        $this->expectException(InvalidQueuePayloadException::class);
        $this->expectExceptionMessage('version is not supported');

        try {
            $this->serializer()->decode($payload);
        } finally {
            self::assertSame(0, SerializerMessageCodec::$decodeCalls);
        }
    }

    public function testUnsupportedEnvelopeVersionIsRejected(): void
    {
        $serializer = $this->serializer();
        $envelope = $this->jsonObject($serializer->encode($this->message()));
        $envelope['envelope_version'] = 2;

        $this->expectException(InvalidQueuePayloadException::class);
        $this->expectExceptionMessage('version is not supported');

        $serializer->decode($this->json($envelope));
    }

    public function testOversizedEnvelopeIsRejectedBeforeParsing(): void
    {
        $serializer = $this->serializer(maxPayloadBytes: 256);

        $this->expectException(InvalidQueuePayloadException::class);
        $serializer->decode(str_repeat('{', 257));
    }

    public function testExcessivelyDeepAuthenticatedJsonIsRejected(): void
    {
        $payload = ['leaf' => 'value'];
        for ($depth = 0; $depth < 8; $depth++) {
            $payload = ['nested' => $payload];
        }

        $this->expectException(InvalidQueuePayloadException::class);
        $this->serializer(maxJsonDepth: 6)->decode($this->signedEnvelope([
            'version' => 1,
            'type' => 'test.message',
            'payload' => $payload,
        ]));
    }

    public function testCodecCannotReturnObjectsForImplicitJsonSerialization(): void
    {
        $registry = new MessageCodecRegistry();
        $registry->register('unsafe.message', SerializerMessage::class, new class implements MessageCodecInterface {
            public function encode(object $message): array
            {
                unset($message);

                return ['object' => new \stdClass()];
            }

            public function decode(array $payload): object
            {
                unset($payload);

                return new SerializerMessage(
                    new SerializerMessageId('unused'),
                    SerializerMessageState::Ready,
                    new DateTimeImmutable(),
                );
            }
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('only bounded JSON-compatible');

        (new MessageSerializer($registry, self::SECRET))->encode($this->message());
    }

    public function testRegistryRejectsAliasAndClassCollisions(): void
    {
        $registry = $this->registry();

        $this->expectException(\LogicException::class);
        $registry->register('test.message', SerializerOtherMessage::class, new SerializerOtherMessageCodec());
    }

    public function testSerializerRequiresApplicationSecret(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('APP_SECRET');

        new MessageSerializer($this->registry(), '  ');
    }

    public function testChangingApplicationSecretInvalidatesExistingEnvelope(): void
    {
        $payload = $this->serializer()->encode($this->message());
        $rotated = new MessageSerializer(
            $this->registry(),
            'rotated-queue-test-secret-with-entropy',
        );

        $this->expectException(InvalidQueuePayloadException::class);
        $this->expectExceptionMessage('integrity');

        $rotated->decode($payload);
    }

    private function serializer(
        int $maxPayloadBytes = 1_048_576,
        int $maxJsonDepth = 32,
    ): MessageSerializer {
        return new MessageSerializer(
            codecs: $this->registry(),
            applicationSecret: self::SECRET,
            maxPayloadBytes: $maxPayloadBytes,
            maxJsonDepth: $maxJsonDepth,
        );
    }

    private function registry(): MessageCodecRegistry
    {
        $registry = new MessageCodecRegistry();
        $registry->register('test.message', SerializerMessage::class, new SerializerMessageCodec());

        return $registry;
    }

    private function message(): SerializerMessage
    {
        return new SerializerMessage(
            new SerializerMessageId('job-1'),
            SerializerMessageState::Ready,
            new DateTimeImmutable('2026-10-23T00:00:00+00:00'),
        );
    }

    /** @param array<string, mixed> $body */
    private function signedEnvelope(array $body): string
    {
        $encodedBody = base64_encode($this->json($body));
        $key = hash_hkdf('sha256', self::SECRET, 32, self::KEY_INFO);

        return $this->json([
            'envelope_version' => 1,
            'body' => $encodedBody,
            'mac' => hash_hmac('sha256', $encodedBody, $key),
        ]);
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function jsonObject(string $json): array
    {
        $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($value);

        return $value;
    }
}

enum SerializerMessageState: string
{
    case Ready = 'ready';
}

final readonly class SerializerMessageId
{
    public function __construct(public string $value)
    {
    }
}

final readonly class SerializerMessage
{
    public function __construct(
        public SerializerMessageId $id,
        public SerializerMessageState $state,
        public DateTimeImmutable $scheduledAt,
    ) {
    }
}

final readonly class SerializerOtherMessage
{
}

final class SerializerMessageCodec implements MessageCodecInterface
{
    public static int $decodeCalls = 0;

    public function encode(object $message): array
    {
        if (!$message instanceof SerializerMessage) {
            throw new \InvalidArgumentException('Unexpected test message.');
        }

        return [
            'id' => $message->id->value,
            'state' => $message->state->value,
            'scheduled_at' => $message->scheduledAt->format(DATE_ATOM),
        ];
    }

    public function decode(array $payload): object
    {
        self::$decodeCalls++;
        $id = $payload['id'] ?? null;
        $state = $payload['state'] ?? null;
        $scheduledAt = $payload['scheduled_at'] ?? null;

        if (!is_string($id) || !is_string($state) || !is_string($scheduledAt)) {
            throw new \InvalidArgumentException('Invalid test message payload.');
        }

        return new SerializerMessage(
            id: new SerializerMessageId($id),
            state: SerializerMessageState::from($state),
            scheduledAt: new DateTimeImmutable($scheduledAt),
        );
    }
}

final class SerializerOtherMessageCodec implements MessageCodecInterface
{
    public function encode(object $message): array
    {
        unset($message);

        return [];
    }

    public function decode(array $payload): object
    {
        unset($payload);

        return new SerializerOtherMessage();
    }
}

final class SerializerWakeupPayload
{
    public static int $wakeups = 0;
    public static int $destructs = 0;

    public function __wakeup(): void
    {
        self::$wakeups++;
    }

    public function __destruct()
    {
        self::$destructs++;
    }
}
