<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chit_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scheme_code')->unique();
            $table->decimal('chit_value', 15, 2); 
            $table->integer('total_members');       
            $table->integer('duration_months');     
            $table->decimal('installment_amount', 15, 2); 
            $table->decimal('commission_pct', 5, 2)->default(5.00); 
            $table->decimal('commission_amount', 15, 2);
            $table->enum('auction_type', ['open', 'closed'])->default('open');
            $table->enum('scheme_type', [
                'fixed',
                'auction',
                'flexible',
                'fixed_return',
                'daily_weekly',
                'group_based',
            ])->default('auction');
            $table->enum('installment_frequency', ['monthly', 'weekly', 'daily'])->default('monthly');
            $table->decimal('fixed_return_amount', 15, 2)->nullable();
            $table->boolean('is_private')->default(false);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chit_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_code')->unique();
            $table->unsignedBigInteger('scheme_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('current_month')->default(0);
            $table->integer('total_months');
            $table->decimal('chit_value', 15, 2);
            $table->integer('total_members');
            $table->decimal('installment_amount', 15, 2);
            $table->decimal('commission_pct', 5, 2)->default(5.00);
            $table->enum('auction_type', ['open', 'closed'])->default('open');
            $table->enum('scheme_type', [
                'fixed',
                'auction',
                'flexible',
                'fixed_return',
                'daily_weekly',
                'group_based',
            ])->default('auction');
            $table->enum('installment_frequency', ['monthly', 'weekly', 'daily'])->default('monthly');
            $table->decimal('fixed_return_amount', 15, 2)->nullable();
            $table->boolean('is_private')->default(false);
            $table->unsignedBigInteger('group_leader_id')->nullable();
            $table->enum('status', ['forming', 'active', 'completed', 'terminated'])->default('forming');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('scheme_id')->references('id')->on('chit_schemes');
            $table->foreign('group_leader_id')->references('id')->on('clients')->nullOnDelete();
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('client_id');
            $table->integer('member_number'); 
            $table->enum('status', ['applied', 'approved', 'active', 'defaulted', 'completed', 'withdrawn'])->default('applied');
            $table->date('joined_date')->nullable();
            $table->boolean('has_won_auction')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->string('signed_agreement')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'client_id']);
            $table->unique(['group_id', 'member_number']);
            $table->foreign('group_id')->references('id')->on('chit_groups')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients');
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('member_id'); 
            $table->integer('month_number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue', 'waived'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->string('payment_mode')->nullable(); 
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'month_number']);
            $table->index(['member_id', 'status']);
            $table->foreign('group_id')->references('id')->on('chit_groups')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('group_members')->onDelete('cascade');
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->integer('month_number');
            $table->date('auction_date');
            $table->time('auction_time')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->enum('bid_type', ['open', 'closed'])->default('open');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('location')->nullable();
            $table->decimal('min_bid', 15, 2)->nullable();
            $table->decimal('max_bid', 15, 2)->nullable();
            $table->decimal('winning_bid', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->nullable(); 
            $table->unsignedBigInteger('winner_member_id')->nullable();
            $table->enum('status', ['scheduled', 'open', 'completed', 'cancelled'])->default('scheduled');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('conducted_by')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'month_number']);
            $table->foreign('group_id')->references('id')->on('chit_groups')->onDelete('cascade');
            $table->foreign('winner_member_id')->references('id')->on('group_members')->nullOnDelete();
        });

        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('member_id'); 
            $table->decimal('bid_amount', 15, 2);
            $table->boolean('is_winner')->default(false);
            $table->timestamps();

            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('group_members')->onDelete('cascade');
        });

        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('auction_id');
            $table->integer('month_number');
            $table->decimal('chit_value', 15, 2);
            $table->decimal('discount', 15, 2);        
            $table->decimal('commission_amount', 15, 2); 
            $table->decimal('net_dividend', 15, 2);     
            $table->decimal('per_member_dividend', 15, 2); 
            $table->enum('status', ['pending', 'processed', 'distributed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('chit_groups')->onDelete('cascade');
            $table->foreign('auction_id')->references('id')->on('auctions')->onDelete('cascade');
        });

        Schema::create('dividend_distributions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dividend_id');
            $table->unsignedBigInteger('member_id');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'paid', 'adjusted'])->default('pending');
            $table->string('payment_mode')->nullable();
            $table->string('reference_no')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('dividend_id')->references('id')->on('dividends')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('group_members')->onDelete('cascade');
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_code')->unique();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('auction_id');
            $table->unsignedBigInteger('winner_member_id');
            $table->decimal('chit_value', 15, 2);
            $table->decimal('winning_bid', 15, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->decimal('payout_amount', 15, 2);  
            $table->enum('payment_mode', ['cash', 'bank_transfer', 'upi', 'cheque'])->default('bank_transfer');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('reference_no')->nullable();
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('chit_groups');
            $table->foreign('auction_id')->references('id')->on('auctions');
            $table->foreign('winner_member_id')->references('id')->on('group_members');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('dividend_distributions');
        Schema::dropIfExists('dividends');
        Schema::dropIfExists('auction_bids');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('chit_groups');
        Schema::dropIfExists('chit_schemes');
    }
};
