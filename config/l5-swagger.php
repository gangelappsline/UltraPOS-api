<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| L5-Swagger 9 configuration
|--------------------------------------------------------------------------
|
| Shape required by l5-swagger 9 (Darlington/l5-swagger):
|   - "default"       : NAME of the active documentation (a string!)
|   - "documentations": per-documentation overrides (merged over "defaults")
|   - "defaults"      : everything else (scan options, UI, security, ...)
|
| The old laravel-swagger/L5 v2-v7 style where the whole config lived nested
| under "default" is silently ignored: src/routes.php would iterate
| config('l5-swagger.documentations') (empty → no /api/documentation route at
| all) and GenerateDocumentationCommand would abort with
| "Documentation config not found".
|
*/

return [

    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => env('L5_SWAGGER_API_TITLE', 'UltraPOS API'),
            ],

            'routes' => [
                // Swagger UI: GET /api/documentation
                'api' => 'api/documentation',

                // Raw OpenAPI document: GET /api/documentation.json
                'docs' => 'api/documentation.json',
            ],

            'paths' => [
                // Absolute path to location where parsed annotations are stored.
                'docs' => storage_path('api-docs'),

                // Include the absolute URL (instead of a relative one) for the
                // assets Swagger UI loads.
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),

                'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

                // File names generated inside "docs" (path) above.
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',

                // 'json' or 'yaml': which of the two files the UI renders.
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

                // Directories scanned for #[OA\...] attributes.
                'annotations' => [
                    base_path('app'),
                ],
            ],

            /*
             * Global security requirement for every operation. The scheme
             * definitions themselves come from the #[OA\SecurityScheme]
             * attributes in app/OpenApi/OpenApiInfo.php; "security" is what
             * makes Swagger UI send the Authorization header on "Try it out".
             */
            'securityDefinitions' => [
                'security' => [
                    ['passport' => []],
                    ['bearerAuth' => []],
                ],
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'api/oauth2-callback',

            // Extra middleware for the documentation routes. ForceJsonResponse
            // keeps the JSON endpoint from ever being negotiated as HTML.
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [\App\Http\Middleware\ForceJsonResponse::class],
                'oauth2_callback' => [],
            ],

            'group_options' => [],
        ],

        'paths' => [
            'docs' => storage_path('api-docs'),
            'views' => base_path('resources/views/vendor/l5-swagger'),
            'base' => env('L5_SWAGGER_BASE_PATH', null),
            'excludes' => [],
        ],

        'scanOptions' => [
            'default_processors_configuration' => [],

            // null = swagger-php's own StaticAnalyser. NOTE: l5-swagger passes
            // this straight to OpenApi\Generator::setAnalyser(), which is
            // typed ?AnalyserInterface — a class string here is a TypeError.
            'analyser' => null,
            'analysis' => null,
            'processors' => [],
            'pattern' => null,
            'exclude' => [],

            'open_api_spec_version' => env(
                'L5_SWAGGER_OPEN_API_SPEC_VERSION',
                \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION
            ),
        ],

        'securityDefinitions' => [
            'securitySchemes' => [],
            'security' => [],
        ],

        // Regenerate on every request to /api/documentation (handy in dev).
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        'proxy' => false,

        'additional_config_url' => null,

        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', 'alpha'),

        'validator_url' => null,

        'constants' => [
            // Referenced by App\OpenApi\OpenApiInfo as a PHP constant.
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000/api/v1'),
        ],

        'ui' => [
            'display' => [
                'dark_mode' => env('L5_SWAGGER_UI_DARK_MODE', true),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'list'),
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
            ],

            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),

                'oauth2' => [
                    'use_pkce_with_authorization_code_grant' => false,
                ],
            ],
        ],
    ],

];
