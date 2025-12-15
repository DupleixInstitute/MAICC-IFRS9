<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  // ...
public function up(): void
{

    // 1. internal_grades_profiles (Unchanged from previous suggestion)
    Schema::create('internal_grade_profiles', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->text('description')->nullable();
        $table->unsignedSmallInteger('max_tenor_years')->default(5);
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    });

    // 2. internal_grade_mappings (Defining the core grades: a, b, c)
    Schema::create('internal_grade_mappings', function (Blueprint $table) {
        $table->id();
        $table->integer('profile_id'); // FK to internal_grades_profiles if needed
        $table->string('grade_code')->unique(); // e.g., 'A', 'B', 'C'
        $table->string('grade_name')->unique(); 
        $table->integer('upper_bound')->nullable(); // e.g., '1'
        $table->integer('lower_bound')->nullable(); // e.g., '20
        // You might still want to keep other relevant S&P/RBM/Ageing data here
        $table->string('rbm_class')->nullable(); 
        $table->string('sp_rating')->nullable();
        
        $table->timestamps();
    });

    // 3. grade_tenor_pd (Storing the term structure of PD)
    Schema::create('grade_tenor_pd', function (Blueprint $table) {
        $table->id();
        $table->foreignId('grade_mapping_id')
              ->constrained('internal_grade_mappings')
              ->onDelete('cascade');
        
        // Tenor (1, 2, 3, 4, 5 corresponding to y1, y2, y3, y4, y5)
        $table->unsignedSmallInteger('tenor_years'); 
        
        // Probability of Default (PD)
        $table->decimal('pd_probability', 8, 4); // Use 4 decimal places for precision

        // Ensure unique PD for a given grade and tenor
        $table->unique(['grade_mapping_id', 'tenor_years']); 
        
        $table->timestamps();
    });
    
    // NOTE: The asset_grade_history table from the previous answer is still
    // necessary to calculate the *Transition Matrix*. 
    // It should track the grade assigned to the asset at a snapshot date.
    // The PD calculation then uses this new `grade_tenor_pd` table.

}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_tenor_pd');
        Schema::dropIfExists('internal_grade_mappings');
        Schema::dropIfExists('internal_grades_profiles');
    }
};
