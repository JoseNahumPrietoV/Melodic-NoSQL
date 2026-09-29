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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id('expense_id');
            $table->decimal('amount', 13, 2);
            $table->string('state')->default('pagado');
            $table->timestamp('at')->useCurrent();
            $table->foreignId('from')->nullable()->references('royalties_id')->on('royalties');
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'user_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
