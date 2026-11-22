<?php

declare(strict_types=1);

namespace App\Infrastructure\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * CompilerPass для регистрации обработчиков событий Prometheus.
 *
 * Пример использования CompilerPass для автоматической регистрации
 * обработчиков и сборки registry
 */
final class PrometheusMetricsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Если нет сервиса registry, создаем его
        if (!$container->hasDefinition('app.prometheus.metrics_registry')) {
            return;
        }

        $registryDefinition = $container->getDefinition('app.prometheus.metrics_registry');

        // Находим все сервисы с тегом 'prometheus.metric'
        $taggedServices = $container->findTaggedServiceIds('prometheus.metric');

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $tag) {
                $metricName = $tag['name'] ?? null;
                if ($metricName === null) {
                    continue;
                }

                // Добавляем обработчик в registry
                $registryDefinition->addMethodCall(
                    'addMetric',
                    [$metricName, new Reference($id)]
                );
            }
        }

        // Пример для обработчиков команд
        $this->processCommandHandlers($container);
    }

    /**
     * Обрабатывает обработчики команд для Messenger.
     */
    private function processCommandHandlers(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds('app.command_handler');

        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->setPublic(false);
        }
    }
}
