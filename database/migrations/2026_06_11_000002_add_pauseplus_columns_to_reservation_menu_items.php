<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            // Ernährungs-/Filter-Flags
            $table->boolean('is_vegetarian')->default(false)->after('available');
            $table->boolean('is_vegan')->default(false)->after('is_vegetarian');
            $table->boolean('is_alcoholic')->default(false)->after('is_vegan');

            // Vier-Augen-Freigabe: draft → review → approved
            $table->string('approval_status')->default('draft')->after('is_alcoholic');
            $table->foreignId('submitted_by')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('submitted_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            // Produktbild (1:1) – ContextFile aus platform-core, bewusst ohne FK
            $table->unsignedBigInteger('image_context_file_id')->nullable()->after('approved_at');

            $table->index(['team_id', 'approval_status']);
            $table->index('image_context_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_menu_items', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'approval_status']);
            $table->dropIndex(['image_context_file_id']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'is_vegetarian', 'is_vegan', 'is_alcoholic',
                'approval_status', 'approved_at', 'image_context_file_id',
            ]);
        });
    }
};
