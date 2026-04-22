<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Exception\RuntimeException;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TransiStore\TranslationProvider\TransiStoreProvider;

class TransiStoreProviderTest extends TestCase
{
    public function testToString(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        $this->assertSame('transi-store://transi-store.com/ORG/PROJECT', (string) $provider);
    }

    public function testDeleteThrows(): void
    {
        $provider = $this->createProvider(new MockHttpClient());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deleting translations is not supported');

        $provider->delete(new TranslatorBag());
    }

    public function testReadMapsDomainsToFileIds(): void
    {
        $xliffEn = <<<XLIFF
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
  <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
    <body>
      <trans-unit id="1"><source>hello</source><target>Hello</target></trans-unit>
    </body>
  </file>
</xliff>
XLIFF;

        $responses = [
            function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringEndsWith('/api/orgs/ORG/projects/PROJECT', $url);

                return new JsonMockResponse([
                    'files' => [
                        ['id' => 42, 'format' => 'yaml', 'filePath' => 'translations/messages.<lang>.yaml'],
                        ['id' => 43, 'format' => 'yaml', 'filePath' => 'translations/validators.<lang>.yaml'],
                    ],
                    'languages' => [
                        ['locale' => 'en', 'isDefault' => true],
                    ],
                ]);
            },
            function (string $method, string $url) use ($xliffEn): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringContainsString('/files/42/translations', $url);
                $this->assertStringContainsString('locale=en', $url);
                $this->assertStringContainsString('format=xliff', $url);

                return new MockResponse($xliffEn, ['http_code' => 200]);
            },
        ];

        $httpClient = new MockHttpClient($responses);
        $provider = $this->createProvider($httpClient, new XliffFileLoader());

        $bag = $provider->read(['messages'], ['en']);
        $catalogue = $bag->getCatalogue('en');

        $this->assertSame('Hello', $catalogue->get('hello', 'messages'));
    }
    

    public function testReadMapsDomainsToFileIdsXliff2(): void
    {
        $xliffEn = <<<XLIFF
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="fr">
  <file id="Website">
    <unit id="hello.world">
      <segment>
        <source>hello.world</source>
        <target>Bonjour le monde</target>
      </segment>
    </unit>
  </file>
</xliff>
XLIFF;

        $responses = [
            function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringEndsWith('/api/orgs/ORG/projects/PROJECT', $url);

                return new JsonMockResponse([
                    'files' => [
                        ['id' => 42, 'format' => 'yaml', 'filePath' => 'translations/messages.<lang>.yaml'],
                        ['id' => 43, 'format' => 'yaml', 'filePath' => 'translations/validators.<lang>.yaml'],
                        // ['id' => 44, 'format' => 'xliff', 'filePath' => 'translations/validators+intl-icu.<lang>.xlf'],
                    ],
                    'languages' => [
                        ['locale' => 'en', 'isDefault' => true],
                    ],
                ]);
            },
            function (string $method, string $url) use ($xliffEn): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringContainsString('/files/42/translations', $url);
                $this->assertStringContainsString('locale=en', $url);
                $this->assertStringContainsString('format=xliff', $url);

                return new MockResponse($xliffEn, ['http_code' => 200]);
            },
        ];

        $httpClient = new MockHttpClient($responses);
        $provider = $this->createProvider($httpClient, new XliffFileLoader());

        $bag = $provider->read(['messages'], ['en']);
        $catalogue = $bag->getCatalogue('en');

        $this->assertSame('Bonjour le monde', $catalogue->get('hello.world', 'messages'));
    }
    

    public function testReadFileIntlIcu(): void
    {
        $xliffEn = <<<XLIFF
<?xml version="1.0" encoding="UTF-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="fr">
  <file id="Website">
    <unit id="hello.world">
      <segment>
        <source>hello.world</source>
        <target>Bonjour le monde</target>
      </segment>
    </unit>
  </file>
</xliff>
XLIFF;

        $responses = [
            function (string $method, string $url): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringEndsWith('/api/orgs/ORG/projects/PROJECT', $url);

                return new JsonMockResponse([
                    'files' => [
                        ['id' => 43, 'format' => 'yaml', 'filePath' => 'translations/validators.<lang>.yaml'],
                        ['id' => 44, 'format' => 'yaml', 'filePath' => 'translations/messages+intl-icu.<lang>.yaml'],
                    ],
                    'languages' => [
                        ['locale' => 'en', 'isDefault' => true],
                    ],
                ]);
            },
            function (string $method, string $url) use ($xliffEn): ResponseInterface {
                $this->assertSame('GET', $method);
                $this->assertStringContainsString('/files/44/translations', $url);
                $this->assertStringContainsString('locale=en', $url);
                $this->assertStringContainsString('format=xliff', $url);

                return new MockResponse($xliffEn, ['http_code' => 200]);
            },
        ];

        $httpClient = new MockHttpClient($responses);
        $provider = $this->createProvider($httpClient, new XliffFileLoader());

        $bag = $provider->read(['messages'], ['en']);
        $catalogue = $bag->getCatalogue('en');

        $this->assertSame('Bonjour le monde', $catalogue->get('hello.world', 'messages'));
    }

    public function testReadSkipsUnknownDomains(): void
    {
        $httpClient = new MockHttpClient([
            new JsonMockResponse([
                'files' => [
                    ['id' => 42, 'format' => 'yaml', 'filePath' => 'translations/messages.<lang>.yaml'],
                ],
                'languages' => [],
            ]),
        ]);

        $provider = $this->createProvider($httpClient);

        $bag = $provider->read(['unknown'], ['en']);

        $this->assertSame([], $bag->getCatalogues());
    }

    public function testWriteUploadsEachDomainLocalePair(): void
    {
        $seen = [];
        $responses = [
            new JsonMockResponse([
                'files' => [
                    ['id' => 42, 'format' => 'yaml', 'filePath' => 'translations/messages.<lang>.yaml'],
                ],
                'languages' => [],
            ]),
            function (string $method, string $url, array $options) use (&$seen): ResponseInterface {
                $seen[] = ['method' => $method, 'url' => $url];

                return new JsonMockResponse(['success' => true, 'stats' => []]);
            },
            function (string $method, string $url, array $options) use (&$seen): ResponseInterface {
                $seen[] = ['method' => $method, 'url' => $url];

                return new JsonMockResponse(['success' => true, 'stats' => []]);
            },
        ];

        $httpClient = new MockHttpClient($responses);
        $provider = $this->createProvider($httpClient);

        $bag = new TranslatorBag();
        $enCatalogue = new MessageCatalogue('en');
        $enCatalogue->add(['hello' => 'Hello'], 'messages');
        $frCatalogue = new MessageCatalogue('fr');
        $frCatalogue->add(['hello' => 'Bonjour'], 'messages');
        $bag->addCatalogue($enCatalogue);
        $bag->addCatalogue($frCatalogue);

        $provider->write($bag);

        $this->assertCount(2, $seen);
        foreach ($seen as $entry) {
            $this->assertSame('POST', $entry['method']);
            $this->assertStringContainsString('/files/42/translations', $entry['url']);
        }
    }

    private function createProvider(MockHttpClient $httpClient, ?XliffFileLoader $loader = null): TransiStoreProvider
    {
        $httpClient = $httpClient->withOptions([
            'base_uri' => 'https://transi-store.com/api/',
            'headers' => ['Authorization' => 'Bearer API_KEY'],
        ]);

        return new TransiStoreProvider(
            $httpClient,
            $loader ?? new XliffFileLoader(),
            new NullLogger(),
            'en',
            'transi-store.com',
            'ORG',
            'PROJECT',
        );
    }
}
