<?php

namespace Database\Seeders;

use App\Models\OnboardingQuestion;
use Illuminate\Database\Seeder;

class OnboardingQuestionSeeder extends Seeder
{
    /**
     * ~55 questions per docs/01-PRD.md FR-ONB-02 and wireframe/onboarding.md's
     * category/step outline. Content is authored here (the wireframe only
     * sketches step ranges, not literal question text).
     */
    public function run(): void
    {
        $step = 0;
        $scale = ['min' => 1, 'max' => 5];

        $questions = [
            // Identity
            ['identity', 'Siapa nama lengkap kamu?', 'text', null, true],
            ['identity', 'Apa jenis kelamin kamu?', 'single_choice', ['Laki-laki', 'Perempuan'], true],
            ['identity', 'Kapan tanggal lahir kamu?', 'date', null, true],
            ['identity', 'Berapa nomor HP kamu?', 'text', null, false],
            ['identity', 'Bahasa yang kamu inginkan untuk aplikasi ini?', 'single_choice', ['Bahasa Indonesia', 'English'], true],

            // Body
            ['body', 'Berapa tinggi badan kamu? (cm)', 'number', null, true],
            ['body', 'Berapa berat badan kamu sekarang? (kg)', 'number', null, true],
            ['body', 'Berapa lingkar pinggang kamu? (cm)', 'number', null, true],
            ['body', 'Berapa target berat badan kamu? (kg)', 'number', null, true],
            ['body', 'Dalam berapa minggu kamu ingin mencapai target itu?', 'number', null, true],

            // Lifestyle
            ['lifestyle', 'Seberapa aktif kegiatan harianmu?', 'single_choice', ['Duduk terus', 'Ringan', 'Sedang', 'Berat'], true],
            ['lifestyle', 'Jam berapa biasanya kamu tidur?', 'time', null, true],
            ['lifestyle', 'Jam berapa biasanya kamu bangun?', 'time', null, true],
            ['lifestyle', 'Berapa jam kerja/aktivitas kamu per hari?', 'number', null, true],
            ['lifestyle', 'Bagaimana pola makan kamu sehari-hari?', 'single_choice', ['Tidak teratur', 'Teratur 3x sehari', 'Sering ngemil', 'Diet khusus (vegetarian/vegan/dll)'], true],
            ['lifestyle', 'Seberapa sering kamu minum minuman manis?', 'single_choice', ['Tidak pernah', 'Jarang', 'Sering', 'Setiap hari'], true],
            ['lifestyle', 'Apakah kamu merokok?', 'single_choice', ['Tidak pernah', 'Sudah berhenti', 'Kadang-kadang', 'Rutin'], true],
            ['lifestyle', 'Seberapa sering kamu minum minuman beralkohol?', 'single_choice', ['Tidak pernah', 'Jarang', 'Sering', 'Setiap minggu'], true],
            ['lifestyle', 'Berapa kali kamu olahraga dalam seminggu?', 'single_choice', ['Tidak pernah', '1-2 kali', '3-4 kali', '5+ kali'], true],
            ['lifestyle', 'Kapan waktu yang kamu suka untuk olahraga?', 'single_choice', ['Pagi', 'Siang', 'Sore', 'Malam'], true],

            // Medical
            ['medical', 'Apakah kamu memiliki salah satu kondisi kesehatan berikut?', 'multi_choice', ['Diabetes Melitus Tipe 2', 'Hipertensi', 'Kolesterol Tinggi', 'Asam Urat', 'Tukak Lambung/GERD', 'Tidak ada'], true],
            ['medical', 'Sejak kapan kondisi tersebut didiagnosis? (jika ada)', 'text', null, false],
            ['medical', 'Bagaimana kondisi tersebut biasanya kamu kelola?', 'single_choice', ['Obat rutin', 'Kontrol dokter berkala', 'Perubahan gaya hidup saja', 'Belum dikelola'], false],
            ['medical', 'Apakah kamu sedang mengonsumsi obat-obatan rutin?', 'single_choice', ['Ya', 'Tidak'], true],
            ['medical', 'Sebutkan obat-obatan yang kamu konsumsi rutin (jika ada)', 'text', null, false, ['repeatable' => true, 'fields' => [['key' => 'name', 'label' => 'Nama obat'], ['key' => 'dosage', 'label' => 'Dosis']]]],
            ['medical', 'Apakah kamu memiliki alergi makanan atau obat?', 'single_choice', ['Ya', 'Tidak'], true],
            ['medical', 'Sebutkan alergi yang kamu miliki (jika ada)', 'text', null, false, ['repeatable' => true, 'fields' => [['key' => 'allergen', 'label' => 'Alergen'], ['key' => 'severity', 'label' => 'Tingkat keparahan']]]],
            ['medical', 'Apakah ada riwayat penyakit dalam keluarga (orang tua/saudara)?', 'multi_choice', ['Diabetes', 'Hipertensi', 'Kolesterol', 'Jantung', 'Tidak ada'], false],
            ['medical', 'Apakah kamu pernah menjalani operasi besar?', 'single_choice', ['Ya', 'Tidak'], false],
            ['medical', 'Apakah ada pantangan makanan karena alasan agama/kepercayaan?', 'single_choice', ['Halal', 'Vegetarian', 'Vegan', 'Tidak ada'], false],

            // Preferences
            ['preferences', 'Makanan apa yang paling kamu sukai?', 'multi_choice', ['Nasi', 'Ayam', 'Ikan', 'Daging sapi', 'Tahu/Tempe', 'Sayuran', 'Buah'], false],
            ['preferences', 'Makanan apa yang tidak kamu sukai atau hindari?', 'text', null, false],
            ['preferences', 'Masakan daerah apa yang paling kamu suka?', 'multi_choice', ['Jawa', 'Sunda', 'Padang', 'Manado', 'Bali', 'Internasional'], false],
            ['preferences', 'Alat olahraga apa yang kamu punya di rumah?', 'multi_choice', ['Tidak ada', 'Matras', 'Dumbbell', 'Treadmill', 'Resistance band', 'Sepeda statis'], false],
            ['preferences', 'Program apa yang paling menarik buat kamu?', 'single_choice', ['Diet & Transformasi 90 Hari', 'Bulking/Menambah massa otot', 'Marathon/Lari', 'Manajemen penyakit kronis', 'Intermittent fasting'], true],
            ['preferences', 'Berapa kali kamu makan dalam sehari biasanya?', 'number', null, true],
            ['preferences', 'Apakah kamu suka memasak sendiri atau lebih sering makan di luar?', 'single_choice', ['Masak sendiri', 'Makan di luar', 'Campuran'], false],
            ['preferences', 'Apakah kamu punya anggaran khusus untuk makanan sehat per hari?', 'single_choice', ['<Rp30.000', 'Rp30.000-Rp60.000', 'Rp60.000-Rp100.000', '>Rp100.000'], false],
            ['preferences', 'Seberapa penting rasa dibanding nutrisi buat kamu?', 'scale', $scale, false],
            ['preferences', 'Seberapa siap kamu mengubah kebiasaan makan?', 'scale', $scale, true],

            // Goal
            ['goal', 'Kenapa kamu ingin memulai program ini sekarang?', 'multi_choice', ['Kesehatan', 'Penampilan', 'Kepercayaan diri', 'Anjuran dokter', 'Persiapan acara penting'], true],
            ['goal', 'Ceritakan singkat motivasi kamu', 'text', null, false],
            ['goal', 'Apa hambatan terbesar yang pernah kamu alami saat mencoba diet/olahraga?', 'single_choice', ['Kurang waktu', 'Kurang motivasi', 'Tidak tahu caranya', 'Mudah menyerah', 'Lingkungan tidak mendukung'], false],
            ['goal', 'Seberapa yakin kamu bisa konsisten dengan program ini?', 'scale', $scale, true],
            ['goal', 'Jam berapa kamu ingin diingatkan minum air?', 'time', null, false],
            ['goal', 'Jam berapa kamu ingin diingatkan waktu makan?', 'time', null, false],
            ['goal', 'Jam berapa kamu ingin diingatkan waktu olahraga?', 'time', null, false],
            ['goal', 'Jam berapa kamu ingin diingatkan check-in harian?', 'time', null, false],
            ['goal', 'Lewat mana kamu ingin menerima notifikasi?', 'multi_choice', ['Dalam aplikasi', 'Email', 'WhatsApp (segera hadir)'], true],
            ['goal', 'Apakah kamu ingin didampingi seorang Coach?', 'single_choice', ['Ya', 'Tidak', 'Belum yakin'], true],
            ['goal', 'Berapa kali seminggu kamu ingin sesi dengan Coach? (jika ya)', 'single_choice', ['Tidak perlu', '1x', '2-3x', 'Sesuai kebutuhan'], false],
            ['goal', 'Apakah kamu ingin bergabung dengan grup komunitas?', 'single_choice', ['Ya', 'Tidak'], false],
            ['goal', 'Bagaimana kamu ingin progres kamu dirayakan?', 'multi_choice', ['Badge/achievement', 'Pesan motivasi', 'Laporan mingguan', 'Tidak perlu'], false],
            ['goal', 'Apa target akhir yang ingin kamu capai dalam 90 hari?', 'text', null, true],
            ['goal', 'Siap untuk memulai perjalanan 90 hari kamu?', 'single_choice', ['Siap!'], true],
        ];

        foreach ($questions as $question) {
            [$category, $text, $inputType, $options, $required] = $question;
            $validationRules = $question[5] ?? null;
            $step++;

            OnboardingQuestion::updateOrCreate(
                ['step' => $step],
                [
                    'category' => $category,
                    'question_text' => $text,
                    'input_type' => $inputType,
                    'options' => $options,
                    'validation_rules' => $validationRules,
                    'is_required' => $required,
                    'order' => $step,
                    'is_active' => true,
                ]
            );
        }
    }
}
