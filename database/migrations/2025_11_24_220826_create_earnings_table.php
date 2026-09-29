<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->id('earning_id');

            $table->decimal('amount', 13, 2);

            $table->string('state')->default('pagado');

            $table->timestamp('at')->useCurrent();

            $table->foreignId('subscription')->nullable()->references('id')->on('subscriptions');

            $table->foreignId('comision')->nullable()->references('royalties_id')->on('royalties');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
