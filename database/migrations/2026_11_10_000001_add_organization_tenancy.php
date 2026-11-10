<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('type', 30)->default('education');
            $table->string('timezone', 50)->default('Asia/Singapore');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });

        foreach (['kelas', 'quizzes', 'questions', 'quiz_sessions', 'attempts', 'participant_login_activities', 'ai_generations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->index()->constrained()->restrictOnDelete();
            });
        }

        $admin = DB::table('users')->where('role', 'admin')->orderBy('id')->first();
        $settings = Schema::hasTable('app_settings') ? DB::table('app_settings')->where('id', 1)->first() : null;
        $name = $settings?->organization_name ?: 'Organisasi Utama';
        $slug = Str::slug($name) ?: 'organisasi-utama';
        if (DB::table('organizations')->where('slug', $slug)->exists()) {
            $slug .= '-1';
        }

        $organizationId = DB::table('organizations')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'type' => 'education',
            'timezone' => 'Asia/Singapore',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->orderBy('id')->get()->each(function ($user) use ($organizationId) {
            DB::table('organization_memberships')->insert([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'role' => $user->role === 'admin' ? 'owner' : 'participant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($admin) {
            DB::table('users')->where('id', $admin->id)->update(['is_platform_admin' => true]);
        }

        foreach (['kelas', 'quizzes', 'questions', 'quiz_sessions', 'attempts', 'participant_login_activities', 'ai_generations'] as $tableName) {
            DB::table($tableName)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        }
    }

    public function down(): void
    {
        foreach (['ai_generations', 'participant_login_activities', 'attempts', 'quiz_sessions', 'questions', 'quizzes', 'kelas'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });

        Schema::dropIfExists('organization_memberships');
        Schema::dropIfExists('organizations');
    }
};
