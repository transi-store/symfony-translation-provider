<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Exception\ProviderException;
use Symfony\Component\Translation\Exception\RuntimeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transi-Store provider for Symfony Translation.
 *
 * In Transi-Store:
 *  - Files refer to Symfony's translation domains (one file per domain);
 *  - Languages refer to Symfony's locales;
 *  - Translations refer to Symfony's translated messages.
 */
final class TransiStoreProvider implements ProviderInterface
{
    private const EXCHANGE_FORMAT = 'xliff';

    /**
     * @var array<string, int>|null map of domain => file id
     */
    private ?array $filesByDomain = null;

    public function __construct(
        private HttpClientInterface $client,
        private LoaderInterface $loader,
        private LoggerInterface $logger,
        private string $defaultLocale,
        private string $endpoint,
        private string $orgSlug,
        private string $projectSlug,
        private XliffFileDumper $xliffFileDumper = new XliffFileDumper(),
    ) {
    }

    public function __toString(): string
    {
        return \sprintf('transi-store://%s/%s/%s', $this->endpoint, $this->orgSlug, $this->projectSlug);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();

            foreach ($catalogue->getDomains() as $domain) {
                assert(\is_string($domain));

                $messages = $catalogue->all($domain);

                if (!$messages) {
                    continue;
                }

                $fileId = $this->getFileForDomain($domain);

                if (!$fileId) {
                    $this->logger->warning(\sprintf('Domain "%s" has no matching file in Transi-Store project "%s/%s", skipping.', $domain, $this->orgSlug, $this->projectSlug));
                    continue;
                }

                assert($catalogue instanceof MessageCatalogue);

                $content = $this->xliffFileDumper->formatCatalogue(
                    $catalogue,
                    $domain,
                    [
                        'default_locale' => $this->defaultLocale,
                        'xliff_version' => '2.0',
                    ]
                );
                $filename = \sprintf('%s.%s.xlf', $domain, $locale);

                $formData = new FormDataPart([
                    'locale' => $locale,
                    'format' => self::EXCHANGE_FORMAT,
                    'strategy' => 'overwrite',
                    'file' => new DataPart($content, $filename, 'application/xml'),
                ]);

                $response = $this->client->request(
                    'POST',
                    \sprintf(
                        'orgs/%s/projects/%s/files/%d/translations',
                        rawurlencode($this->orgSlug),
                        rawurlencode($this->projectSlug),
                        $fileId
                    ),
                    [
                    'body' => $formData->bodyToIterable(),
                    'headers' => $formData->getPreparedHeaders()->toArray(),
                ]);

                if (200 !== $statusCode = $response->getStatusCode()) {
                    $this->logger->error(\sprintf('Unable to upload translations for domain "%s" and locale "%s" to Transi-Store: "%s".', $domain, $locale, $response->getContent(false)));

                    if (500 <= $statusCode) {
                        throw new ProviderException(\sprintf('Unable to upload translations for domain "%s" and locale "%s" to Transi-Store.', $domain, $locale), $response);
                    }
                }
            }
        }
    }

    /**
     * 
     * @param array<string> $domains 
     * @param array<string> $locales 
     */
    public function read(array $domains, array $locales): TranslatorBag
    {
        $translatorBag = new TranslatorBag();

        foreach ($locales as $locale) {
            foreach ($domains as $domain) {
                $fileId = $this->getFileForDomain($domain);

                if (!$fileId) {
                    $this->logger->warning(\sprintf('Domain "%s" has no matching file in Transi-Store project "%s/%s", skipping.', $domain, $this->orgSlug, $this->projectSlug));
                    continue;
                }

                $url = sprintf(
                    'orgs/%s/projects/%s/files/%d/translations',
                    rawurlencode($this->orgSlug),
                    rawurlencode($this->projectSlug),
                    $fileId
                );

                $response = $this->client->request('GET', $url, [
                    'query' => [
                        'locale' => $locale,
                        'format' => self::EXCHANGE_FORMAT,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if (404 === $statusCode) {
                    $this->logger->warning(\sprintf('Translations for locale "%s" and domain "%s" do not exist in Transi-Store.', $locale, $domain));
                    continue;
                }

                if (200 !== $statusCode) {
                    throw new ProviderException(\sprintf('Unable to read translations for locale "%s" and domain "%s" from Transi-Store: "%s".', $locale, $domain, $response->getContent(false)), $response);
                }

                $content = $response->getContent(false);

                $catalogue = $this->loader->load(
                    $content,
                    $locale,
                    $domain,
                );

                $translatorBag->addCatalogue($catalogue);
            }
        }

        return $translatorBag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        throw new RuntimeException('Deleting translations is not supported by the Transi-Store provider.');
    }

    private function getFileForDomain(string $domain): ?int
    {
        $filesByDomain = $this->getFilesByDomain();

        if (isset($filesByDomain[$domain])) {
            return $filesByDomain[$domain];
        }

        // no domain found, try with the "+intl-icu" suffix convention
        if (isset($filesByDomain[$domain.'+intl-icu'])) {
            return $filesByDomain[$domain.'+intl-icu'];
        }

        return null;
    }

    /**
     * @return array<string, int> domain => file id
     */
    private function getFilesByDomain(): array
    {
        if (null !== $this->filesByDomain) {
            return $this->filesByDomain;
        }

        $response = $this->client->request(
            'GET',
            sprintf('orgs/%s/projects/%s', rawurlencode($this->orgSlug), rawurlencode($this->projectSlug))
        );

        if (200 !== $response->getStatusCode()) {
            throw new ProviderException(
                \sprintf('Unable to fetch project metadata from Transi-Store: "%s".', $response->getContent(false)),
                $response
            );
        }

        $data = $response->toArray(false);

        $map = [];

        if (!isset($data['files'])) {
            return $map;
        }

        if (!\is_array($data['files'])) {
            throw new ProviderException(
                'Invalid response from Transi-Store: "files" key is not an array.',
                $response
            );
        }

        foreach ($data['files']  as $file) {
            if (
                !\is_array($file)
                || !isset($file['id'], $file['filePath'])
                || !is_string($file['filePath'])
                || !is_int($file['id'])
            ) {
                throw new ProviderException(
                    'Invalid response from Transi-Store: each file should be an array with "id" and "filePath" keys.',
                    $response
                );
            }

            $domain = $this->domainFromFilePath($file['filePath']);

            if (null === $domain) {
                $this->logger->warning(\sprintf('Unable to derive a Symfony domain from Transi-Store file path "%s", skipping.', $file['filePath']));
                continue;
            }

            $map[$domain] = $file['id'];
        }

        return $this->filesByDomain = $map;
    }

    private function domainFromFilePath(string $filePath): ?string
    {
        $basename = basename($filePath);

        if (preg_match('/^(.+?)\.<lang>\.[^.]+$/', $basename, $m)) {
            return $m[1];
        }

        return null;
    }
}
