<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use RuntimeException;

class MigrateToMongo extends Command
{
    protected $signature = 'melodic:migrate-to-mongo
        {--collection= : Migra solo esta colección}
        {--from= : Conexión relacional de origen (por defecto, la de la app)}
        {--dry-run : Lee y transforma sin escribir en MongoDB}';

    protected $description = 'Carga los datos relacionales en MongoDB con upsert por legacy_id';

    private const COLLECTIONS = [
        'subscriptions',
        'users',
        'albums',
        'songs',
        'playlists',
        'royalties',
        'earnings',
        'expenses',
    ];

    private const BATCH_SIZE = 500;

    private ConnectionInterface $source;

    private Database $target;

    private array $ids = [];

    private ?Collection $playlistSongs = null;

    public function handle(): int
    {
        $from = $this->option('from') ?? config('database.default');
        $only = $this->option('collection');

        if ($from === 'mongodb') {
            $this->error('La conexión de origen debe ser relacional.');

            return self::INVALID;
        }

        if ($only !== null && ! in_array($only, self::COLLECTIONS, true)) {
            $this->error('Colección desconocida. Opciones: '.implode(', ', self::COLLECTIONS).'.');

            return self::INVALID;
        }

        $this->source = DB::connection($from);
        $this->target = DB::connection('mongodb')->getDatabase();

        $report = [];

        foreach (self::COLLECTIONS as $collection) {
            if ($only === null || $only === $collection) {
                $report[] = $this->migrate($collection);
            }
        }

        $this->table(['Colección', 'Leídos', 'Escritos', 'Errores'], $report);

        if ($this->option('dry-run')) {
            $this->info('Simulación: no se escribió nada en MongoDB.');
        }

        return collect($report)->sum(fn (array $row) => $row[3]) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrate(string $collection): array
    {
        $read = $written = $errors = 0;
        $batch = [];

        foreach ($this->rows($collection)->cursor() as $row) {
            $read++;

            try {
                $batch[] = $this->upsert($collection, $row);
            } catch (RuntimeException $exception) {
                $errors++;
                $this->warn("{$collection}: {$exception->getMessage()}");
            }

            if (count($batch) === self::BATCH_SIZE) {
                [$ok, $failed] = $this->write($collection, $batch);
                $written += $ok;
                $errors += $failed;
                $batch = [];
            }
        }

        [$ok, $failed] = $this->write($collection, $batch);

        return [$collection, $read, $written + $ok, $errors + $failed];
    }

    private function rows(string $collection): Builder
    {
        return match ($collection) {
            'songs' => $this->source->table('songs')
                ->join('albums', 'albums.album_id', '=', 'songs.album_id')
                ->join('users', 'users.user_id', '=', 'albums.user_id')
                ->select('songs.*', 'albums.title as album_title', 'albums.path as album_path',
                    'albums.user_id as artist_id', 'users.username as artist_username')
                ->orderBy('songs.song_id'),
            'users' => $this->source->table('users')->orderBy('user_id'),
            default => $this->source->table($collection)->orderBy($this->sourceKey($collection)),
        };
    }

    private function upsert(string $collection, object $row): array
    {
        if ($collection === 'users') {
            return ['updateOne' => [['_id' => $row->user_id], ['$set' => $this->user($row)], ['upsert' => true]]];
        }

        $legacyId = (int) $row->{$this->sourceKey($collection)};
        $document = ['legacy_id' => $legacyId] + $this->document($collection, $row);

        return ['updateOne' => [
            ['legacy_id' => $legacyId],
            ['$set' => $document, '$setOnInsert' => ['_id' => $this->idFor($collection, $legacyId)]],
            ['upsert' => true],
        ]];
    }

    private function document(string $collection, object $row): array
    {
        return match ($collection) {
            'subscriptions' => [
                'name' => $row->name,
                'cost' => $this->money($row->cost),
                'months' => (int) $row->months,
            ],
            'albums' => [
                'title' => $row->title,
                'path' => $row->path,
                'user_id' => $row->user_id,
            ],
            'songs' => [
                'name' => $row->name,
                'length' => (int) $row->length,
                'plays' => new Int64((int) $row->plays),
                'file_path' => $row->file_path,
                'image_path' => $row->image_path,
                'album_id' => $this->reference('albums', $row->album_id),
                'album' => ['title' => $row->album_title, 'path' => $row->album_path],
                'artist' => ['user_id' => $row->artist_id, 'username' => $row->artist_username],
            ],
            'playlists' => [
                'name' => $row->name,
                'description' => $row->description,
                'path' => $row->path,
                'user_id' => $row->user_id,
                'songs' => $this->playlistSongs($row->playlist_id),
            ],
            'royalties' => [
                'recipient_id' => $row->recipient_id,
                'amount' => $this->money($row->amount),
                'last_payment' => $this->date($row->last_payment),
            ],
            'earnings' => [
                'amount' => $this->money($row->amount),
                'state' => $row->state,
                'at' => $this->date($row->at),
                'subscription' => $this->reference('subscriptions', $row->subscription),
                'comision' => $this->reference('royalties', $row->comision),
            ],
            'expenses' => [
                'amount' => $this->money($row->amount),
                'state' => $row->state,
                'at' => $this->date($row->at),
                'from' => $this->reference('royalties', $row->from),
                'user_id' => $row->user_id,
            ],
        } + $this->timestamps($row);
    }

    private function user(object $row): array
    {
        return [
            'username' => $row->username,
            'email' => $row->email,
            'password' => $row->password,
            'role' => $row->role,
            'is_suscribed' => (bool) $row->is_suscribed,
            'fin_subscription' => $this->day($row->fin_subscription),
            'subscription_id' => $this->reference('subscriptions', $row->subscription_id),
            'label' => $row->label,
            'path' => $row->path,
            'email_verified_at' => $this->date($row->email_verified_at),
            'remember_token' => $row->remember_token,
        ] + $this->timestamps($row);
    }

    private function playlistSongs(int $playlistId): array
    {
        $this->playlistSongs ??= $this->source->table('playlist_song')
            ->orderBy('created_at')
            ->orderBy('song_id')
            ->get()
            ->groupBy('playlist_id');

        return $this->playlistSongs->get($playlistId, collect())
            ->map(fn (object $entry) => [
                'song_id' => $this->reference('songs', $entry->song_id),
                'added_at' => $this->date($entry->created_at),
            ])
            ->values()
            ->all();
    }

    private function write(string $collection, array $batch): array
    {
        if ($batch === [] || $this->option('dry-run')) {
            return [count($batch), 0];
        }

        try {
            $result = $this->target->selectCollection($collection)->bulkWrite($batch, ['ordered' => false]);
        } catch (BulkWriteException $exception) {
            foreach ($exception->getWriteResult()->getWriteErrors() as $error) {
                $this->warn("{$collection}: {$error->getMessage()}");
            }

            $result = $exception->getWriteResult();
            $failed = count($result->getWriteErrors());

            return [$result->getMatchedCount() + $result->getUpsertedCount(), $failed];
        }

        return [$result->getMatchedCount() + $result->getUpsertedCount(), 0];
    }

    private function idFor(string $collection, int $legacyId): ObjectId
    {
        $this->ids[$collection] ??= $this->existingIds($collection);

        return $this->ids[$collection][$legacyId] ??= new ObjectId;
    }

    private function reference(string $collection, int|string|null $legacyId): ?ObjectId
    {
        if ($legacyId === null) {
            return null;
        }

        $this->ids[$collection] ??= $this->existingIds($collection);

        return $this->ids[$collection][(int) $legacyId]
            ?? throw new RuntimeException("no existe {$collection} con legacy_id {$legacyId}");
    }

    private function existingIds(string $collection): array
    {
        $documents = $this->target->selectCollection($collection)->find(
            ['legacy_id' => ['$exists' => true]],
            ['projection' => ['legacy_id' => 1]],
        );

        $ids = [];

        foreach ($documents as $document) {
            $ids[$document['legacy_id']] = $document['_id'];
        }

        return $ids;
    }

    private function sourceKey(string $collection): string
    {
        return match ($collection) {
            'subscriptions' => 'id',
            'albums' => 'album_id',
            'songs' => 'song_id',
            'playlists' => 'playlist_id',
            'royalties' => 'royalties_id',
            'earnings' => 'earning_id',
            'expenses' => 'expense_id',
        };
    }

    private function timestamps(object $row): array
    {
        return [
            'created_at' => $this->date($row->created_at),
            'updated_at' => $this->date($row->updated_at),
        ];
    }

    private function money(int|float|string $value): Decimal128
    {
        return new Decimal128(is_string($value) ? $value : number_format($value, 2, '.', ''));
    }

    private function date(?string $value): ?UTCDateTime
    {
        return $value === null ? null : new UTCDateTime(CarbonImmutable::parse($value, 'UTC'));
    }

    private function day(?string $value): ?UTCDateTime
    {
        return $value === null ? null : new UTCDateTime(CarbonImmutable::parse($value, 'UTC')->startOfDay());
    }
}
