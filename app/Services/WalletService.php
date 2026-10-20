<?php

namespace App\Services;

use App\Models\Earning;
use App\Models\Expense;
use App\Models\Royalty;
use App\Models\Song;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

class WalletService
{
    private const ROYALTY_PER_PLAY = '0.15';

    private const PAYOUT_SHARE = '0.70';

    private const TRANSACTION_ATTEMPTS = 10;

    public function registerPlay(Song $song): void
    {
        $recipientId = $song->album->artist->label ?? $song->album->artist->user_id;

        DB::connection('mongodb')->transaction(function () use ($song, $recipientId) {
            Song::where('_id', $song->getKey())->increment('plays');

            Royalty::where('recipient_id', $recipientId)->update([
                '$inc' => ['amount' => new Decimal128(self::ROYALTY_PER_PLAY)],
                '$set' => ['updated_at' => new UTCDateTime],
                '$setOnInsert' => ['last_payment' => null, 'created_at' => new UTCDateTime],
            ], ['upsert' => true]);
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function withdraw(User $user): bool
    {
        return DB::connection('mongodb')->transaction(function () use ($user) {
            $royalty = $this->emptyBalance($user);

            if ($royalty === null) {
                return false;
            }

            $total = $royalty->amount;
            $payout = bcadd(bcmul($total, self::PAYOUT_SHARE, 3), '0.005', 2);
            $commission = bcsub($total, $payout, 2);
            $royaltyId = $royalty->royalties_id;

            Expense::create([
                'amount' => $payout,
                'state' => 'pagado',
                'at' => now(),
                'from' => $royaltyId,
                'user_id' => $user->user_id,
            ]);

            Earning::create([
                'amount' => $commission,
                'state' => 'pagado',
                'at' => now(),
                'comision' => $royaltyId,
            ]);

            return true;
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function emptyBalance(User $user): ?Royalty
    {
        $now = new UTCDateTime;

        return Royalty::raw(fn (Collection $collection) => $collection->findOneAndUpdate(
            ['recipient_id' => $user->user_id, 'amount' => ['$gt' => new Decimal128('0')]],
            ['$set' => ['amount' => new Decimal128('0.00'), 'last_payment' => $now, 'updated_at' => $now]],
            [
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_BEFORE,
                'session' => DB::connection('mongodb')->getSession(),
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ],
        ));
    }
}
