<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class TransiStoreTranslationProviderBundle extends AbstractBundle
{
    /**
     * 
     * @param array<mixed> $config 
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(GitBranchResolver::class)
                ->autowire(false)
                ->autoconfigure(false)

            ->set(TransiStoreProviderFactory::class)
                ->args([
                    service('http_client'),
                    service('logger'),
                    param('kernel.default_locale'),
                    service('translation.loader.xliff'),
                    service(GitBranchResolver::class),
                ])
                ->tag('translation.provider_factory');
    }
}
