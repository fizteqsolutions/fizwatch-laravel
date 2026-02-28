<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FizWatch URL
    |--------------------------------------------------------------------------
    |
    | The URL of your FizWatch instance (e.g. https://fizwatch.example.com).
    | If not set, error reporting to FizWatch is disabled.
    |
    */

    'url' => env('FIZWATCH_URL'),

    /*
    |--------------------------------------------------------------------------
    | FizWatch Project API Key
    |--------------------------------------------------------------------------
    |
    | The API key for your project, found in the FizWatch project settings.
    | If not set, error reporting to FizWatch is disabled.
    |
    */

    'key' => env('FIZWATCH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for a response from the FizWatch API.
    |
    */

    'timeout' => env('FIZWATCH_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Request body and query parameter field names that should be filtered
    | out before sending to FizWatch. Values are replaced with [Filtered].
    |
    */

    'sensitive_fields' => [
        '_token',
        'password',
        'password_confirmation',
        'credit_card',
        'ssn',
        'secret',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Headers
    |--------------------------------------------------------------------------
    |
    | HTTP header names that should be filtered out before sending to FizWatch.
    |
    */

    'sensitive_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes listed here will not be reported to FizWatch.
    | Uses instanceof matching, so adding a parent class also ignores
    | all of its subclasses.
    |
    */

    'ignored_exceptions' => [
        // \League\OAuth2\Server\Exception\OAuthServerException::class,
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

];
