<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_types', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_types', 'slug')) {
                $table->string('slug', 120)->nullable()->after('name');
            }
            if (! Schema::hasColumn('loan_types', 'seo_title')) {
                $table->string('seo_title', 255)->nullable();
            }
            if (! Schema::hasColumn('loan_types', 'seo_description')) {
                $table->text('seo_description')->nullable();
            }
            if (! Schema::hasColumn('loan_types', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('loan_types', 'show_on_website')) {
                $table->boolean('show_on_website')->default(true);
            }
            if (! Schema::hasColumn('loan_types', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
        });

        Schema::table('loan_products', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_products', 'web_slug')) {
                $table->string('web_slug', 160)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'short_description')) {
                $table->string('short_description', 500)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'web_description')) {
                $table->longText('web_description')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'show_on_website')) {
                $table->boolean('show_on_website')->default(true);
            }
            if (! Schema::hasColumn('loan_products', 'is_featured')) {
                $table->boolean('is_featured')->default(false);
            }
            if (! Schema::hasColumn('loan_products', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0);
            }
            if (! Schema::hasColumn('loan_products', 'seo_title')) {
                $table->string('seo_title', 255)->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'seo_description')) {
                $table->text('seo_description')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'features_json')) {
                $table->json('features_json')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'benefits_json')) {
                $table->json('benefits_json')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'documents_json')) {
                $table->json('documents_json')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'min_age')) {
                $table->unsignedTinyInteger('min_age')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'max_age')) {
                $table->unsignedTinyInteger('max_age')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'min_monthly_income')) {
                $table->unsignedInteger('min_monthly_income')->nullable();
            }
            if (! Schema::hasColumn('loan_products', 'online_application_enabled')) {
                $table->boolean('online_application_enabled')->default(true);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        Schema::create('website_banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 32)->default('home_hero');
            $table->string('title', 255);
            $table->string('subtitle', 500)->nullable();
            $table->string('cta_text', 64)->nullable();
            $table->string('cta_url', 512)->nullable();
            $table->string('image_path', 512)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('website_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 64)->default('general');
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('website_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('role', 120)->nullable();
            $table->string('location', 120)->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('avatar_path', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('website_contact_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20);
            $table->string('subject', 255)->nullable();
            $table->text('message');
            $table->string('status', 32)->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('website_lead_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_ref', 32)->unique();
            $table->foreignId('loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('applicant_name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20);
            $table->string('pan_number', 16)->nullable();
            $table->unsignedInteger('requested_amount')->nullable();
            $table->unsignedSmallInteger('requested_tenure_months')->nullable();
            $table->string('employment_type', 64)->nullable();
            $table->unsignedInteger('monthly_income')->nullable();
            $table->string('city', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('new');
            $table->json('form_data')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_lead_applications');
        Schema::dropIfExists('website_contact_enquiries');
        Schema::dropIfExists('website_testimonials');
        Schema::dropIfExists('website_faqs');
        Schema::dropIfExists('website_banners');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });

        foreach (['loan_products', 'loan_types'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $cols = $tableName === 'loan_products'
                    ? ['web_slug', 'short_description', 'web_description', 'show_on_website', 'is_featured', 'sort_order', 'seo_title', 'seo_description', 'features_json', 'benefits_json', 'documents_json', 'min_age', 'max_age', 'min_monthly_income', 'online_application_enabled']
                    : ['slug', 'seo_title', 'seo_description', 'sort_order', 'show_on_website', 'is_featured'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn($tableName, $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
