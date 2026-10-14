<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Unit;

use MikhailMelnikov\JobsMonitor\Support\JobPayloadParser;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;

/**
 * Тесты разбора payload'а задачи очереди.
 */
class JobPayloadParserTest extends TestCase
{
    /**
     * Название задачи берётся из payload'а очереди.
     */
    public function testReadsJobNameFromPayload(): void
    {
        $payload = json_encode(['displayName' => 'App\Jobs\SomeJob', 'job' => 'Illuminate\Queue\CallQueuedHandler@call']);

        self::assertSame('App\Jobs\SomeJob', (new JobPayloadParser((string) $payload))->name());
    }

    /**
     * Если названия в payload'е нет, задача помечается как неизвестная, а не пустой строкой.
     */
    public function testFallsBackToPlaceholderWhenNameIsMissing(): void
    {
        self::assertSame('Неизвестная задача', (new JobPayloadParser('{}'))->name());
        self::assertSame('Неизвестная задача', (new JobPayloadParser('битый payload'))->name());
    }

    /**
     * Сериализованный объект job'а вырезается: администратору он ничего не говорит.
     */
    public function testPrettyPayloadDropsSerializedCommand(): void
    {
        $payload = json_encode([
            'displayName' => 'App\Jobs\SomeJob',
            'data' => ['commandName' => 'App\Jobs\SomeJob', 'command' => 'O:16:"App\Jobs\SomeJob":0:{}'],
        ]);

        $pretty = (new JobPayloadParser((string) $payload))->pretty();

        self::assertStringContainsString('commandName', $pretty);
        self::assertStringNotContainsString('O:16:', $pretty);
    }

    /**
     * Payload, который не является JSON, показывается в исходном виде.
     */
    public function testKeepsRawPayloadWhenItIsNotJson(): void
    {
        self::assertSame('битый payload', (new JobPayloadParser('битый payload'))->pretty());
    }

    /**
     * Получатель-модель приходит в payload'е ссылкой: SerializesModels подменяет
     * её на ModelIdentifier, поэтому из payload'а достаются класс и id, а не адрес.
     */
    public function testReadsModelRecipientReference(): void
    {
        $command = 'O:48:"Illuminate\Notifications\SendQueuedNotifications":3:'
            . '{s:11:"notifiables";O:45:"Illuminate\Contracts\Database\ModelIdentifier":5:'
            . '{s:5:"class";s:29:"App\Modules\Users\Models\User";s:2:"id";a:1:{i:0;i:17;}'
            . 's:9:"relations";a:0:{}s:10:"connection";s:5:"pgsql";s:15:"collectionClass";N;}'
            . 's:12:"notification";O:8:"StdClass":0:{}s:8:"channels";a:1:{i:0;s:4:"mail";}}';

        self::assertSame(
            ['emails' => [], 'class' => 'App\Modules\Users\Models\User', 'ids' => [17]],
            (new JobPayloadParser($this->payloadWithCommand($command)))->recipientReference()
        );
    }

    /**
     * Отправка мимо модели (Notification::route) несёт адрес прямо в payload'е,
     * причём получатель завёрнут в коллекцию — её надо развернуть.
     */
    public function testReadsAnonymousRecipientEmail(): void
    {
        $command = 'O:48:"Illuminate\Notifications\SendQueuedNotifications":3:'
            . '{s:11:"notifiables";O:29:"Illuminate\Support\Collection":2:'
            . '{s:8:"' . "\0*\0" . 'items";a:1:{i:0;O:44:"Illuminate\Notifications\AnonymousNotifiable":1:'
            . '{s:6:"routes";a:1:{s:4:"mail";s:16:"anon@example.com";}}}'
            . 's:28:"' . "\0*\0" . 'escapeWhenCastingToString";b:0;}'
            . 's:12:"notification";O:8:"StdClass":0:{}s:8:"channels";a:1:{i:0;s:4:"mail";}}';

        self::assertSame(
            ['emails' => ['anon@example.com'], 'class' => null, 'ids' => []],
            (new JobPayloadParser($this->payloadWithCommand($command)))->recipientReference()
        );
    }

    /**
     * У задачи, не связанной с отправкой, получателя нет — и разбор об этом не спотыкается.
     */
    public function testReturnsNoRecipientForNonNotificationJob(): void
    {
        self::assertNull(
            (new JobPayloadParser($this->payloadWithCommand('O:8:"stdClass":0:{}')))->recipientReference()
        );
        self::assertNull((new JobPayloadParser('битый payload'))->recipientReference());
        self::assertNull((new JobPayloadParser('{}'))->recipientReference());
    }

    /**
     * Разбор не должен восстанавливать объекты payload'а: класс, которого в проекте нет,
     * не ломает страницу и не выполняет чужой код.
     */
    public function testSurvivesUnknownClassesInPayload(): void
    {
        $command = 'O:22:"Vendor\Gone\MissingJob":1:{s:11:"notifiables";O:13:"Vendor\Absent":0:{}}';

        self::assertNull((new JobPayloadParser($this->payloadWithCommand($command)))->recipientReference());
    }

    /**
     * Payload задачи очереди с заданным сериализованным job'ом.
     *
     * @param string $command Сериализованный объект job'а
     */
    private function payloadWithCommand(string $command): string
    {
        return (string) json_encode([
            'displayName' => 'App\Jobs\SomeJob',
            'data' => ['commandName' => 'App\Jobs\SomeJob', 'command' => $command],
        ]);
    }
}
