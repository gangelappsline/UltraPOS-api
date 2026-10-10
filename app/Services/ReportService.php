<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SaleStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporting & analytics. All queries are tenant-scoped by an explicit
 * business_id (services run behind the EnsureBusinessScope middleware).
 */
class ReportService
{
    /** Sales report with optional daily grouping. */
    public function sales(
        int $businessId,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $groupBy = 'day',
        ?int $userId = null,
        ?string $saleType = null,
        ?string $paymentStatus = null,
    ): array {
        $query = Sale::query()
            ->where('business_id', $businessId)
            ->where('status', '!=', SaleStatus::CANCELLED->value)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($saleType, fn ($q) => $q->where('sale_type', $saleType))
            ->when($paymentStatus, fn ($q) => $q->where('payment_status', $paymentStatus));

        $totals = (clone $query)
            ->selectRaw(
                'COUNT(*) as sales_count,
                 COALESCE(SUM(subtotal), 0) as subtotal,
                 COALESCE(SUM(tax), 0) as tax,
                 COALESCE(SUM(discount), 0) as discount,
                 COALESCE(SUM(total), 0) as total,
                 COALESCE(SUM(paid_amount), 0) as paid_amount,
                 COALESCE(SUM(total - paid_amount), 0) as outstanding'
            )
            ->first();

        // Portable date grouping (MySQL + SQLite for tests).
        $driver = DB::connection()->getDriverName();
        $dateColumn = match (true) {
            $groupBy === 'month' && $driver === 'mysql' => "DATE_FORMAT(created_at, '%Y-%m')",
            $groupBy === 'month' => "strftime('%Y-%m', created_at)",
            $driver === 'mysql' => 'DATE(created_at)',
            default => 'date(created_at)',
        };

        $series = (clone $query)
            ->groupBy('period')
            ->selectRaw("{$dateColumn} as period, COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(tax), 0) as tax")
            ->orderBy('period')
            ->get();

        return [
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'group_by' => $groupBy,
                'user_id' => $userId,
                'sale_type' => $saleType,
                'payment_status' => $paymentStatus,
            ],
            'totals' => [
                'sales_count' => (int) ($totals->sales_count ?? 0),
                'subtotal' => (float) ($totals->subtotal ?? 0),
                'tax' => (float) ($totals->tax ?? 0),
                'discount' => (float) ($totals->discount ?? 0),
                'total' => (float) ($totals->total ?? 0),
                'paid_amount' => (float) ($totals->paid_amount ?? 0),
                'outstanding' => (float) ($totals->outstanding ?? 0),
            ],
            'series' => $series,
        ];
    }

    /** Top selling products by revenue within a period. */
    public function topSellingProducts(int $businessId, ?Carbon $from, ?Carbon $to, int $limit = 10): array
    {
        $rows = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)
                ->completed()
                ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from->toDateString()))
                ->when($to, fn ($qq) => $qq->whereDate('created_at', '<=', $to->toDateString())))
            ->groupBy('product_id', 'product_name')
            ->selectRaw(
                'product_id, product_name,
                 SUM(quantity) as units_sold,
                 SUM(subtotal) as gross_revenue,
                 SUM(total) as revenue'
            )
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'product_id' => $row->product_id,
            'product_name' => $row->product_name,
            'units_sold' => (float) $row->units_sold,
            'gross_revenue' => (float) $row->gross_revenue,
            'revenue' => (float) $row->revenue,
        ])->all();
    }

    /** Low stock report with reorder suggestion. */
    public function lowStockProducts(int $businessId): array
    {
        $products = Product::query()
            ->where('business_id', $businessId)
            ->with('unit')
            ->lowStock()
            ->orderByRaw('(stock - min_stock) ASC')
            ->get();

        return $products->map(fn (Product $product): array => [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'stock' => (float) $product->stock,
            'min_stock' => (float) $product->min_stock,
            'suggested_reorder' => (float) max(
                ($product->max_stock ?? ($product->min_stock * 2)) - $product->stock,
                0
            ),
            'unit' => $product->unit?->abbreviation,
        ])->all();
    }

    /** Top customers by spend within a period. */
    public function topCustomers(int $businessId, ?Carbon $from, ?Carbon $to, int $limit = 10): array
    {
        return Sale::query()
            ->where('business_id', $businessId)
            ->completed()
            ->whereNotNull('customer_id')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->with('customer:id,name,email,phone,loyalty_points')
            ->groupBy('customer_id')
            ->selectRaw(
                'customer_id,
                 COUNT(*) as purchases_count,
                 COALESCE(SUM(total), 0) as total_spent'
            )
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(fn ($sale): array => [
                'customer' => $sale->customer !== null ? [
                    'id' => $sale->customer->id,
                    'name' => $sale->customer->name,
                    'email' => $sale->customer->email,
                    'phone' => $sale->customer->phone,
                    'loyalty_points' => $sale->customer->loyalty_points,
                ] : null,
                'purchases_count' => (int) $sale->purchases_count,
                'total_spent' => (float) $sale->total_spent,
            ])
            ->all();
    }

    /** Profit report: revenue − COGS (cost_price snapshot per line). */
    public function profit(int $businessId, ?Carbon $from, ?Carbon $to): array
    {
        $items = SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)
                ->completed()
                ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from->toDateString()))
                ->when($to, fn ($qq) => $qq->whereDate('created_at', '<=', $to->toDateString())))
            ->with('product:id,cost_price')
            ->get();

        $revenue = 0.0;
        $cogs = 0.0;

        foreach ($items as $item) {
            $revenue += (float) $item->subtotal;
            $cogs += (float) ($item->product?->cost_price ?? 0) * (float) $item->quantity;
        }

        $expenses = Expense::query()
            ->where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to->toDateString()))
            ->sum('amount');

        $grossProfit = round($revenue - $cogs, 2);

        return [
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0.0,
            'expenses' => round((float) $expenses, 2),
            'net_profit' => round($grossProfit - (float) $expenses, 2),
        ];
    }

    /** Inventory valuation at cost and at retail. */
    public function inventoryValuation(int $businessId): array
    {
        $totals = Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->selectRaw(
                'COUNT(*) as products_count,
                 COALESCE(SUM(stock), 0) as total_units,
                 COALESCE(SUM(stock * cost_price), 0) as cost_value,
                 COALESCE(SUM(stock * sale_price), 0) as retail_value'
            )
            ->first();

        $topByValue = Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->selectRaw('id, sku, name, stock, cost_price, (stock * cost_price) as value')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return [
            'products_count' => (int) ($totals->products_count ?? 0),
            'total_units' => (float) ($totals->total_units ?? 0),
            'cost_value' => round((float) ($totals->cost_value ?? 0), 2),
            'retail_value' => round((float) ($totals->retail_value ?? 0), 2),
            'potential_margin' => round((float) ($totals->retail_value ?? 0) - (float) ($totals->cost_value ?? 0), 2),
            'low_stock_count' => Product::query()->where('business_id', $businessId)->lowStock()->count(),
            'out_of_stock_count' => Product::query()->where('business_id', $businessId)
                ->where('track_stock', true)->where('stock', '<=', 0)->count(),
            'expiring_soon_count' => Product::query()->where('business_id', $businessId)->expiringSoon(30)->count(),
            'top_by_value' => $topByValue,
        ];
    }

    /** Tax collected report (completed sales), grouped by rate. */
    public function tax(int $businessId, ?Carbon $from, ?Carbon $to): array
    {
        $base = fn () => SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('business_id', $businessId)
                ->completed()
                ->when($from, fn ($qq) => $qq->whereDate('created_at', '>=', $from->toDateString()))
                ->when($to, fn ($qq) => $qq->whereDate('created_at', '<=', $to->toDateString())));

        $totalTax = (float) ($base)()->sum('tax');
        $totalBase = (float) ($base)()->sum('subtotal');

        $byRate = ($base)()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->groupBy('products.tax_rate')
            ->selectRaw('products.tax_rate as rate, SUM(sale_items.subtotal) as base, SUM(sale_items.tax) as tax')
            ->orderBy('rate')
            ->get();

        return [
            'taxable_base' => round($totalBase, 2),
            'tax_collected' => round($totalTax, 2),
            'by_rate' => $byRate->map(fn ($row): array => [
                'rate' => (float) $row->rate,
                'base' => round((float) $row->base, 2),
                'tax' => round((float) $row->tax, 2),
            ])->all(),
        ];
    }

    /** Dashboard aggregate (stats + alerts + recent sales). */
    public function dashboard(int $businessId): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::today()->startOfMonth();

        $todayStats = Sale::query()
            ->where('business_id', $businessId)
            ->completed()
            ->whereDate('created_at', $today->toDateString())
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(total), 0) as t, COALESCE(SUM(tax), 0) as x')
            ->first();

        $monthStats = Sale::query()
            ->where('business_id', $businessId)
            ->completed()
            ->whereDate('created_at', '>=', $monthStart->toDateString())
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(total), 0) as t')
            ->first();

        $yesterday = Sale::query()
            ->where('business_id', $businessId)
            ->completed()
            ->whereDate('created_at', $today->copy()->subDay()->toDateString())
            ->sum('total');

        $todayTotal = (float) ($todayStats->t ?? 0);
        $growth = $yesterday > 0 ? round((($todayTotal - (float) $yesterday) / (float) $yesterday) * 100, 2) : null;

        return [
            'today' => [
                'sales_count' => (int) ($todayStats->c ?? 0),
                'total' => $todayTotal,
                'tax' => (float) ($todayStats->x ?? 0),
                'growth_vs_yesterday_percent' => $growth,
            ],
            'month' => [
                'sales_count' => (int) ($monthStats->c ?? 0),
                'total' => (float) ($monthStats->t ?? 0),
            ],
            'counters' => [
                'products' => Product::query()->where('business_id', $businessId)->count(),
                'active_products' => Product::query()->where('business_id', $businessId)->active()->count(),
                'low_stock' => Product::query()->where('business_id', $businessId)->lowStock()->count(),
                'customers' => Customer::query()->where('business_id', $businessId)->count(),
                'receivables' => round((float) Customer::query()->where('business_id', $businessId)->sum('current_balance'), 2),
                'pending_sales' => Sale::query()->where('business_id', $businessId)
                    ->status(SaleStatus::PENDING)->count(),
            ],
            'top_products_today' => $this->topSellingProducts($businessId, $today, $today, 5),
            'recent_sales' => Sale::query()
                ->where('business_id', $businessId)
                ->with(['customer:id,name', 'user:id,name'])
                ->latest('id')
                ->limit(10)
                ->get(),
            'alerts' => $this->alerts($businessId),
        ];
    }

    /** Operational alerts for the dashboard. */
    public function alerts(int $businessId): array
    {
        $alerts = [];

        $lowStock = Product::query()->where('business_id', $businessId)->lowStock()->count();
        if ($lowStock > 0) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => 'warning',
                'message' => "{$lowStock} product(s) at or below minimum stock.",
                'count' => $lowStock,
            ];
        }

        $expiring = Product::query()->where('business_id', $businessId)->expiringSoon(30)->count();
        if ($expiring > 0) {
            $alerts[] = [
                'type' => 'expiring_products',
                'severity' => 'warning',
                'message' => "{$expiring} product(s) expiring within 30 days.",
                'count' => $expiring,
            ];
        }

        $subscription = Subscription::query()
            ->where('business_id', $businessId)
            ->usable()
            ->latest('id')
            ->first();

        if ($subscription !== null) {
            if ($subscription->onTrial() && $subscription->trial_ends_at?->diffInDays(Carbon::now(), false) <= 3) {
                $alerts[] = [
                    'type' => 'trial_ending',
                    'severity' => 'info',
                    'message' => 'Your free trial is about to end.',
                ];
            } elseif ($subscription->isExpiringWithin((int) config('ultrapos.subscription_warning_days', 7))) {
                $alerts[] = [
                    'type' => 'subscription_expiring',
                    'severity' => 'warning',
                    'message' => 'Your subscription expires on '.$subscription->ends_at?->toDateString().'.',
                ];
            }
        }

        $unpaid = Sale::query()
            ->where('business_id', $businessId)
            ->completed()
            ->where('payment_status', '!=', 'paid')
            ->sum('total');

        if ((float) $unpaid > 0) {
            $alerts[] = [
                'type' => 'receivables',
                'severity' => 'info',
                'message' => 'Outstanding sale balances to collect.',
                'amount' => round((float) $unpaid, 2),
            ];
        }

        return $alerts;
    }

    /** Payment method breakdown for a period (used by reports/tax audits). */
    public function paymentsByMethod(int $businessId, ?Carbon $from, ?Carbon $to): array
    {
        return Payment::query()
            ->where('business_id', $businessId)
            ->where('status', TransactionStatus::COMPLETED->value)
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to->toDateString()))
            ->groupBy('method')
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->get()
            ->all();
    }
}
