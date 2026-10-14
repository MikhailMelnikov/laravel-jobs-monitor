<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Support\Facades\Lang;

/**
 * Parser of a queue job payload.
 *
 * The jobs and failed_jobs tables keep a job as serialized JSON; the UI needs
 * the job name and a readable representation of its data.
 */
final class JobPayloadParser
{
    /**
     * Decoded payload — json_decode runs once per instance.
     *
     * @var array<string, mixed>|null
     */
    private ?array $decoded = null;

    /**
     * @param string $payload JSON payload from the queue table
     */
    public function __construct(private readonly string $payload)
    {
    }

    /**
     * Job name: the job class or, when absent, the handler type.
     */
    public function name(): string
    {
        $payload = $this->decode();

        $name = $payload['displayName']
            ?? $payload['data']['commandName']
            ?? $payload['job']
            ?? null;

        return is_string($name) && $name !== '' ? $name : (string) Lang::get('jobs-monitor::messages.unknown_job');
    }

    /**
     * Reference to the notification recipient, or null when the job sends nothing.
     *
     * The payload holds no ready address: SerializesModels replaces the notifiable
     * with a ModelIdentifier (class + id), and only AnonymousNotifiable keeps the
     * address in plain text. So a reference is returned here and the address is
     * fetched by {@see \MikhailMelnikov\JobsMonitor\UseCase\ResolveJobRecipientsHandler}.
     *
     * Objects are restored with an allowed_classes list: everything except
     * ModelIdentifier becomes __PHP_Incomplete_Class, which runs neither __wakeup
     * nor __unserialize. Parsing therefore loads no models and executes no foreign
     * code — important because failed jobs are shown in batches on every page.
     *
     * @return array{emails: string[], class: string|null, ids: array<int, int|string>}|null
     */
    public function recipientReference(): ?array
    {
        $command = $this->decode()['data']['command'] ?? null;

        if (!is_string($command) || $command === '') {
            return null;
        }

        $job = @unserialize($command, ['allowed_classes' => [ModelIdentifier::class]]);

        if (!is_object($job)) {
            return null;
        }

        $notifiables = $this->properties($job)['notifiables'] ?? null;

        if ($notifiables === null) {
            return null;
        }

        $emails = [];
        $class  = null;
        $ids    = [];

        foreach ($this->asList($notifiables) as $notifiable) {
            if ($notifiable instanceof ModelIdentifier) {
                // a single job never mixes recipients of different classes — take the first one
                $class ??= $notifiable->class;

                if ($class === $notifiable->class) {
                    $ids = array_merge($ids, (array) $notifiable->id);
                }

                continue;
            }

            if (is_object($notifiable)) {
                $emails = array_merge($emails, $this->routedEmails($notifiable));
            }
        }

        $emails = array_values(array_unique(array_filter($emails)));
        $ids    = array_values(array_unique($ids));

        if ($emails === [] && $ids === []) {
            return null;
        }

        return ['emails' => $emails, 'class' => $class, 'ids' => $ids];
    }

    /**
     * Payload as pretty-printed JSON for display.
     *
     * The serialized job object is dropped: it takes the whole screen but tells
     * the administrator nothing.
     */
    public function pretty(): string
    {
        $payload = $this->decode();

        if ($payload === []) {
            return $this->payload;
        }

        unset($payload['data']['command']);

        $pretty = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $pretty === false ? $this->payload : $pretty;
    }

    /**
     * Unwrap the recipients into a list.
     *
     * A single notifiable is stored as is, several of them — as a collection,
     * so both shapes have to be walked.
     *
     * @param mixed $notifiables Value of the notifiables property
     *
     * @return array<int, mixed>
     */
    private function asList(mixed $notifiables): array
    {
        if (is_array($notifiables)) {
            return array_values($notifiables);
        }

        if (is_object($notifiables)) {
            $items = $this->properties($notifiables)['items'] ?? null;

            if (is_array($items)) {
                return array_values($items);
            }
        }

        return [$notifiables];
    }

    /**
     * Object properties keyed by their names.
     *
     * Casting an object to an array gives protected and private properties
     * NUL-wrapped names ("\0*\0items"), so the wrapping is stripped.
     *
     * @param object $object Object restored from the payload
     *
     * @return array<string, mixed>
     */
    private function properties(object $object): array
    {
        $properties = [];

        foreach ((array) $object as $key => $value) {
            $name = strrpos($key, "\0");

            $properties[$name === false ? $key : substr($key, $name + 1)] = $value;
        }

        return $properties;
    }

    /**
     * Addresses of an AnonymousNotifiable — a notification routed via Notification::route('mail', ...).
     *
     * The route may be a string, a list of addresses or an "address => name" map,
     * so the address is taken from a string key, otherwise from the value.
     *
     * @param object $notifiable Recipient from the payload
     *
     * @return string[]
     */
    private function routedEmails(object $notifiable): array
    {
        $routes = $this->properties($notifiable)['routes'] ?? null;

        if (!is_array($routes) || !isset($routes['mail'])) {
            return [];
        }

        $emails = [];

        foreach ((array) $routes['mail'] as $key => $value) {
            $email = is_string($key) ? $key : $value;

            if (is_string($email) && $email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Decode the payload JSON.
     *
     * @return array<string, mixed> Empty array when the payload is not a JSON object
     */
    private function decode(): array
    {
        if ($this->decoded !== null) {
            return $this->decoded;
        }

        $decoded = json_decode($this->payload, true);

        return $this->decoded = is_array($decoded) ? $decoded : [];
    }
}
