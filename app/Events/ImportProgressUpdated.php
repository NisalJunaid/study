<?php

namespace App\Events;

use App\Models\Import;
use App\Support\Broadcasting\RealtimePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $importId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("import.{$this->importId}"),
            new PrivateChannel('admin.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.progress.updated';
    }

    public function broadcastWith(): array
    {
        $import = Import::query()->find($this->importId);

        if (! $import) {
            return [
                'import' => [
                    'id' => $this->importId,
                    'status' => Import::STATUS_FAILED,
                ],
            ];
        }

        return [
            'import' => RealtimePayload::import($import),
        ];
    }
}
