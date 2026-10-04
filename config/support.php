<?php

return [
    'email' => env('SUPPORT_EMAIL', env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com'))),
];
