<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePresensiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->dateTime('checktime');
            $table->string('checktype');
            $table->string('verifycode');
            $table->string('sensorid');
            $table->string('memoinfo')->nullable();
            $table->string('workcode');
            $table->string('sn');
            $table->string('userextfmt');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('presensi');
    }
}
