<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** In-app summary of a queued CSV product import. */
class ProductImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{created: int, updated: int, skipped: int, errors: array<int, string>}  $result
     */
    public function __construct(public readonly array $result)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'product_import_completed',
            'created' => $this->result['created'],
            'updated' => $this->result['updated'],
            'skipped' => $this->result['skipped'],
            'errors' => array_slice($this->result['errors'], 0, 20),
            'message' => sprintf(
                'Product import finished: %d created, %d updated, %d skipped, %d error(s).',
                $this->result['created'],
                $this->result['updated'],
                $this->result['skipped'],
                count($this->result['errors']),
            ),
        ];
    }
}
