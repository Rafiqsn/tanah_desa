<?php
// database/migrations/2025_09_14_000200_create_tanah_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tanah', function (Blueprint $table) {
            $table->id();

            // Identitas bidang
            $table->string('nomor_urut', 64)->unique();
            $table->foreignId('warga_id')->nullable()
                  ->constrained('warga')->nullOnDelete();

            // Jumlah luas bidang
            $table->decimal('jumlah_m2', 14, 2)->nullable();

            // A. Status Hak Tanah – Bersertifikat
            $table->decimal('hm', 14, 2)->default(0);
            $table->decimal('hgb', 14, 2)->default(0);
            $table->decimal('hp', 14, 2)->default(0);
            $table->decimal('hgu', 14, 2)->default(0);
            $table->decimal('hpl', 14, 2)->default(0);

            // B. Status Hak Tanah – Belum Bersertifikat
            $table->decimal('ma', 14, 2)->default(0);
            $table->decimal('vi', 14, 2)->default(0);
            $table->decimal('tn', 14, 2)->default(0);

            // C. Penggunaan Tanah – Non Pertanian
            $table->decimal('perumahan', 14, 2)->default(0);
            $table->decimal('perdagangan_jasa', 14, 2)->default(0);
            $table->decimal('perkantoran', 14, 2)->default(0);
            $table->decimal('industri', 14, 2)->default(0);
            $table->decimal('fasilitas_umum', 14, 2)->default(0);

            // D. Penggunaan Tanah – Pertanian
            $table->decimal('sawah', 14, 2)->default(0);
            $table->decimal('tegalan', 14, 2)->default(0);
            $table->decimal('perkebunan', 14, 2)->default(0);
            $table->decimal('peternakan_perikanan', 14, 2)->default(0);
            $table->decimal('hutan_belukar', 14, 2)->default(0);
            $table->decimal('hutan_lindung', 14, 2)->default(0);
            $table->decimal('mutasi_tanah', 14, 2)->default(0);
            $table->decimal('lain_lain', 14, 2)->default(0);
            $table->decimal('tanah_kosong', 14, 2)->default(0);

            // E. Kolom tambahan
            $table->text('keterangan')->nullable();

            $table->timestamps();

            // Index untuk pencarian
            $table->index(['warga_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('tanah');
    }
};
