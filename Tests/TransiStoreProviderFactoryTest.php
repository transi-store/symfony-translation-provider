<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider\Tests;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\AbstractProviderFactoryTestCase;
use Symfony\Component\Translation\Test\IncompleteDsnTestTrait;
use TransiStore\TranslationProvider\TransiStoreProviderFactory;

class TransiStoreProviderFactoryTest extends AbstractProviderFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public static function supportsProvider(): iterable
    {
        yield [true, 'transi-store://API_KEY@default/ORG/PROJECT'];
        yield [false, 'somethingElse://API_KEY@default/ORG/PROJECT'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_KEY@default/ORG/PROJECT'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'transi-store://transi-store.com/ORG/PROJECT',
            'transi-store://API_KEY@default/ORG/PROJECT',
        ];

        yield [
            'transi-store://example.com/ORG/PROJECT',
            'transi-store://API_KEY@example.com/ORG/PROJECT',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['transi-store://default'];
        yield ['transi-store://API_KEY@default'];
        yield ['transi-store://API_KEY@default/ORG'];
    }

    public function testBaseUri(): void
    {
        $response = new JsonMockResponse(['files' => [], 'languages' => []]);
        $httpClient = new MockHttpClient([$response]);
        $factory = new TransiStoreProviderFactory($httpClient, new NullLogger(), 'en', new ArrayLoader());
        $provider = $factory->create(new Dsn('transi-store://API_KEY@default/ORG/PROJECT'));

        $provider->read(['messages'], ['en']);

        $this->assertMatchesRegularExpression(
            '#https://transi-store\.com/api/orgs/ORG/projects/PROJECT/?#',
            $response->getRequestUrl(),
        );
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new TransiStoreProviderFactory(new MockHttpClient(), new NullLogger(), 'en', new ArrayLoader());
    }
}
