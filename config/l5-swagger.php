<?php

declare(strict_types=1);

return [
    'default' => [
        'api' => [
            'title' => env('L5_SWAGGER_API_TITLE', 'UltraPOS API'),
        ],

        'routes' => [
            /*
             * Route for accessing api documentation interface (Swagger UI).
             */
            'api' => 'api/documentation',

            /*
             * Route prefix for serving the generated OpenAPI JSON document.
             * Combined with "document_name" below this exposes the spec at
             * GET /api/documentation.json
             */
            'docs' => 'api',

            /*
             * Route for OAUTH2 authentication callback.
             */
            'oauth2_callback' => 'api/oauth2-callback',

            /*
             * Middleware allows to prevent unexpected access to API documentation
             */
            'middleware' => [
                'api' => [
                    \App\Http\Middleware\ForceJsonResponse::class,
                ],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],

            /*
             * Route Group options
             */
            'group_options' => [],
        ],

        'paths' => [
            /*
             * Absolute path to location where parsed annotations will be stored
             */
            'docs' => storage_path('api-docs'),

            /*
             * Absolute path to directory where to export views
             */
            'views' => resource_path('views/vendor/l5-swagger'),

            /*
             * Edit to set the api's base path
             */
            'base' => env('L5_SWAGGER_BASE_PATH', null),

            /*
             * Absolute path to directory that contains the l5-swagger assets
             */
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

            /*
             * File name of the generated json documentation file
             */
            'docs_json' => 'documentation.json',

            /*
             * File name of the generated YAML documentation file
             */
            'docs_yaml' => 'documentation.yaml',

            /*
             * Set this to `json` or `yaml` to determine which documentation file
             * to use in UI
             */
            'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

            /*
             * Absolute paths to directory containing the swagger annotations
             */
            'annotations' => [
                base_path('app'),
            ],
        ],

        'components' => [
            /*
             * List of security schemes (OAuth2 Passport Bearer tokens).
             */
            'securitySchemes' => [
                'passport' => [
                    'type' => 'oauth2',
                    'description' => 'Laravel Passport OAuth2 (password grant or personal access tokens). '
                        .'Alternatively send the token as: Authorization: Bearer {token}',
                    'flows' => [
                        'password' => [
                            'tokenUrl' => '/oauth/token',
                            'scopes' => [
                                'admin' => 'Global administrator scope',
                                'business' => 'Business owner / manager scope',
                                'seller' => 'Seller / cashier scope',
                                'customer' => 'Customer scope',
                            ],
                        ],
                    ],
                ],
            ],
            'security' => [
                [
                    'passport' => ['admin', 'business', 'seller', 'customer'],
                ],
            ],
        ],

        /*
         * Set this to `true` in development mode so that docs are regenerated
         * on every request.
         */
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

        /*
         * Set this to `true` to generate a copy of documentation in yaml format
         */
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        /*
         * Edit to trust the proxy's ip address - needed for AWS Load Balancer
         */
        'proxy' => false,

        /*
         * Configs plugin allows to fetch external configs instead of passing them to SwaggerUIBundle
         */
        'additional_config_url' => null,

        /*
         * Apply a sort to the operation list of each API
         */
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', 'alpha'),

        /*
         * Pass the validatorUrl parameter to SwaggerUi init on the JS side
         */
        'validator_url' => null,

        /*
         * Swagger UI configuration parameters
         */
        'ui' => [
            'display' => [
                'dark_mode' => env('L5_SWAGGER_UI_DARK_MODE', true),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'list'),
                'filter' => env('L5_SWAGGER_UI_FILTERS', true),
            ],

            'authorization' => [
                /*
                 * If set to true, it persists authorization data and it would
                 * not be lost on browser close/refresh.
                 */
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),

                'oauth2' => [
                    /*
                     * If set to true, adds PKCE to AuthorizationCodeGrant flow
                     */
                    'use_pkce_with_authorization_code_grant' => true,
                ],
            ],
        ],

        /*
         * Constants which can be used in annotations
         */
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000/api/v1'),
        ],
    ],
];
