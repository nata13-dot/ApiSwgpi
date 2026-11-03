<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envía a través de la micro API de correo (Mailer_Swgpi): la petición sólo encola, así que
 * Laravel no espera la conexión SMTP con Gmail. Se activa con MAIL_MAILER=swgpi.
 */
class SwgpiMailerTransport extends AbstractTransport
{
    public function __construct(private readonly string $url, private readonly string $key)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $addresses = fn (array $list) => array_map(fn (Address $address) => $address->getAddress(), $list);

        $payload = [
            'to' => $addresses($email->getTo()),
            'cc' => $addresses($email->getCc()),
            'bcc' => $addresses($email->getBcc()),
            'reply_to' => ($email->getReplyTo()[0] ?? null)?->getAddress(),
            'from_name' => ($email->getFrom()[0] ?? null)?->getName() ?: null,
            'subject' => (string) $email->getSubject(),
            // El registro de envíos guarda este tipo en lugar del asunto.
            'category' => $email->getHeaders()->get('X-Tag')?->getBodyAsString() ?? 'general',
            'html' => (string) ($email->getHtmlBody() ?? ''),
            'text' => (string) ($email->getTextBody() ?? ''),
            'attachments' => array_map(fn ($part) => [
                'filename' => $part->getFilename() ?? 'adjunto',
                'content' => base64_encode($part->getBody()),
                'content_type' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
            ], $email->getAttachments()),
        ];

        $response = Http::withToken($this->key)->acceptJson()->timeout(5)->connectTimeout(2)
            ->withHeaders(['Idempotency-Key' => $message->getMessageId()])
            ->post(rtrim($this->url, '/') . '/v1/emails', $payload);

        if (!$response->successful()) {
            throw new TransportException('El servicio de correo rechazó el envío: ' . ($response->json('error') ?? $response->status()));
        }
    }

    public function __toString(): string
    {
        return 'swgpi';
    }
}
