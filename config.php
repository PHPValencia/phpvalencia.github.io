<?php

return [
    'production' => true,
    'community' => [
        'name' => 'PHP Valencia',
        'description' => 'Grupo local de desarrolladores web que usan el lenguaje #PHP. Y, como siempre, después de cada charla, algunos van a cenar por la zona, ¡todos sois bienvenidos!'
    ],
    'collections' => [
        'events' => [
            'sort' => '-date',
        ],
        'news' => [
            'sort' => '-date',
        ]
    ],
    'formattedDate' => function ($page, $date) {
        return (new \DateTime($date))->format('d/m/Y');
    },
    'menu' => [
        [
            'title' => '¿Qué es?',
            'url' => '/'
        ],
        [
            'title' => 'Eventos',
            'url' => '/events'
        ],
        [
            'title' => 'Boletín mensual',
            'url' => '/boletin-mensual'
        ],
        [
            'title' => 'Aloja el evento',
            'url' => '/host-the-event'
        ]
    ]
];
