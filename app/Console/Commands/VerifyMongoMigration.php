<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

class VerifyMongoMigration extends Command
{
    protected $signature = 'melodic:verify-mongo
        {--from= : Conexión relacional de origen (por defecto, la de la app)}
        {--sample=20 : Documentos por colección para comparar campo por campo}';

    protected $description = 'Compara la base relacional con MongoDB y falla si hay diferencias';

    private const SCHEMA = [
        'subscriptions' => ['key' => 'id', 'fields' => [
            'name' => 'string', 'cost' => 'money', 'months' => 'int',
            'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'users' => ['key' => 'user_id', 'fields' => [
            'username' => 'string', 'email' => 'string', 'password' => 'string', 'role' => 'string',
            'is_suscribed' => 'bool', 'fin_subscription' => 'day', 'subscription_id' => 'subscriptions',
            'label' => 'string', 'path' => 'string', 'email_verified_at' => 'date',
            'remember_token' => 'string', 'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'albums' => ['key' => 'album_id', 'fields' => [
            'title' => 'string', 'path' => 'string', 'user_id' => 'string',
            'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'songs' => ['key' => 'song_id', 'fields' => [
            'name' => 'string', 'length' => 'int', 'plays' => 'int', 'file_path' => 'string',
            'image_path' => 'string', 'album_id' => 'albums', 'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'playlists' => ['key' => 'playlist_id', 'fields' => [
            'name' => 'string', 'description' => 'string', 'path' => 'string', 'user_id' => 'string',
            'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'royalties' => ['key' => 'royalties_id', 'fields' => [
            'recipient_id' => 'string', 'amount' => 'money', 'last_payment' => 'date',
            'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'earnings' => ['key' => 'earning_id', 'fields' => [
            'amount' => 'money', 'state' => 'string', 'at' => 'date', 'subscription' => 'subscriptions',
            'comision' => 'royalties', 'created_at' => 'date', 'updated_at' => 'date',
        ]],
        'expenses' => ['key' => 'expense_id', 'fields' => [
            'amount' => 'money', 'state' => 'string', 'at' => 'date', 'from' => 'royalties',
            'user_id' => 'string', 'created_at' => 'date', 'updated_at' => 'date',
        ]],
    ];

    private const REFERENCES = [
        ['users', 'subscription_id', 'subscriptions'],
        ['users', 'label', 'users'],
        ['albums', 'user_id', 'users'],
        ['songs', 'album_id', 'albums'],
        ['songs', 'artist.user_id', 'users'],
        ['playlists', 'user_id', 'users'],
        ['playlists', 'songs.song_id', 'songs'],
        ['royalties', 'recipient_id', 'users'],
        ['earnings', 'subscription', 'subscriptions'],
        ['earnings', 'comision', 'royalties'],
        ['expenses', 'from', 'royalties'],
        ['expenses', 'user_id', 'users'],
    ];

    private ConnectionInterface $source;

    private Database $target;

    private array $legacyIds = [];

    private array $differences = [];

    public function handle(): int
    {
        $this->source = DB::connection($this->option('from') ?? config('database.default'));
        $this->target = DB::connection('mongodb')->getDatabase();

        $this->compareCounts();
        $this->compareTotals();
        $this->comparePlaylistSizes();
        $this->checkReferences();
        $this->compareSamples((int) $this->option('sample'));

        if ($this->differences === []) {
            $this->info('Sin diferencias entre la base relacional y MongoDB.');

            return self::SUCCESS;
        }

        $this->table(['Comprobación', 'Diferencia'], $this->differences);
        $this->error(count($this->differences).' diferencias encontradas.');

        return self::FAILURE;
    }

    private function compareCounts(): void
    {
        foreach (array_keys(self::SCHEMA) as $collection) {
            $this->expect(
                'conteo',
                $collection,
                $this->source->table($collection)->count(),
                $this->target->selectCollection($collection)->countDocuments(),
            );
        }
    }

    private function compareTotals(): void
    {
        foreach (['royalties', 'earnings', 'expenses'] as $collection) {
            $this->expect(
                'suma de amount',
                $collection,
                $this->money($this->source->table($collection)->sum('amount')),
                $this->money($this->mongoSum($collection, '$amount')),
            );
        }

        $this->expect(
            'suma de plays',
            'songs',
            (int) $this->source->table('songs')->sum('plays'),
            (int) (string) $this->mongoSum('songs', '$plays'),
        );
    }

