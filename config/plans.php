<?php

return [
    'default' => 'starter',

    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'limits' => [
                'members' => 10,
                'companies' => 100,
                'automation_rules' => 5,
                'meta_connections' => 5,
            ],
        ],
        'growth' => [
            'label' => 'Growth',
            'limits' => [
                'members' => 50,
                'companies' => 1000,
                'automation_rules' => 25,
                'meta_connections' => 25,
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'limits' => [
                'members' => null,
                'companies' => null,
                'automation_rules' => null,
                'meta_connections' => null,
            ],
        ],
    ],
];
