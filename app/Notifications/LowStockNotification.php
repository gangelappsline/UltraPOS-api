<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Database + mail alert when a product drops to/below its minimum stock.
 * Sent to the business owner.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $sku,
        public readonly float $currentStock,
        public readonly float $minStock,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self(
            $product->id,
            $product->name,
            $product->sku,
            (float) $product->stock,
            (float) $product->min_stock,
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low stock alert: '.$this->productName)
            ->greeting('Stock alert')
            ->line(sprintf(
                'The product "%s" (SKU %s) dropped to %s units, at or below its minimum of %s.',
                $this->productName,
                $this->sku,
                number_format($this->currentStock, 2),
                number_format($this->minStock, 2),
            ))
            ->line('Consider reordering soon to avoid stock-outs at the POS.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'sku' => $this->sku,
            'current_stock' => $this->currentStock,
            'min_stock' => $this->minStock,
            'message' => sprintf('%s is low on stock (%s left).', $this->productName, number_format($this->currentStock, 2)),
        ];
    }
}
