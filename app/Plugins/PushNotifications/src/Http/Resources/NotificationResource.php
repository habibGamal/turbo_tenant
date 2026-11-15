<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property mixed $data
 * @property bool $read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property string|null $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'read' => (bool) $this->read,
            'read_at' => $this->read_at?->toISOString() ?? (string) $this->read_at,
            'type' => $this->type,
            'created_at' => $this->created_at?->toISOString() ?? (string) $this->created_at,
            'updated_at' => $this->updated_at?->toISOString() ?? (string) $this->updated_at,
        ];
    }
}
