<?php

declare(strict_types=1);

return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Contract API routes
        ['name' => 'contract#index', 'url' => '/api/contracts', 'verb' => 'GET'],
        ['name' => 'contract#archived', 'url' => '/api/contracts/archived', 'verb' => 'GET'],
        ['name' => 'contract#show', 'url' => '/api/contracts/{id}', 'verb' => 'GET'],
        ['name' => 'contract#create', 'url' => '/api/contracts', 'verb' => 'POST'],
        ['name' => 'contract#update', 'url' => '/api/contracts/{id}', 'verb' => 'PUT'],
        ['name' => 'contract#destroy', 'url' => '/api/contracts/{id}', 'verb' => 'DELETE'],
        ['name' => 'contract#archive', 'url' => '/api/contracts/{id}/archive', 'verb' => 'POST'],
        ['name' => 'contract#restore', 'url' => '/api/contracts/{id}/restore', 'verb' => 'POST'],

        // Category API routes
        ['name' => 'category#index', 'url' => '/api/categories', 'verb' => 'GET'],
        ['name' => 'category#create', 'url' => '/api/categories', 'verb' => 'POST'],
        ['name' => 'category#update', 'url' => '/api/categories/{id}', 'verb' => 'PUT'],
        ['name' => 'category#destroy', 'url' => '/api/categories/{id}', 'verb' => 'DELETE'],

        // Settings API routes
        ['name' => 'settings#get', 'url' => '/api/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/settings', 'verb' => 'PUT'],
    ]
];
