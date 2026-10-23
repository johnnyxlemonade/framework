<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

use JsonException;
use Lemonade\Framework\Queue\Exception\InvalidQueuePayloadException;
use LogicException;

/**
 * Persists registered messages in bounded, versioned JSON envelopes authenticated before hydration.
 *
 * This serializer is a database-transport boundary. It never accepts legacy PHP serialization and
 * derives a queue-specific HMAC key from the application's stable secret.
 *
 * @internal Applications should register MessageCodecInterface implementations instead of invoking
 * this persistence boundary directly.
 */
final readonly class MessageSerializer
{
    private const int ENVELOPE_VERSION = 1;
    private const string KEY_INFO = 'lemonade.queue.message-envelope.v1';

    private string $authenticationKey;

    /**
     * Creates a bounded serializer over an explicit codec allow-list and application secret.
     *
     * @throws LogicException When the application secret or safety limits are invalid.
     */
    public function __construct(
        private MessageCodecRegistry $codecs,
        string $applicationSecret,
        private int $maxPayloadBytes = 1_048_576,
        private int $maxJsonDepth = 32,
    ) {
        if (trim($applicationSecret) === '') {
            throw new LogicException('APP_SECRET must be configured before using the database queue transport.');
        }

        if ($this->maxPayloadBytes < 256) {
            throw new LogicException('Queue payload byte limit must be at least 256 bytes.');
        }

        if ($this->maxJsonDepth < 4 || $this->maxJsonDepth > 512) {
            throw new LogicException('Queue JSON depth limit must be between 4 and 512.');
        }

        $this->authenticationKey = hash_hkdf(
            'sha256',
            $applicationSecret,
            32,
            self::KEY_INFO,
        );
    }

    /**
     * Encodes a registered message as an authenticated envelope suitable for database persistence.
     *
     * @throws LogicException When the message is unregistered or its codec returns unsupported data.
     */
    public function encode(object $message): string
    {
        $encoded = $this->codecs->encode($message);
        $this->assertJsonValue($encoded['payload'], 1);

        try {
            $body = json_encode(
                [
                    'version' => $encoded['version'],
                    'type' => $encoded['type'],
                    'payload' => $encoded['payload'],
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                $this->maxJsonDepth,
            );

            $body = base64_encode($body);
            $envelope = json_encode(
                [
                    'envelope_version' => self::ENVELOPE_VERSION,
                    'body' => $body,
                    'mac' => hash_hmac('sha256', $body, $this->authenticationKey),
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                $this->maxJsonDepth,
            );
        } catch (JsonException $exception) {
            throw new LogicException('Queue message codec returned data that cannot be encoded as JSON.', 0, $exception);
        }

        if (strlen($envelope) > $this->maxPayloadBytes) {
            throw new LogicException('Encoded queue payload exceeds the configured byte limit.');
        }

        return $envelope;
    }

    /**
     * Verifies a persisted envelope before resolving its alias or invoking a hydration codec.
     *
     * @throws InvalidQueuePayloadException When the envelope is unsafe or unsupported.
     */
    public function decode(string $payload): object
    {
        if ($payload === '' || strlen($payload) > $this->maxPayloadBytes) {
            throw InvalidQueuePayloadException::malformed();
        }

        $envelope = $this->decodeJsonObject($payload);
        $this->assertExactFields($envelope, ['envelope_version', 'body', 'mac']);

        if (($envelope['envelope_version'] ?? null) !== self::ENVELOPE_VERSION) {
            throw InvalidQueuePayloadException::unsupportedVersion();
        }

        $body = $envelope['body'] ?? null;
        $mac = $envelope['mac'] ?? null;

        if (!is_string($body) || !is_string($mac) || preg_match('/^[a-f0-9]{64}$/D', $mac) !== 1) {
            throw InvalidQueuePayloadException::malformed();
        }

        $expectedMac = hash_hmac('sha256', $body, $this->authenticationKey);
        if (!hash_equals($expectedMac, $mac)) {
            throw InvalidQueuePayloadException::invalidMac();
        }

        $decodedBody = base64_decode($body, true);
        if ($decodedBody === false || $decodedBody === '') {
            throw InvalidQueuePayloadException::malformed();
        }

        $message = $this->decodeJsonObject($decodedBody);
        $this->assertExactFields($message, ['version', 'type', 'payload']);

        $version = $message['version'] ?? null;
        $type = $message['type'] ?? null;
        $data = $message['payload'] ?? null;

        if (!is_int($version) || $version < 1 || !is_string($type) || !is_array($data)) {
            throw InvalidQueuePayloadException::malformed();
        }

        $this->assertJsonValue($data, 1, InvalidQueuePayloadException::malformed(...));

        return $this->codecs->decode($type, $version, $data);
    }

    /** @return array<string, mixed> */
    private function decodeJsonObject(string $json): array
    {
        try {
            $decoded = json_decode(
                $json,
                true,
                $this->maxJsonDepth,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw InvalidQueuePayloadException::malformed($exception);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw InvalidQueuePayloadException::malformed();
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $value
     * @param list<string> $fields
     */
    private function assertExactFields(array $value, array $fields): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($fields);

        if ($actual !== $fields) {
            throw InvalidQueuePayloadException::malformed();
        }
    }

    private function assertJsonValue(
        mixed $value,
        int $depth,
        ?callable $failure = null,
    ): void {
        if ($depth > $this->maxJsonDepth) {
            $this->failJsonValue($failure);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertJsonValue($item, $depth + 1, $failure);
            }

            return;
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            if (is_float($value) && !is_finite($value)) {
                $this->failJsonValue($failure);
            }

            return;
        }

        $this->failJsonValue($failure);
    }

    private function failJsonValue(?callable $failure): never
    {
        if ($failure !== null) {
            throw $failure();
        }

        throw new LogicException('Queue message codecs may return only bounded JSON-compatible arrays and scalar values.');
    }
}
