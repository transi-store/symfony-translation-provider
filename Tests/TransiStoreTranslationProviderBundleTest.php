<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TransiStore\TranslationProvider\TransiStoreProviderFactory;
use TransiStore\TranslationProvider\TransiStoreTranslationProviderBundle;

class TransiStoreTranslationProviderBundleTest extends TestCase
{
    public function testBundleRegistersFactoryWithTranslationProviderFactoryTag(): void
    {
        $bundle = new TransiStoreTranslationProviderBundle();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.default_locale', 'en');
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension($bundle->getContainerExtension()->getAlias());
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertTrue($container->hasDefinition(TransiStoreProviderFactory::class));

        $definition = $container->getDefinition(TransiStoreProviderFactory::class);
        $this->assertArrayHasKey('translation.provider_factory', $definition->getTags());
    }
}
