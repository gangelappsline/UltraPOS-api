<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Root OpenAPI 3.0 document definition for L5-Swagger.
 * Swagger UI: /api/documentation — JSON spec: /api/documentation.json
 */
#[OA\Info(
    version: '1.0.0',
    title: 'UltraPOS API',
    description: 'Multi-tenant Point of Sale REST API (Laravel 12 + Passport OAuth2). '
        .'Serves web, mobile and desktop POS clients for retail stores, groceries, pharmacies, '
        .'restaurants and hardware stores. Every response uses the standardized envelope: '
        .'{ success, message, data|error, meta{timestamp, request_id} }.',
    contact: new OA\Contact(email: 'support@ultrapos.test', name: 'UltraPOS Support'),
    license: new OA\License(name: 'MIT'),
)]
#[OA\Server(url: 'http://localhost:8000/api/v1', description: 'Local development')]
#[OA\SecurityScheme(
    type: 'oauth2',
    securityScheme: 'passport',
    description: 'Laravel Passport OAuth2. Use the password grant (/oauth/token) or the '
        .'/api/v1/auth/login endpoint, then send "Authorization: Bearer {access_token}".',
    flows: [
        new OA\Flow(
            flow: 'password',
            tokenUrl: '/oauth/token',
            scopes: [
                'admin' => 'Global administrator',
                'business' => 'Business owner / manager',
                'seller' => 'Seller / cashier',
                'customer' => 'End customer',
            ],
        ),
    ],
)]
#[OA\SecurityScheme(
    type: 'http',
    securityScheme: 'bearerAuth',
    scheme: 'bearer',
    bearerFormat: 'JWT-like opaque token',
    description: 'Alternative: paste the Passport access token directly (Authorization: Bearer ...).',
)]
#[OA\Tag(name: 'Auth', description: 'Registration, login, token lifecycle and email verification')]
#[OA\Tag(name: 'Businesses', description: 'Tenant management and per-tenant settings')]
#[OA\Tag(name: 'Users', description: 'Staff management inside a tenant')]
#[OA\Tag(name: 'Products', description: 'Catalog: products, variants, stock adjustments, import/export')]
#[OA\Tag(name: 'Categories', description: 'Product category tree')]
#[OA\Tag(name: 'Brands', description: 'Product brands')]
#[OA\Tag(name: 'Units', description: 'Units of measure')]
#[OA\Tag(name: 'Inventory', description: 'Stock ledger and inventory reports')]
#[OA\Tag(name: 'Sales', description: 'POS sales, cancellations, refunds and receipts')]
#[OA\Tag(name: 'Customers', description: 'Customer CRM, credit and loyalty')]
#[OA\Tag(name: 'Suppliers', description: 'Supplier directory')]
#[OA\Tag(name: 'Purchases', description: 'Supplier purchase orders and receiving')]
#[OA\Tag(name: 'Payments', description: 'Polymorphic payment registration and refunds')]
#[OA\Tag(name: 'CashRegisters', description: 'Cash drawer sessions and movements')]
#[OA\Tag(name: 'Expenses', description: 'Business expenses')]
#[OA\Tag(name: 'Reports', description: 'Sales, profit, tax, inventory and customer analytics')]
#[OA\Tag(name: 'Dashboard', description: 'POS dashboard widgets')]
#[OA\Tag(name: 'Notifications', description: 'In-app notification center')]
#[OA\Tag(name: 'Subscriptions', description: 'Tenant subscription lifecycle')]
#[OA\Tag(name: 'Plans', description: 'Public SaaS plans catalog')]
#[OA\Tag(name: 'Admin', description: 'Global administration (role: admin)')]
#[OA\Tag(name: 'System', description: 'Health checks and metadata')]
final class OpenApiInfo
{
}
