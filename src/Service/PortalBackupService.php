<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Windwalker\Http\HttpClient;
use Windwalker\Utilities\Str;

class PortalBackupService
{
    public function register(string $accessToken, array $data): array
    {
        $http = $this->getHttpClient();
        $res = $http->post(
            'backup/register',
            $data,
            [
                'headers' => [
                    'Authorization' => "Bearer $accessToken"
                ]
            ]
        );

        if (!$res->isSuccess()) {
            throw new \RuntimeException($res->getReasonPhrase(), $res->getStatusCode());
        }

        return $res->toArray()['data'] ?? [];
    }

    public function auth(OutputInterface $output): string
    {
        $http = $this->getHttpClient();

        $res = $http->get(
            'device/auth',
            [
                'params' => [
                    'scope' => 'backup'
                ]
            ],
        );

        if (!$res->isSuccess()) {
            throw new \RuntimeException($res->getReasonPhrase(), $res->getStatusCode());
        }

        $result = $res->toArray()['data'] ?? [];

        $code = $result['code'];
        $dt = $result['device_token'];

        $url = $this->getPortalDeviceLoginUrl();

        $output->writeln("Please fill <info>$code</info> to Portal.");
        $output->writeln("Open <info>{$url}</info> from your local browser.");

        $total = 0;
        $wait = 2;

        while (!$accessToken = $this->getPortalAccessToken($dt)) {
            if ($total > 150) {
                throw new \RuntimeException('Please enter code in 150 seconds.');
            }

            $total += $wait;
            sleep($wait);
        }

        return $accessToken;
    }

    public function getPortalAccessToken(string $dt): ?string
    {
        $http = $this->getHttpClient();
        $res = $http->post(
            'device/access-token',
            [
                'device_token' => $dt
            ]
        );

        if (!$res->isSuccess()) {
            return null;
        }

        return $res->toArray()['data']['access_token'] ?? null;
    }

    public function getPortalDeviceLoginUrl(): string
    {
        return rtrim($this->getPortalUrl(), '/') . '/device/login';
    }

    public function getHttpClient(): HttpClient
    {
        return (new HttpClient())
            ->withBaseUri($this->getPortalApiHost());
    }

    /**
     * @return  string
     */
    protected function getPortalApiHost(): string
    {
        return Str::ensureRight(
            env('BACKUP_SERVER_APT_ENDPOINT')
                ?: 'https://portal.simulr.co/api/',
            '/'
        );
    }

    /**
     * @return  string|null
     */
    protected function getPortalUrl(): ?string
    {
        return env('BACKUP_SERVER_URL') ?: 'https://portal.simulr.co/';
    }
}
