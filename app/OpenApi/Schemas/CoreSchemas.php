<?php

declare(strict_types=1);

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    title: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@store.test'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+525512345678'),
        new OA\Property(property: 'avatar', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'email_verified', type: 'boolean', example: true),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['business']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Business',
    title: 'Business',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Demo Store'),
        new OA\Property(property: 'legal_name', type: 'string', nullable: true),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
        new OA\Property(property: 'business_type', type: 'string',
            enum: ['store', 'grocery', 'pharmacy', 'restaurant', 'hardware', 'other'], example: 'store'),
        new OA\Property(property: 'business_type_label', type: 'string', example: 'Retail store'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'city', type: 'string', nullable: true),
        new OA\Property(property: 'state', type: 'string', nullable: true),
        new OA\Property(property: 'country', type: 'string', example: 'MX'),
        new OA\Property(property: 'postal_code', type: 'string', nullable: true),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'timezone', type: 'string', example: 'America/Mexico_City'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'settings', type: 'object', nullable: true),
        new OA\Property(property: 'owner', ref: '#/components/schemas/User'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Category',
    title: 'Category',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'Beverages'),
        new OA\Property(property: 'slug', type: 'string', example: 'beverages'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image', type: 'string', nullable: true),
        new OA\Property(property: 'sort_order', type: 'integer', example: 0),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'children', type: 'array', items: new OA\Items(ref: '#/components/schemas/Category')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Brand',
    title: 'Brand',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Acme'),
        new OA\Property(property: 'slug', type: 'string', example: 'acme'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'logo', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UnitOfMeasure',
    title: 'UnitOfMeasure',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Kilogram'),
        new OA\Property(property: 'abbreviation', type: 'string', example: 'kg'),
        new OA\Property(property: 'type', type: 'string', enum: ['unit', 'weight', 'volume', 'length'], example: 'weight'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Product',
    title: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'sku', type: 'string', example: 'SKU-0001'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true, example: '7501234567890'),
        new OA\Property(property: 'name', type: 'string', example: 'Cola 600ml'),
        new OA\Property(property: 'slug', type: 'string', example: 'cola-600ml'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image', type: 'string', nullable: true),
        new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 8.50),
        new OA\Property(property: 'sale_price', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'tax_rate', type: 'number', format: 'float', example: 16.00),
        new OA\Property(property: 'stock', type: 'number', format: 'float', example: 120.00),
        new OA\Property(property: 'min_stock', type: 'number', format: 'float', example: 10.00),
        new OA\Property(property: 'max_stock', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'track_stock', type: 'boolean', example: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'is_featured', type: 'boolean', example: false),
        new OA\Property(property: 'is_low_stock', type: 'boolean', example: false),
        new OA\Property(property: 'is_out_of_stock', type: 'boolean', example: false),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'metadata', type: 'object', nullable: true, example: ['active_ingredient' => null]),
        new OA\Property(property: 'category', ref: '#/components/schemas/Category'),
        new OA\Property(property: 'brand', ref: '#/components/schemas/Brand'),
        new OA\Property(property: 'unit', ref: '#/components/schemas/UnitOfMeasure'),
        new OA\Property(property: 'variants', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductVariant')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ProductVariant',
    title: 'ProductVariant',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Cola 600ml — Light'),
        new OA\Property(property: 'sku', type: 'string', example: 'SKU-0001-L'),
        new OA\Property(property: 'barcode', type: 'string', nullable: true),
        new OA\Property(property: 'attributes', type: 'object', example: ['flavor' => 'light']),
        new OA\Property(property: 'cost_price', type: 'number', format: 'float', example: 8.00),
        new OA\Property(property: 'sale_price', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'stock', type: 'number', format: 'float', example: 40.00),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Customer',
    title: 'Customer',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'user_id', type: 'integer', nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'John Smith'),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
        new OA\Property(property: 'credit_limit', type: 'number', format: 'float', example: 5000.00),
        new OA\Property(property: 'current_balance', type: 'number', format: 'float', example: 320.50),
        new OA\Property(property: 'available_credit', type: 'number', format: 'float', example: 4679.50),
        new OA\Property(property: 'loyalty_points', type: 'integer', example: 128),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'SaleItem',
    title: 'SaleItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'product_variant_id', type: 'integer', nullable: true),
        new OA\Property(property: 'product_name', type: 'string', example: 'Cola 600ml'),
        new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 15.00),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 2.00),
        new OA\Property(property: 'discount', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 4.80),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 30.00),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 34.80),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Sale',
    title: 'Sale',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'reference_number', type: 'string', example: 'SAL-1-20250115-00001'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'cancelled', 'refunded'], example: 'completed'),
        new OA\Property(property: 'payment_status', type: 'string', enum: ['unpaid', 'partial', 'paid'], example: 'paid'),
        new OA\Property(property: 'sale_type', type: 'string', enum: ['pos', 'online', 'phone'], example: 'pos'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 30.00),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 4.80),
        new OA\Property(property: 'discount', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 34.80),
        new OA\Property(property: 'paid_amount', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'change_amount', type: 'number', format: 'float', example: 15.20),
        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'customer', ref: '#/components/schemas/Customer'),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/SaleItem')),
        new OA\Property(property: 'payments', type: 'array', items: new OA\Items(ref: '#/components/schemas/Payment')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Payment',
    title: 'Payment',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', example: 1),
        new OA\Property(property: 'payable_type', type: 'string', example: 'Sale'),
        new OA\Property(property: 'payable_id', type: 'integer', example: 1),
        new OA\Property(property: 'method', type: 'string', enum: ['cash', 'card', 'transfer', 'credit', 'mixed'], example: 'cash'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 34.80),
        new OA\Property(property: 'reference', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'failed', 'refunded'], example: 'completed'),
        new OA\Property(property: 'paid_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Supplier',
    title: 'Supplier',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Distribuidora Central'),
        new OA\Property(property: 'contact_name', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'tax_id', type: 'string', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Purchase',
    title: 'Purchase',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reference_number', type: 'string', example: 'PUR-1-20250115-00001'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'ordered', 'received', 'cancelled'], example: 'ordered'),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 850.00),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 136.00),
        new OA\Property(property: 'discount', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 986.00),
        new OA\Property(property: 'paid_amount', type: 'number', format: 'float', example: 0.00),
        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 986.00),
        new OA\Property(property: 'purchased_at', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'supplier', ref: '#/components/schemas/Supplier'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/PurchaseItem')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PurchaseItem',
    title: 'PurchaseItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 50.00),
        new OA\Property(property: 'unit_cost', type: 'number', format: 'float', example: 17.00),
        new OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 850.00),
        new OA\Property(property: 'tax', type: 'number', format: 'float', example: 136.00),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 986.00),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'InventoryMovement',
    title: 'InventoryMovement',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'type', type: 'string', enum: ['purchase', 'sale', 'adjustment', 'return', 'transfer', 'loss'], example: 'sale'),
        new OA\Property(property: 'quantity', type: 'number', format: 'float', example: -2.00),
        new OA\Property(property: 'previous_stock', type: 'number', format: 'float', example: 122.00),
        new OA\Property(property: 'new_stock', type: 'number', format: 'float', example: 120.00),
        new OA\Property(property: 'reference_type', type: 'string', nullable: true, example: 'App\\Models\\Sale'),
        new OA\Property(property: 'reference_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'product', ref: '#/components/schemas/Product'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CashRegister',
    title: 'CashRegister',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: ['open', 'closed'], example: 'open'),
        new OA\Property(property: 'opened_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'closed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'opening_amount', type: 'number', format: 'float', example: 500.00),
        new OA\Property(property: 'closing_amount', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'expected_amount', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'difference', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CashMovement',
    title: 'CashMovement',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'cash_register_id', type: 'integer', example: 1),
        new OA\Property(property: 'type', type: 'string', enum: ['opening', 'sale', 'refund', 'withdrawal', 'deposit', 'closing'], example: 'sale'),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 34.80),
        new OA\Property(property: 'signed_amount', type: 'number', format: 'float', example: 34.80),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Expense',
    title: 'Expense',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'category', type: 'string', example: 'utilities'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 120.00),
        new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2025-01-15'),
        new OA\Property(property: 'receipt', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Plan',
    title: 'Plan',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Starter'),
        new OA\Property(property: 'slug', type: 'string', example: 'starter'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 29.00),
        new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly'], example: 'monthly'),
        new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string'), example: ['1 register', '500 products']),
        new OA\Property(property: 'limits', type: 'object', example: ['users' => 3, 'products' => 500]),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Subscription',
    title: 'Subscription',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'status', type: 'string', enum: ['trialing', 'active', 'past_due', 'cancelled', 'expired'], example: 'trialing'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'on_trial', type: 'boolean', example: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'plan', ref: '#/components/schemas/Plan'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'Notification',
    title: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', example: 'LowStockNotification'),
        new OA\Property(property: 'data', type: 'object', example: ['message' => 'Cola 600ml is low on stock (3 left).']),
        new OA\Property(property: 'read', type: 'boolean', example: false),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuditLog',
    title: 'AuditLog',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'business_id', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'action', type: 'string', enum: ['created', 'updated', 'deleted', 'restored', 'force_deleted'], example: 'updated'),
        new OA\Property(property: 'model_type', type: 'string', example: 'Product'),
        new OA\Property(property: 'model_id', type: 'integer', example: 42),
        new OA\Property(property: 'old_values', type: 'object', nullable: true),
        new OA\Property(property: 'new_values', type: 'object', nullable: true),
        new OA\Property(property: 'ip_address', type: 'string', nullable: true),
        new OA\Property(property: 'user_agent', type: 'string', nullable: true),
        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
final class CoreSchemas
{
}
