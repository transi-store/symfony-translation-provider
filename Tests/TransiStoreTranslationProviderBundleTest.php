<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use TransiStore\TranslationProvider\TransiStoreProviderFactory;
use TransiStore\TranslationProvider\TransiStoreTranslationProviderBundle;

class TransiStoreTranslationProviderBundleTest extends TestCase
{
    public function testBundleRegistersFactoryWithTranslationProviderFactoryTag(): void
    {
        $bundle = new TransiStoreTranslationProviderBundle();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.default_locale', 'en');

        
        $containerExtension = $bundle->getContainerExtension();

        assert($containerExtension instanceof ExtensionInterface);

        $container->registerExtension($containerExtension);
        $container->loadFromExtension($containerExtension->getAlias());
        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        $this->assertTrue($container->hasDefinition(TransiStoreProviderFactory::class));

        $definition = $container->getDefinition(TransiStoreProviderFactory::class);
        $this->assertArrayHasKey('translation.provider_factory', $definition->getTags());
    }
}
