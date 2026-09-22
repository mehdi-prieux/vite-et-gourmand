<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/**
 * Envoie un message applicatif. En test, MAIL_TRANSPORT=log écrit un journal
 * local dédié ; en production, la fonction mail() est utilisée.
 */
function sendApplicationMail(string $to, string $subject, string $text): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject)) {
        throw new InvalidArgumentException('Destinataire ou objet de message invalide.');
    }

    $transport = appConfig('MAIL_TRANSPORT') ?: 'mail';
    if (!in_array($transport, ['mail', 'log'], true)) {
        throw new RuntimeException('MAIL_TRANSPORT invalide.');
    }
    if ($transport === 'log') {
        if (appConfig('APP_ENV') !== 'test') {
            throw new RuntimeException('Le transport de test est interdit hors APP_ENV=test.');
        }
        $path = appConfig('MAIL_LOG_PATH');
        if (!is_string($path) || $path === '') {
            throw new RuntimeException('MAIL_LOG_PATH est requis avec MAIL_TRANSPORT=log.');
        }
        $entry = json_encode(
            ['to' => $to, 'subject' => $subject, 'text' => $text, 'sent_at' => gmdate(DATE_ATOM)],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        return file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    $from = appConfig('MAIL_FROM');
    if ($from === false && appConfig('APP_ENV') === 'production') {
        throw new RuntimeException('MAIL_FROM est requis en production.');
    }
    $from = $from ?: 'no-reply@vite-gourmand.local';
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('MAIL_FROM invalide.');
    }
    $headers = [
        'From: Vite & Gourmand <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, implode("\r\n", $headers));
}
