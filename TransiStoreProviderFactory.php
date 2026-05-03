<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Exception\IncompleteDsnException;
use Symfony\Component\Translation\Exception\UnsupportedSchemeException;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\AbstractProviderFactory;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TransiStoreProviderFactory extends AbstractProviderFactory
{
    private const DEFAULT_HOST = 'transi-store.com';
    private const SCHEME = 'transi-store';

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        private string $defaultLocale,
        private LoaderInterface $loader,
        private GitBranchResolver $branchResolver = new GitBranchResolver(),
    ) {
    }

    public function create(Dsn $dsn): TransiStoreProvider
    {
        if (self::SCHEME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME, $this->getSupportedSchemes());
        }

        $endpoint = 'default' === $dsn->getHost() ? self::DEFAULT_HOST : $dsn->getHost();
        $endpoint .= $dsn->getPort() ? ':' . $dsn->getPort() : '';

        [$orgSlug, $projectSlug] = $this->parsePath($dsn);

        $client = $this->client->withOptions([
            'base_uri' => 'https://'.$endpoint.'/api/',
            'headers' => [
                'Authorization' => 'Bearer '.$this->getUser($dsn),
            ],
        ]);

        return new TransiStoreProvider(
            $client,
            $this->loader,
            $this->logger,
            $this->defaultLocale,
            $endpoint,
            $orgSlug,
            $projectSlug,
            $this->branchResolver->resolve(),
        );
    }

    protected function getSupportedSchemes(): array
    {
        return [self::SCHEME];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parsePath(Dsn $dsn): array
    {
        $path = trim((string) $dsn->getPath(), '/');
        $parts = explode('/', $path);

        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            throw new IncompleteDsnException('The "transi-store" DSN path must contain the organization and project slugs (e.g. "/ORG_SLUG/PROJECT_SLUG").', $dsn->getOriginalDsn());
        }

        return [$parts[0], $parts[1]];
    }
}
