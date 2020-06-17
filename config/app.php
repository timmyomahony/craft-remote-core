<?php
return [
    'modules' => [
        'remoteservices' => [
            'class' => \weareferal\remoteservices\RemoteServicesModule::class,
            'components' => [
                'providerservices' => [
                    'class' => 'weareferal\remoteservices\services\ProviderServices',
                ],
            ],
        ],
    ],
    'bootstrap' => ['remoteservices'],
];
