<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MikhailMelnikov\JobsMonitor\Support\JobPayloadParser;
use Throwable;

/**
 * Recipient addresses of failed jobs by the references from their payloads.
 *
 * References come from {@see JobPayloadParser::recipientReference()}: a model
 * class and ids for a notification, a ready address for an anonymous route.
 * Missing addresses are fetched with one query per class so the failed jobs
 * page does not turn into N queries.
 *
 * The current address is shown: if it was fixed after the failure, the list
 * shows the fixed one — the one the retry will use.
 */
final class ResolveJobRecipientsHandler
{
    /**
     * @param array<array-key, array{emails: string[], class: string|null, ids: array<int, int|string>}|null> $references
     *        Recipient references under arbitrary keys
     */
    public function __construct(private readonly array $references)
    {
    }

    /**
     * Resolve the references into addresses.
     *
     * @return array<array-key, string|null> Addresses under the same keys as the references;
     *                                       null — no recipient or the address was not found
     */
    public function handle(): array
    {
        if (! Config::get('jobs-monitor.recipients.enabled', true)) {
            return array_map(static fn (): ?string => null, $this->references);
        }

        $emailsByClass = $this->fetchEmails();
        $resolved      = [];

        foreach ($this->references as $key => $reference) {
            if ($reference === null) {
                $resolved[$key] = null;
                continue;
            }

            $emails = $reference['emails'];

            if ($reference['class'] !== null) {
                foreach ($reference['ids'] as $id) {
                    $email = $emailsByClass[$reference['class']][$id] ?? null;

                    if (is_string($email) && $email !== '') {
                        $emails[] = $email;
                    }
                }
            }

            $emails = array_values(array_unique($emails));

            $resolved[$key] = $emails === [] ? null : implode(', ', $emails);
        }

        return $resolved;
    }

    /**
     * Fetch the model addresses — one query per recipient class.
     *
     * @return array<string, array<int|string, string|null>> Address by model class and id
     */
    private function fetchEmails(): array
    {
        $idsByClass = [];

        foreach ($this->references as $reference) {
            if ($reference === null || $reference['class'] === null || $reference['ids'] === []) {
                continue;
            }

            $idsByClass[$reference['class']] = array_merge(
                $idsByClass[$reference['class']] ?? [],
                $reference['ids']
            );
        }

        $attribute = (string) Config::get('jobs-monitor.recipients.attribute', 'email');
        $emails    = [];

        foreach ($idsByClass as $class => $ids) {
            if (!is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                $model = new $class();

                // global scopes are dropped: the recipient has to be shown even
                // when their profile is already soft-deleted
                $emails[$class] = $model->newQuery()
                    ->withoutGlobalScopes()
                    ->whereKey(array_values(array_unique($ids)))
                    ->pluck($attribute, $model->getKeyName())
                    ->all();
            } catch (Throwable $exception) {
                // a recipient may have no address column — then the address is simply not shown
                Log::warning('[jobs-monitor] Failed to resolve failed job recipients', [
                    'model' => $class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $emails;
    }
}
