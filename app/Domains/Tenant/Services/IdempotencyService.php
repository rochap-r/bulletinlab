<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Contracts\IdempotencyServiceInterface;
use App\Domains\Tenant\Exceptions\IdempotencyConflictException;
use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdempotencyService implements IdempotencyServiceInterface
{
    private const STATUS_COMPLETED = 1;

    private const STATUS_CONFLICT = 2;

    public function execute(
        string $key,
        string $commandName,
        callable $callback,
        ?Model $context = null,
        ?Model $actor = null,
        ?string $requestHash = null,
    ): mixed {
        if ($key === '' || $key === null || mb_strlen($key) > 191) {
            throw new IdempotencyConflictException('Invalid idempotency key.');
        }

        $requestHash = $requestHash ?? (string) Str::uuid();

        return DB::transaction(function () use (
            $key,
            $commandName,
            $callback,
            $context,
            $actor,
            $requestHash,
        ): mixed {
            $record = IdempotencyKey::query()
                ->where('actor_type', $actor?->getMorphClass())
                ->where('actor_id', $actor?->getKey())
                ->where('command_name', $commandName)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($record !== null) {
                if ($record->request_hash !== $requestHash) {
                    throw new IdempotencyConflictException(
                        'The same idempotency key was reused with a different payload.',
                    );
                }

                return $this->replayResult($record);
            }

            $result = $callback();

            $resultType = $result instanceof Model ? $result->getMorphClass() : null;
            $resultId = $result instanceof Model ? $result->getKey() : null;

            IdempotencyKey::query()->create([
                'context_type' => $context?->getMorphClass(),
                'context_id' => $context?->getKey(),
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'command_name' => $commandName,
                'key' => $key,
                'request_hash' => $requestHash,
                'result_type' => $resultType,
                'result_id' => $resultId,
                'status' => self::STATUS_COMPLETED,
                'expires_at' => now()->addHours(24),
            ]);

            return $result;
        });
    }

    public function hashPayload(array $payload): string
    {
        $canonical = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return hash('sha256', $canonical === false ? '' : $canonical);
    }

    private function replayResult(IdempotencyKey $record): mixed
    {
        if ($record->result_type !== null
            && $record->result_id !== null
            && class_exists($record->result_type)) {
            /** @var class-string<Model> $model */
            $model = $record->result_type;

            return $model::query()->find($record->result_id);
        }

        return null;
    }
}
