<?php

namespace Sendbyte\Laravel\Mail;

use Illuminate\Support\Str;
use Sendbyte\Laravel\Sendbyte;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Lets SendByte be configured as a normal Laravel mailer, so existing
 * Mail::to(...)->send(...) / Mailable code keeps working unchanged:
 *
 *   // config/mail.php
 *   'mailers' => [
 *       'sendbyte' => ['transport' => 'sendbyte'],
 *   ],
 */
class SendbyteTransport extends AbstractTransport
{
    public function __construct(protected Sendbyte $sendbyte)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $result = $this->sendbyte->sendEmail($this->payloadFromEmail($email));

        if (isset($result['id'])) {
            $message->setMessageId($result['id']);
        }
    }

    protected function payloadFromEmail(Email $email): array
    {
        $from = $email->getFrom();

        $payload = [
            'from' => $this->formatAddress($from[0]),
            'to' => $this->formatAddresses($email->getTo()),
            'subject' => $email->getSubject() ?? '',
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = $this->formatAddresses($cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = $this->formatAddresses($bcc);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->formatAddress($replyTo[0]);
        }

        if ($html = $email->getHtmlBody()) {
            $payload['html'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['text'] = $text;
        }

        if ($attachments = $this->attachmentsFromEmail($email)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    protected function attachmentsFromEmail(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? Str::random(12);

            $attachments[] = [
                'filename' => $filename,
                'content' => base64_encode($attachment->getBody()),
                'content_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            ];
        }

        return $attachments;
    }

    protected function formatAddress(Address $address): string
    {
        return $address->getName() !== ''
            ? sprintf('%s <%s>', $address->getName(), $address->getAddress())
            : $address->getAddress();
    }

    /**
     * @param  Address[]  $addresses
     */
    protected function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(fn (Address $address) => $this->formatAddress($address), $addresses));
    }

    public function __toString(): string
    {
        return 'sendbyte';
    }
}
