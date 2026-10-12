<?php

return [
    'phone_invalid' => 'To nie wygląda na numer telefonu.',
    'phone_not_allowed' => 'Na ten numer nie wyślemy kodu.',
    'delivery_failed' => 'Nie udało się wysłać kodu. Spróbuj za chwilę.',
    'code_digits' => 'Kod ma sześć cyfr.',
    'invalid_code' => 'Kod jest nieprawidłowy, wygasł albo został już użyty.',

    // Celowo bez polskich znaków — z no_unicode SMSAPI odrzuciłoby wiadomość
    // spoza GSM-7, a bez niego policzyłoby ją jako dwa razy droższe UCS-2.
    'sms' => 'Twoj kod logowania: :code. Wazny :minutes minut. Nikomu go nie podawaj.',
];
