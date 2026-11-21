<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\ReporterLite\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\AddressFactory;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Magento\Framework\Mail\MimeMessageInterfaceFactory;
use Magento\Framework\Mail\MimePartInterfaceFactory;
use Magento\Framework\Mail\TransportInterfaceFactory;

/**
 * Sends report e-mails through Magento's mail transport, so the store SMTP settings and the
 * "disable email communications" switch apply. Optional attachment.
 */
class Mailer
{
    /**
     * @param EmailMessageInterfaceFactory $messageFactory
     * @param MimeMessageInterfaceFactory $mimeMessageFactory
     * @param MimePartInterfaceFactory $mimePartFactory
     * @param AddressFactory $addressFactory
     * @param TransportInterfaceFactory $transportFactory
     * @param Config $config
     */
    public function __construct(
        private EmailMessageInterfaceFactory $messageFactory,
        private MimeMessageInterfaceFactory $mimeMessageFactory,
        private MimePartInterfaceFactory $mimePartFactory,
        private AddressFactory $addressFactory,
        private TransportInterfaceFactory $transportFactory,
        private Config $config
    ) {
    }

    /**
     * Sends one message
     *
     * @param string[] $to
     * @param string $subject
     * @param string $html
     * @param string $text
     * @param array{name:string,type:string,body:string}|null $attachment
     * @param string|null $emlFile write the message to this file instead of sending (tests)
     * @return void
     * @throws LocalizedException
     */
    public function send(
        array $to,
        string $subject,
        string $html,
        string $text,
        ?array $attachment = null,
        ?string $emlFile = null
    ): void {
        $from = $this->config->sender();
        if ($from['email'] === '') {
            throw new LocalizedException(__('The store has no sender e-mail address.'));
        }
        if ($emlFile === null && $this->config->mailDisabled()) {
            throw new LocalizedException(__(
                'E-mail is switched off in the store settings (Stores > Configuration > Advanced > System).'
            ));
        }
        $parts = [$this->mimePartFactory->create(['content' => $html])];
        $symfonyMime = class_exists(\Symfony\Component\Mime\Part\TextPart::class);
        if ($attachment && !$symfonyMime) {
            // Magento 2.4.4-2.4.7 (laminas-mime): the attachment is a second MIME part
            $parts[] = $this->mimePartFactory->create([
                'content' => $attachment['body'],
                'type' => (string)preg_replace('/;.*$/', '', $attachment['type']),
                'fileName' => $attachment['name'],
                'disposition' => 'attachment',
                'encoding' => 'base64',
            ]);
        }
        $message = $this->messageFactory->create([
            'body' => $this->mimeMessageFactory->create(['parts' => $parts]),
            'to' => array_map(function ($email) {
                return $this->addressFactory->create(['email' => $email, 'name' => null]);
            }, $to),
            'from' => [$this->addressFactory->create(['email' => $from['email'], 'name' => $from['name'] ?: null])],
            'subject' => $subject,
        ]);
        if (method_exists($message, 'getSymfonyMessage') && $symfonyMime) {
            $body = new \Symfony\Component\Mime\Part\Multipart\AlternativePart(
                new \Symfony\Component\Mime\Part\TextPart($text, 'utf-8', 'plain', 'quoted-printable'),
                new \Symfony\Component\Mime\Part\TextPart($html, 'utf-8', 'html', 'quoted-printable')
            );
            if ($attachment) {
                $body = new \Symfony\Component\Mime\Part\Multipart\MixedPart($body, new \Symfony\Component\Mime\Part\DataPart(
                    $attachment['body'],
                    $attachment['name'],
                    (string)preg_replace('/;.*$/', '', $attachment['type'])
                ));
            }
            $symfony = $message->getSymfonyMessage();
            $symfony->setBody($body);
            $symfony->getHeaders()->addTextHeader('X-Mailer', 'MageBooster Reporter Lite');
            $symfony->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
            if ($emlFile !== null) {
                $this->writeEml($emlFile, $symfony->toString());
                return;
            }
        } elseif ($emlFile !== null) {
            $this->writeEml($emlFile, 'Subject: ' . $subject . "\n\n" . $text);
            return;
        }
        $this->transportFactory->create(['message' => $message])->sendMessage();
    }

    /**
     * Writes a test message to disk
     *
     * @param string $file
     * @param string $raw
     * @return void
     */
    private function writeEml(string $file, string $raw): void
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- CLI test output to a path the admin chose
        file_put_contents($file, $raw);
    }
}
