<?php

declare(strict_types=1);

namespace VpnBot\Telegram;

final class TelegramClient
{
    public function __construct(
        private readonly string $api,
        private readonly string $requestsErrorLog = '/logs/requests_error',
    ) {
    }

    public function request($method, $data, $jsonHeader = 0)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api . $method,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $jsonHeader ? [
                'Content-Type: application/json',
            ] : [],
            CURLOPT_POSTFIELDS => $data,
        ]);
        $responseBody = curl_exec($ch);
        $response = json_decode($responseBody, true);

        if (! empty($response['description']) || is_null($responseBody)) {
            file_put_contents($this->requestsErrorLog, var_export([
                'r' => [
                    'method' => $method,
                    'data' => $this->summarizeRequestData($data),
                ],
                'a' => $responseBody,
            ], true) . "\n", FILE_APPEND);
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeRequestData(mixed $data): array
    {
        if (! is_array($data)) {
            return [
                'type' => get_debug_type($data),
            ];
        }

        $summary = [
            'type' => 'array',
            'keys' => array_keys($data),
        ];

        foreach ($data as $key => $value) {
            if ($value instanceof \CURLFile) {
                $summary['has_file'] = true;
                $summary['file_fields'][] = (string) $key;

                continue;
            }

            if (is_string($value) && in_array((string) $key, ['text', 'caption'], true)) {
                $summary['text_lengths'][(string) $key] = mb_strlen($value, 'utf-8');
            }
        }

        return $summary;
    }

    public function setCommands(array $commands): void
    {
        $this->request('setMyCommands', json_encode(['commands' => $commands]), 1);
    }

    public function send($chat, $text, ?int $to = 0, $button = false, $reply = false, $mode = 'HTML', $disableNotification = false)
    {
        if (is_null($text)) {
            $text = '';
        }

        $extra = null;
        if ($button) {
            $extra = ['inline_keyboard' => $button];
        }
        if (false !== $reply) {
            $extra = [
                'force_reply' => true,
                'input_field_placeholder' => $reply,
                'selective' => true,
            ];
        }

        $length = 3096;
        if (mb_strlen($text, 'utf-8') > $length) {
            $tails = $this->splitText($text, $length);
            foreach ($tails as $key => $value) {
                $data = [
                    'chat_id' => $chat,
                    'text' => "$value\n",
                    'parse_mode' => $mode,
                    'disable_notification' => $disableNotification,
                    'reply_to_message_id' => 0 == $key && $to > 0 ? $to : false,
                ];
                if ($key == array_key_last($tails) && $extra) {
                    $data['reply_markup'] = json_encode($extra);
                }
                $response = $this->request('sendMessage', $data);
            }

            return $response ?? null;
        }

        $data = [
            'chat_id' => $chat,
            'text' => $text,
            'parse_mode' => $mode,
            'disable_notification' => $disableNotification,
            'reply_to_message_id' => $to,
        ];
        if (! empty($extra)) {
            $data['reply_markup'] = json_encode($extra);
        }

        return $this->request('sendMessage', $data);
    }

    public function splitText($text, $size = 4096): array
    {
        $tails = preg_split('~\n~', $text);
        if (empty($tails)) {
            return [$text];
        }

        $lines = [];
        foreach ($tails as $value) {
            $lines[] = [
                'length' => mb_strlen($value, 'utf-8'),
                'text' => $value,
            ];
        }

        $index = 0;
        $output = [];
        foreach ($lines as $value) {
            $index += $value['length'];
            $output[(int) ceil($index / $size)] = ($output[(int) ceil($index / $size)] ?? '') . $value['text'] . "\n";
        }

        return array_values($output);
    }

    public function sendDraft($chat, $draftId, $text = '', $mode = 'HTML')
    {
        return $this->request('sendMessageDraft', json_encode([
            'chat_id' => $chat,
            'draft_id' => $draftId,
            'text' => $text,
            'parse_mode' => $mode,
        ]), 1);
    }

    public function image($chat, $idUrlCFile, $caption = false, $to = false)
    {
        return $this->request('sendPhoto', [
            'chat_id' => $chat,
            'photo' => $idUrlCFile,
            'caption' => $caption,
            'reply_to_message_id' => $to,
        ]);
    }

    public function sendPhoto($chat, $idUrlCFile, $caption = false, $to = false)
    {
        return $this->request('sendPhoto', [
            'chat_id' => $chat,
            'photo' => $idUrlCFile,
            'caption' => $caption,
            'reply_to_message_id' => $to,
            'parse_mode' => 'html',
        ]);
    }

    public function sendFile($chat, $idUrlCFile, $caption = false, $to = false)
    {
        return $this->request('sendDocument', [
            'chat_id' => $chat,
            'document' => $idUrlCFile,
            'caption' => $caption,
            'reply_to_message_id' => $to,
            'parse_mode' => 'html',
        ]);
    }

    public function update($chat, $messageId, $text, $button = false, $reply = false, $mode = 'HTML')
    {
        $extra = null;
        if ($button) {
            $extra = ['inline_keyboard' => $button];
        }
        if ($reply !== false) {
            $extra = [
                'force_reply' => true,
                'input_field_placeholder' => $reply,
            ];
        }

        $data = [
            'chat_id' => $chat,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $mode,
            'disable_web_page_preview' => true,
        ];
        if (! empty($extra)) {
            $data['reply_markup'] = json_encode($extra);
        }

        return $this->request('editMessageText', $data);
    }

    public function answer($callbackId, $textNotify = false, $notify = false)
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'show_alert' => $notify,
            'text' => $textNotify,
        ]);
    }

    public function delete($chat, $messageId)
    {
        return $this->request('deleteMessage', [
            'chat_id' => $chat,
            'message_id' => $messageId,
        ]);
    }

    public function pin($chat, $messageId, $notnotify = true)
    {
        return $this->request('pinChatMessage', [
            'chat_id' => $chat,
            'message_id' => $messageId,
            'disable_notification' => $notnotify,
        ]);
    }

    public function unpin($chat, $messageId)
    {
        return $this->request('unpinChatMessage', [
            'chat_id' => $chat,
            'message_id' => $messageId,
        ]);
    }
}