    private function comparePlaylistSizes(): void
    {
        $expected = $this->source->table('playlists')
            ->leftJoin('playlist_song', 'playlist_song.playlist_id', '=', 'playlists.playlist_id')
            ->groupBy('playlists.playlist_id')
            ->selectRaw('playlists.playlist_id as id, count(playlist_song.song_id) as total')
            ->pluck('total', 'id')
            ->map(fn ($total) => (int) $total)
            ->all();

        $actual = [];

        foreach ($this->target->selectCollection('playlists')->aggregate([
            ['$project' => ['legacy_id' => 1, 'total' => ['$size' => ['$ifNull' => ['$songs', []]]]]],
        ]) as $playlist) {
            $actual[$playlist['legacy_id']] = $playlist['total'];
        }

        foreach ($expected as $id => $total) {
            $this->expect('canciones por playlist', "playlists legacy_id {$id}", $total, $actual[$id] ?? null);
        }
    }

    private function checkReferences(): void
    {
        foreach (self::REFERENCES as [$collection, $field, $target]) {
            $pipeline = str_contains($field, 'songs.') ? [['$unwind' => '$songs']] : [];

            $orphans = $this->target->selectCollection($collection)->aggregate([
                ...$pipeline,
                ['$match' => [$field => ['$ne' => null]]],
                ['$lookup' => ['from' => $target, 'localField' => $field, 'foreignField' => '_id', 'as' => 'match']],
                ['$match' => ['match' => ['$size' => 0]]],
                ['$count' => 'total'],
            ])->toArray();

            $this->expect('referencias', "{$collection}.{$field} → {$target}", 0, $orphans[0]['total'] ?? 0);
        }
    }

    private function compareSamples(int $size): void
    {
        foreach (self::SCHEMA as $collection => ['key' => $key, 'fields' => $fields]) {
            $rows = $this->source->table($collection)->inRandomOrder()->limit($size)->get();

            foreach ($rows as $row) {
                $filter = $collection === 'users' ? ['_id' => $row->user_id] : ['legacy_id' => (int) $row->{$key}];
                $document = $this->target->selectCollection($collection)->findOne($filter);
                $label = "{$collection} {$key} {$row->{$key}}";

                if ($document === null) {
                    $this->differences[] = ['muestra', "{$label}: no existe en MongoDB"];

                    continue;
                }

                foreach ($fields as $field => $type) {
                    if (! $document->offsetExists($field)) {
                        $this->differences[] = ['muestra', "{$label}: falta el campo {$field}"];

                        continue;
                    }

                    $this->expect(
                        'muestra',
                        "{$label}.{$field}",
                        $this->normalize($type, $row->{$field}),
                        $this->normalize($type, $document[$field], fromMongo: true),
                    );
                }
            }
        }
    }

    private function expect(string $check, string $subject, mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            $this->differences[] = [$check, "{$subject}: esperado ".var_export($expected, true).', obtenido '.var_export($actual, true)];
        }
    }

    private function normalize(string $type, mixed $value, bool $fromMongo = false): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'string' => (string) $value,
            'int' => (int) (string) $value,
            'bool' => (bool) $value,
            'money' => $this->money($value),
            'date' => $this->dateTime($value)->format('Y-m-d H:i:s'),
            'day' => $this->dateTime($value)->format('Y-m-d'),
            default => $fromMongo ? $this->legacyId($type, (string) $value) : (int) $value,
        };
    }

    private function money(mixed $value): string
    {
        $value = is_float($value) ? number_format($value, 2, '.', '') : (string) ($value ?? '0');

        return bcadd($value, '0', 2);
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        return $value instanceof UTCDateTime
            ? CarbonImmutable::instance($value->toDateTime())->utc()
            : CarbonImmutable::parse($value, 'UTC');
    }

    private function legacyId(string $collection, string $objectId): ?int
    {
        $this->legacyIds[$collection] ??= collect($this->target->selectCollection($collection)->find(
            [],
            ['projection' => ['legacy_id' => 1]],
        ))->mapWithKeys(fn (BSONDocument $document) => [(string) $document['_id'] => $document['legacy_id'] ?? null])->all();

        return $this->legacyIds[$collection][$objectId] ?? null;
    }

    private function mongoSum(string $collection, string $field): mixed
    {
        $result = $this->target->selectCollection($collection)->aggregate([
            ['$group' => ['_id' => null, 'total' => ['$sum' => $field]]],
        ])->toArray();

        return $result[0]['total'] ?? 0;
    }
}
