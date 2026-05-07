<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Thrown by HttpBackofficeApi when the upstream Backoffice API returns
 * a non-2xx response. It carries the parsed error envelope from the
 * API spec so controllers and views can render friendly alerts.
 */
class BackofficeApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly ?string $correlationId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response, ?Throwable $previous = null): self
    {
        $status = $response->status();
        $body = null;

        try {
            $body = $response->json();
        } catch (Throwable) {
            $body = null;
        }

        // Most controller errors use { "error": { code, message, details, correlationId } }.
        // Spring Security 401/403 responses use raw { code, message, ... }.
        $err = is_array($body) && isset($body['error']) && is_array($body['error'])
            ? $body['error']
            : (is_array($body) ? $body : []);

        $code = is_string($err['code'] ?? null) ? $err['code'] : null;
        $message = is_string($err['message'] ?? null) && $err['message'] !== ''
            ? $err['message']
            : self::defaultMessageFor($status);

        $details = self::flattenDetails($err['details'] ?? []);

        $correlationId = is_string($err['correlationId'] ?? null) ? $err['correlationId'] : null;

        return new self($status, $code, $message, $details, $correlationId, $previous);
    }

    private static function defaultMessageFor(int $status): string
    {
        return match (true) {
            $status === 401 => 'Your session has expired. Please sign in again.',
            $status === 403 => 'You do not have permission to perform this action.',
            $status === 404 => 'The requested resource could not be found.',
            $status === 409 => 'The request conflicts with the current state.',
            $status === 422 => 'Some fields are invalid.',
            $status === 429 => 'Too many requests. Please slow down and try again.',
            $status >= 500 => 'The Backoffice service is temporarily unavailable. Please try again.',
            default => 'The request could not be completed.',
        };
    }

    private static function flattenDetails(mixed $value, ?string $prefix = null): array
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? [] : [$prefix ? "{$prefix}: {$value}" : $value];
        }

        if (is_scalar($value)) {
            return [$prefix ? "{$prefix}: {$value}" : (string) $value];
        }

        if (! is_array($value)) {
            return [];
        }

        $messages = [];

        foreach ($value as $key => $item) {
            $itemPrefix = is_string($key)
                ? ($prefix ? "{$prefix}.{$key}" : $key)
                : $prefix;

            $messages = array_merge($messages, self::flattenDetails($item, $itemPrefix));
        }

        return $messages;
    }

    public function userMessage(): string
    {
        return $this->details
            ? rtrim($this->message, '.').' - '.implode('; ', array_slice($this->details, 0, 3))
            : $this->message;
    }
}
