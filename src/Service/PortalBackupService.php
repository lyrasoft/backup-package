<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Service;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Windwalker\Http\Helper\ResponseHelper;
use Windwalker\Http\HttpClient;
use Windwalker\Utilities\Str;

class PortalBackupService
{
    public function register(string $accessToken, array $data): array
    {
        $http = $this->getHttpClient();
        $res = $http->post(
            $this->getPortalApiEndpoint('backup/register'),
            $data,
            [
                'headers' => [
                    'Authorization' => "Bearer $accessToken"
                ]
            ]
        );

        $data = $this->extractResponse($res);

        return $data['data'] ?? [];
    }

    public function auth(OutputInterface $output): string
    {
        $http = $this->getHttpClient();

        $res = $http->get(
            $this->getPortalApiEndpoint('device/auth'),
            [
                'params' => [
                    'scope' => 'backup'
                ]
            ],
        );

        $data = $this->extractResponse($res);

        $result = $data['data'] ?? [];

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
            $this->getPortalApiEndpoint('device/access-token'),
            [
                'device_token' => $dt
            ]
        );

        try {
            $data = $this->extractResponse($res);
        } catch (\RuntimeException $e) {
            // No actions
        }

        return $data['data']['access_token'] ?? null;
    }

    public function getPortalDeviceLoginUrl(): string
    {
        return $this->getPortalUrl('device/login');
    }

    public function getHttpClient(): HttpClient
    {
        return new HttpClient();
    }

    public function extractResponse(ResponseInterface $res): array
    {
        $body = (string) $res->getBody();
        $body = (array) json_decode($body, true);

        if (!ResponseHelper::isSuccess($res->getStatusCode())) {
            throw new \RuntimeException(
                $body['message'] ?? $res->getReasonPhrase(),
                $res->getStatusCode()
            );
        }

        return $body;
    }

    /**
     * @param  string  $path
     *
     * @return  string
     */
    protected function getPortalApiEndpoint(string $path = ''): string
    {
        $host = Str::ensureRight(
            env('BACKUP_SERVER_APT_ENDPOINT')
                ?: 'https://portal.simular.co/api/',
            '/'
        );

        if ($path) {
            $host .= $path;
        }

        return $host;
    }

    /**
     * @return  string|null
     */
    protected function getPortalUrl(string $path = ''): ?string
    {
        $url = Str::ensureRight(env('BACKUP_SERVER_URL') ?: 'https://portal.simular.co/', '/');

        if ($path) {
            $url .= $path;
        }

        return $url;
    }
}
