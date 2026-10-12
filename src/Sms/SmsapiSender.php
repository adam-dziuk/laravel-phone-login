<?php

namespace AdamDziuk\PhoneLogin\Sms;

use AdamDziuk\PhoneLogin\Contracts\SmsSender;
use AdamDziuk\PhoneLogin\Exceptions\SmsDeliveryFailed;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Smsapi\Client\Curl\SmsapiHttpClient;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;
use Smsapi\Client\Feature\Sms\SmsFeature;
use Throwable;

class SmsapiSender implements SmsSender
{
    public function __construct(
        private readonly string $token,
        private readonly ?string $from = null,
        private readonly string $service = 'pl',
        private readonly bool $noUnicode = true,
        private readonly bool $fast = true,
        private readonly ?string $logChannel = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $from = (string) ($config['from'] ?? '');
        $channel = (string) ($config['log_channel'] ?? '');

        return new self(
            token: (string) ($config['token'] ?? ''),
            from: $from === '' ? null : $from,
            service: (string) ($config['service'] ?? 'pl'),
            noUnicode: (bool) ($config['no_unicode'] ?? true),
            fast: (bool) ($config['fast'] ?? true),
            logChannel: $channel === '' ? null : $channel,
        );
    }

    public function send(string $phone, string $message): void
    {
        $bag = SendSmsBag::withMessage($phone, $message);
        $bag->noUnicode = $this->noUnicode;
        $bag->fast = $this->fast;

        if ($this->from !== null) {
            $bag->from = $this->from;
        }

        $sms = $this->sms();

        try {
            $sms->sendSms($bag);
        } catch (Throwable $exception) {
            $this->report($phone, $exception);

            throw SmsDeliveryFailed::to($phone, $exception);
        }
    }

    private function sms(): SmsFeature
    {
        $client = new SmsapiHttpClient;

        return match ($this->service) {
            'pl' => $client->smsapiPlService($this->token)->smsFeature(),
            'com' => $client->smsapiComService($this->token)->smsFeature(),
            default => throw new InvalidArgumentException("Unknown SMSAPI service [{$this->service}], expected \"pl\" or \"com\"."),
        };
    }

    /**
     * The whole number is logged, because without it nobody can tell whose
     * code never arrived. Point log_channel at a channel with its own
     * retention if that matters for your privacy policy.
     */
    private function report(string $phone, Throwable $exception): void
    {
        Log::channel($this->logChannel)->error('SMSAPI rejected the message.', [
            'phone' => $phone,
            'from' => $this->from,
            'exception' => $exception::class,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ]);
    }
}
