<?php

declare(strict_types=1);

namespace App\PushMessage;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lichte client voor de Expo Push API. Geen aparte SDK nodig — één HTTP POST.
 * Zie ARCHITECTURE.md sectie 16a.
 */
final readonly class ExpoPushClient
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ExpoPushDeliveryException als de aanroep faalt of Expo een foutstatus teruggeeft
     */
    public function send(string $expoPushToken, string $title, string $body): void
    {
        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'json' => [
                    'to' => $expoPushToken,
                    'title' => $title,
                    'body' => $body,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 300) {
                throw new ExpoPushDeliveryException(\sprintf(
                    'Expo Push API antwoordde met statuscode %d.',
                    $statusCode,
                ));
            }

            $data = $response->toArray(false);
            $ticket = \is_array($data['data'] ?? null) ? $data['data'] : [];

            if ('error' === ($ticket['status'] ?? null)) {
                $message = $ticket['message'] ?? null;

                throw new ExpoPushDeliveryException(
                    \is_scalar($message) ? (string) $message : 'Onbekende fout van Expo Push API.',
                );
            }
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Expo Push API was niet bereikbaar.', ['exception' => $exception]);

            throw new ExpoPushDeliveryException('Expo Push API was niet bereikbaar.', previous: $exception);
        }
    }
}
