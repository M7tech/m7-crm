<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME', env('APP_NAME', 'M7 CRM')),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-10-04'),
];
