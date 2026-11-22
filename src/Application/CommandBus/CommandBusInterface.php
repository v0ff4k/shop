<?php

declare(strict_types=1);

namespace App\Application\CommandBus;

/**
 * Интерфейс шины команд.
 */
interface CommandBusInterface
{
    /**
     * Выполнить команду и вернуть результат
     *
     * @template T
     *
     * @return T|null
     */
    public function dispatch(object $command): mixed;
}
