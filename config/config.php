<?php

define('APP_NAME', 'Forex System');

$scriptDirectory = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/')
);

$baseUrl = rtrim(
    $scriptDirectory,
    '/'
) . '/';

define(
    'BASE_URL',
    $baseUrl === '//'
        ? '/'
        : $baseUrl
);


/*
|--------------------------------------------------------------------------
| API KEY DE TWELVE DATA
|--------------------------------------------------------------------------
*/

$apiKeyFromEnvironment =
    getenv('TWELVE_DATA_API_KEY');

define(
    'TWELVE_DATA_API_KEY',
    $apiKeyFromEnvironment
        ?: '697afcd9741d4d7a858c0cfd1405967f'
);


/*
|--------------------------------------------------------------------------
| URL DE TWELVE DATA
|--------------------------------------------------------------------------
*/

define(
    'TWELVE_DATA_BASE_URL',
    'https://api.twelvedata.com'
);


/*
|--------------------------------------------------------------------------
| CACHE DEL MERCADO
|--------------------------------------------------------------------------
|
| 300 segundos = 5 minutos
|
| Durante 5 minutos el servidor reutiliza
| las cotizaciones obtenidas anteriormente.
|
*/

define(
    'MARKET_CACHE_SECONDS',
    300
);


/*
|--------------------------------------------------------------------------
| BLOQUEO DEL BOTÓN ACTUALIZAR
|--------------------------------------------------------------------------
|
| 300000 milisegundos = 5 minutos
|
*/

define(
    'MANUAL_REFRESH_COOLDOWN_MS',
    300000
);


/*
|--------------------------------------------------------------------------
| ACTUALIZACIÓN AUTOMÁTICA
|--------------------------------------------------------------------------
|
| 900000 milisegundos = 15 minutos
|
*/

define(
    'MARKET_REFRESH_MS',
    900000
);

////////////