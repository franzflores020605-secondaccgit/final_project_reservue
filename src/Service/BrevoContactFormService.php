<?php

namespace App\Service;

use App\Dto\ContactFormSubmission;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends contact form data to Brevo: transactional email to your inbox + optional contact list.
 * If the REST API is not configured, falls back to Symfony Mailer (e.g. Brevo SMTP via MAILER_DSN)
 * when sender and notify addresses are set.
 *
 * @see https://developers.brevo.com/reference/sendtransacemail
 * @see https://developers.brevo.com/reference/createcontact
 */
final class BrevoContactFormService
{
    private const API_BASE = 'https://api.brevo.com/v3';

    /** Brevo xkeysib-* keys are much longer; avoid calling the API with placeholder values. */
    private const MIN_API_KEY_LENGTH = 20;

    private readonly string $apiKey;

    private readonly string $senderEmail;

    private readonly string $senderName;

    private readonly string $notifyEmail;

    private readonly ?int $contactListId;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        string $apiKey,
        string $senderEmail,
        string $senderName,
        string $notifyEmail,
        string $contactListIdEnv = '',
        private readonly string $mailerDsn = '',
        private readonly bool $kernelDebug = false,
    ) {
        $this->apiKey = trim($apiKey);
        $this->senderEmail = trim($senderEmail);
        $this->senderName = trim($senderName);
        $this->notifyEmail = trim($notifyEmail);
        $this->contactListId = self::parseOptionalListId($contactListIdEnv);
    }

    private static function parseOptionalListId(string $raw): ?int
    {
        $t = trim($raw);
        if ($t === '' || !ctype_digit($t)) {
            return null;
        }

        return (int) $t;
    }

    public function isConfigured(): bool
    {
        return self::isConfiguredValue($this->apiKey)
            && \strlen($this->apiKey) >= self::MIN_API_KEY_LENGTH
            && self::isConfiguredValue($this->senderEmail)
            && self::isValidEmail($this->senderEmail)
            && self::isConfiguredValue($this->notifyEmail)
            && self::isValidEmail($this->notifyEmail);
    }

    /**
     * Non-empty after trim, and not the literal string "null".
     */
    private static function isConfiguredValue(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== '' && strtolower($trimmed) !== 'null';
    }

    private static function isValidEmail(string $email): bool
    {
        return false !== filter_var($email, \FILTER_VALIDATE_EMAIL);
    }

    private function canSendViaSymfonyMailer(): bool
    {
        if ($this->mailerDsn === '' || str_starts_with($this->mailerDsn, 'null://')) {
            return false;
        }

        return self::isConfiguredValue($this->senderEmail)
            && self::isValidEmail($this->senderEmail)
            && self::isConfiguredValue($this->notifyEmail)
            && self::isValidEmail($this->notifyEmail);
    }

    public function submit(ContactFormSubmission $submission): void
    {
        if ($this->isConfigured()) {
            $this->sendTransactionalNotification($submission);
            if ($this->contactListId !== null && $this->contactListId > 0) {
                $this->upsertListContact($submission);
            }

            return;
        }

        if ($this->canSendViaSymfonyMailer()) {
            $this->sendViaSymfonyMailer($submission);

            return;
        }

        throw new ServiceUnavailableHttpException(null, 'Contact email is not configured. Set BREVO_API_KEY plus BREVO_SENDER_EMAIL and BREVO_NOTIFY_EMAIL, or leave the API key empty and set both emails to send via MAILER_DSN (SMTP).');
    }

    private function buildNotificationHtml(ContactFormSubmission $submission): string
    {
        $safeName = htmlspecialchars($submission->name, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $safeEmail = htmlspecialchars($submission->email, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($submission->message, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return <<<HTML
            <p><strong>Name:</strong> {$safeName}</p>
            <p><strong>Email:</strong> <a href="mailto:{$safeEmail}">{$safeEmail}</a></p>
            <p><strong>Message:</strong></p>
            <p>{$safeMessage}</p>
        HTML;
    }

    private function sendViaSymfonyMailer(ContactFormSubmission $submission): void
    {
        $inner = $this->buildNotificationHtml($submission);
        $email = (new Email())
            ->from(new Address($this->senderEmail, $this->senderName !== '' ? $this->senderName : 'ReserVue'))
            ->to($this->notifyEmail)
            ->replyTo(new Address($submission->email, $submission->name))
            ->subject('ReserVue contact: '.$submission->name)
            ->html('<html><body>'.$inner.'</body></html>');

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Contact form Symfony Mailer send failed', [
                'exception' => $e,
            ]);
            throw new \RuntimeException('Could not send your message. Please try again later.', 0, $e);
        }
    }

    private function sendTransactionalNotification(ContactFormSubmission $submission): void
    {
        $html = $this->buildNotificationHtml($submission);

        $response = $this->httpClient->request('POST', self::API_BASE.'/smtp/email', [
            'timeout' => 20,
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => $this->senderName,
                    'email' => $this->senderEmail,
                ],
                'to' => [
                    ['email' => $this->notifyEmail, 'name' => 'ReserVue'],
                ],
                'replyTo' => [
                    'email' => $submission->email,
                    'name' => $submission->name,
                ],
                'subject' => 'ReserVue contact: '.$submission->name,
                'htmlContent' => '<html><body>'.$html.'</body></html>',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = $response->getContent(false);
            $this->logger->error('Brevo transactional email failed', [
                'status' => $status,
                'body' => $body,
            ]);
            $message = 'Could not send your message. Please try again later.';
            if ($this->kernelDebug) {
                $decoded = json_decode($body, true);
                if (\is_array($decoded) && isset($decoded['message']) && \is_string($decoded['message']) && $decoded['message'] !== '') {
                    $message .= ' ('.$decoded['message'].')';
                }
            }
            throw new \RuntimeException($message);
        }
    }

    private function upsertListContact(ContactFormSubmission $submission): void
    {
        [$firstName, $lastName] = $this->splitName($submission->name);

        $payload = [
            'email' => $submission->email,
            'attributes' => [
                'FIRSTNAME' => $firstName,
                'LASTNAME' => $lastName,
            ],
            'listIds' => [$this->contactListId],
            'updateEnabled' => true,
        ];

        $response = $this->httpClient->request('POST', self::API_BASE.'/contacts', [
            'timeout' => 20,
            'headers' => [
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $this->logger->warning('Brevo contact list sync failed (email was still sent)', [
                'status' => $status,
                'body' => $response->getContent(false),
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $trimmed = trim($fullName);
        if ($trimmed === '') {
            return ['Guest', ''];
        }

        $parts = preg_split('/\s+/u', $trimmed, 2, \PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return ['Guest', ''];
        }

        $first = $parts[0];
        $last = $parts[1] ?? '';

        return [$first, $last];
    }
}
