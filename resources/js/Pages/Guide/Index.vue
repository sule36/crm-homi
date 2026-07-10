<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const activeTab = ref('workflow');

const tabs = [
    { id: 'workflow', name: 'Alur Prospek & Sales', icon: '⚡' },
    { id: 'setup_meta', name: 'Integrasi Omnichannel & AI', icon: '🤖' },
    { id: 'operational', name: 'SPK & Bank Partner', icon: '🏗️' },
    { id: 'finance', name: 'Keuangan & RAB', icon: '💰' },
];

const faqs = ref([
    {
        q: 'Bagaimana cara mendaftarkan Webhook Facebook & Instagram di Meta Developer?',
        a: 'Masuk ke dashboard developer.facebook.com, buat aplikasi tipe Bisnis. Tambahkan produk Messenger dan Instagram. Pada bagian Webhooks, masukkan URL Webhook Meta Homi Anda (bisa dilihat di Pengaturan -> Omnichannel & AI) dan samakan Verify Token-nya. Subscribe ke topic "messages" dan "messaging_postbacks".',
        open: false
    },
    {
        q: 'Kenapa AI Autopilot tidak membalas chat pelanggan?',
        a: 'Pastikan tiga hal: 1) Anda sudah mengisi Gemini API Key di Pengaturan -> WhatsApp atau file .env. 2) Anda telah mengaktifkan switch toggle AI Autopilot untuk platform bersangkutan di Pengaturan -> Omnichannel & AI. 3) Token Akses Halaman atau Token Cloud API WhatsApp Anda masih aktif dan tidak expired.',
        open: false
    },
    {
        q: 'Di mana file SPK yang dihasilkan disimpan?',
        a: 'File SPK hasil booking disimpan secara lokal di dalam folder penyimpanan Laravel (storage). Anda dapat mengunduh atau melakukan *stream* file PDF resmi secara langsung dari panel detail Booking & KPR dengan mengklik tombol "SPK PDF".',
        open: false
    },
    {
        q: 'Apakah saya bisa membatasi kuota prospek (leads) yang diterima oleh agen?',
        a: 'Bisa. Buka menu Monitor Agen di sidebar. Di sana Anda dapat mengaktifkan/menonaktifkan pembagian otomatis leads untuk masing-masing agen, serta mengatur kapasitas maksimal leads yang bisa di-assign ke tiap agen secara real-time.',
        open: false
    }
]);

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    alert('Teks berhasil disalin ke clipboard!');
};
</script>

<template>
    <Head title="Panduan Penggunaan" />
    <CrmLayout>
        <template #breadcrumb>Panduan Penggunaan</template>

        <!-- HEADER BANNER (Premium Gradient Card with Micro-animations) -->
        <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-10 text-white shadow-2xl mb-8 border border-white/5">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.15),transparent)] pointer-events-none"></div>
            <div class="relative z-10 max-w-3xl space-y-3">
                <span class="inline-block px-3 py-1 bg-indigo-500/20 border border-indigo-400/20 text-indigo-300 text-[9px] font-black uppercase tracking-widest rounded-full">
                    📖 Pusat Bantuan & Manual
                </span>
                <h1 class="text-2xl sm:text-4xl font-black tracking-tight leading-tight">
                    Homi Developer <span class="bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">Help Center</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-350 leading-relaxed font-medium">
                    Panduan lengkap alur kerja sistem CRM, tata cara setup integrasi omnichannel, otomasi AI autopilot marketing, hingga pengelolaan dana operasional dan RAB proyek.
                </p>
            </div>
        </div>

        <!-- TABS SELECTOR -->
        <!-- Mobile (Dropdown) -->
        <div class="block md:hidden mb-6">
            <label class="block text-[10px] font-black text-slate-450 uppercase mb-2">Pilih Topik Panduan:</label>
            <div class="relative">
                <select v-model="activeTab" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-xs font-black uppercase tracking-wider focus:ring-2 focus:ring-blue-500/20 cursor-pointer appearance-none pr-10">
                    <option v-for="tab in tabs" :key="tab.id" :value="tab.id">
                        {{ tab.icon }} &nbsp;&nbsp; {{ tab.name }}
                    </option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            </div>
        </div>

        <!-- Desktop (Horizontal Row) -->
        <div class="hidden md:flex overflow-x-auto whitespace-nowrap bg-slate-100 p-1.5 rounded-2xl mb-8 w-full max-w-full gap-1 select-none shadow-inner border border-slate-200/40">
            <button v-for="tab in tabs" :key="tab.id"
                @click="activeTab = tab.id"
                :class="activeTab === tab.id ? 'bg-white text-slate-900 shadow-md font-bold' : 'text-slate-500 hover:text-slate-700 font-semibold'"
                class="px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest transition-all flex items-center space-x-2 shrink-0">
                <span>{{ tab.icon }}</span>
                <span>{{ tab.name }}</span>
            </button>
        </div>

        <!-- MAIN CONTENT AREA -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <!-- LEFT CONTAINER: TABS DETAIL CONTENT -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- TAB: WORKFLOW -->
                <div v-if="activeTab === 'workflow'" class="space-y-6">
                    <!-- Workflow Intro -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 border-b border-slate-50 pb-3">⚡ Alur Kerja Siklus Prospek (Leads Journey)</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium">
                            Sistem Homi CRM dirancang khusus untuk meminimalkan waktu respon dan memaksimalkan rasio konversi leads menjadi closing unit perumahan. Berikut adalah 5 tahapan utama siklus leads:
                        </p>

                        <!-- Timeline Workflow Cards -->
                        <div class="relative border-l-2 border-slate-100 pl-6 ml-3 space-y-6 py-2">
                            <!-- Step 1 -->
                            <div class="relative">
                                <span class="absolute -left-[35px] top-0 w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm">1</span>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">Penerimaan Leads (Lead Gen Webhook)</h4>
                                <p class="text-[11px] text-slate-450 mt-1 leading-relaxed">
                                    Pencari rumah mengisi iklan di Google Lead Form, Facebook Lead Ads, atau formulir di Landing Page website Anda. Data dikirim instan lewat Webhook API ke Homi CRM.
                                </p>
                            </div>
                            <!-- Step 2 -->
                            <div class="relative">
                                <span class="absolute -left-[35px] top-0 w-6 h-6 bg-pink-100 text-pink-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm">2</span>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">Pembagian Otomatis Agen (Auto-Assign)</h4>
                                <p class="text-[11px] text-slate-450 mt-1 leading-relaxed">
                                    Leads baru secara real-time didistribusikan ke agen sales yang sedang aktif (berstatus ON) menggunakan skema antrean adil, tanpa melebihi kapasitas limit leads mereka.
                                </p>
                            </div>
                            <!-- Step 3 -->
                            <div class="relative">
                                <span class="absolute -left-[35px] top-0 w-6 h-6 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm">3</span>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">AI Autopilot & Chat Omnichannel</h4>
                                <p class="text-[11px] text-slate-450 mt-1 leading-relaxed">
                                    Pesan sambutan langsung terkirim otomatis. Jika AI Autopilot aktif, chatbot pintar Gemini akan menanggapi pertanyaan seputar lokasi, harga, promosi, dan spesifikasi unit perumahan.
                                </p>
                            </div>
                            <!-- Step 4 -->
                            <div class="relative">
                                <span class="absolute -left-[35px] top-0 w-6 h-6 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm">4</span>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">Site Visit (Kunjungan Proyek)</h4>
                                <p class="text-[11px] text-slate-450 mt-1 leading-relaxed">
                                    AI atau sales membujuk prospek untuk membuat janji kunjungan lokasi (Site Visit) di akhir pekan. Agen sales mencatat log aktivitas "Visit" dan mengatur pengingat follow-up lanjutan.
                                </p>
                            </div>
                            <!-- Step 5 -->
                            <div class="relative">
                                <span class="absolute -left-[35px] top-0 w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-xs font-black shadow-sm">5</span>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-wide">Booking & Closing SPK</h4>
                                <p class="text-[11px] text-slate-450 mt-1 leading-relaxed">
                                    Prospek memilih unit rumah, membayar Tanda Jadi (UTJ), dan menyerahkan dokumen KPR. Admin melakukan persetujuan booking dan menerbitkan berkas PDF SPK resmi dari sistem.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: SETUP META -->
                <div v-if="activeTab === 'setup_meta'" class="space-y-6">
                    <!-- Webhook Setup Guide -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 border-b border-slate-50 pb-3">🤖 Konfigurasi Integrasi Omnichannel (FB / IG / WA)</h3>
                        
                        <div class="space-y-4">
                            <!-- Section: Meta Webhook -->
                            <div class="space-y-2">
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[9px] font-black uppercase rounded">Langkah 1: Hubungkan Webhook Messenger & Instagram</span>
                                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                                    Meta Developer Console memerlukan URL Webhook Homi Anda untuk meneruskan pesan chat. Buka menu **Pengaturan** -> **Omnichannel & AI** untuk melihat URL Anda. Nilai bawaan sistem:
                                </p>
                                <div class="flex items-center gap-2">
                                    <code class="flex-1 bg-slate-50 p-3 rounded-xl border border-slate-150 text-xs font-mono text-indigo-600 select-all">
                                        https://crm.homi.id/api/webhooks/meta-messaging
                                    </code>
                                    <button @click="copyToClipboard('https://crm.homi.id/api/webhooks/meta-messaging')" class="px-3 py-3 bg-slate-950 text-white rounded-xl text-[10px] font-black uppercase tracking-wider shrink-0 hover:bg-slate-800 transition-colors">
                                        Copy URL
                                    </button>
                                </div>
                            </div>

                            <!-- Section: Verify Token -->
                            <div class="space-y-2 pt-2">
                                <span class="px-2 py-0.5 bg-purple-50 text-purple-600 text-[9px] font-black uppercase rounded">Langkah 2: Tentukan Verify Token Webhook</span>
                                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                                    Tulis kunci verifikasi rahasia acak di tab **Omnichannel & AI** (misal: `homi_meta_secret_key`), lalu masukkan token rahasia yang sama di kolom "Verify Token" pada dashboard Meta Developers saat memverifikasi callback URL.
                                </p>
                            </div>

                            <!-- Section: Access Token -->
                            <div class="space-y-2 pt-2">
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase rounded">Langkah 3: Ambil Page Access Token</span>
                                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                                    Pada dashboard Meta Developers, pilih Halaman Facebook yang ingin dihubungkan, lalu generate **Page Access Token**. Salin token tersebut dan tempelkan ke kolom **Meta Page Access Token** di dashboard CRM Anda. Token ini wajib memiliki izin:
                                </p>
                                <ul class="list-disc pl-5 text-[11px] text-slate-450 space-y-1 font-bold">
                                    <li>`pages_messaging` (Membaca/membalas chat Facebook Messenger)</li>
                                    <li>`instagram_manage_messages` (Membaca/membalas chat Instagram Direct)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- AI Autopilot Overview -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-emerald-600 border-b border-slate-50 pb-3">🤖 Aturan AI Autopilot Gemini</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium">
                            Model AI Gemini bertindak sebagai asisten marketing otomatis yang merespon chat pelanggan secara cepat. Berikut adalah prinsip kerja Gemini AI Autopilot:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                                <h5 class="text-[10px] font-black text-slate-800 uppercase">🎯 Tujuan Percakapan</h5>
                                <p class="text-[10px] text-slate-500 leading-relaxed font-medium">
                                    Mengarahkan prospek secara persuasif agar setuju melakukan **kunjungan proyek (Site Visit)** atau membayarkan **Booking Fee (Tanda Jadi UTJ)** senilai Rp 5.000.000.
                                </p>
                            </div>
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                                <h5 class="text-[10px] font-black text-slate-800 uppercase">💬 Metode Pertanyaan</h5>
                                <p class="text-[10px] text-slate-500 leading-relaxed font-medium">
                                    Di akhir setiap tanggapan, AI wajib menyisipkan **pertanyaan terbuka** yang ramah guna memicu prospek membalas pesan lebih lanjut.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: OPERATIONAL -->
                <div v-if="activeTab === 'operational'" class="space-y-6">
                    <!-- SPK Customization Guide -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-pink-600 border-b border-slate-50 pb-3">🏗️ Pembuatan Dokumen SPK & Unit Rumah</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium">
                            Dokumen **Surat Pemesanan Rumah (SPK)** dihasilkan secara otomatis oleh sistem saat admin menyetujui transaksi Booking.
                        </p>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <span class="text-base mt-0.5">🏢</span>
                                <div class="text-xs">
                                    <p class="font-black text-slate-900">Identitas Header Dokumen</p>
                                    <p class="text-slate-500 mt-1">Ubah nama developer, alamat kantor, email, dan logo resmi di tab **Template SPK** pada halaman Pengaturan. Logo yang diunggah akan langsung muncul di kop surat PDF SPK.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 pt-2">
                                <span class="text-base mt-0.5">💰</span>
                                <div class="text-xs">
                                    <p class="font-black text-slate-900">Partner Bank & Simulasi KPR</p>
                                    <p class="text-slate-500 mt-1">Gunakan tab **Bank Partner** untuk mendaftarkan bank-bank resmi yang bekerja sama dengan developer Anda. Skema bunga (fixed, float, atau syariah flat) yang didaftarkan di sini akan langsung digunakan oleh kalkulator KPR pada menu chat.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: FINANCE -->
                <div v-if="activeTab === 'finance'" class="space-y-6">
                    <!-- General Cash & Financial Ledger -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-emerald-600 border-b border-slate-50 pb-3">💰 Panduan Modul Keuangan & RAB Proyek</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-medium">
                            Modul keuangan mengelola seluruh transaksi kas operasional kantor, penggajian karyawan, hingga Rencana Anggaran Biaya pembangunan rumah.
                        </p>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                                <h5 class="text-[11px] font-black text-slate-900 uppercase">💳 Kas & Transaksi</h5>
                                <p class="text-[10px] text-slate-500 leading-relaxed">
                                    Uang muka booking perumahan (UTJ) dari pembeli secara otomatis masuk sebagai pemasukan kas operasional di menu **Kas & Pembayaran** setelah admin memverifikasi transaksi.
                                </p>
                            </div>
                            <div class="p-5 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                                <h5 class="text-[11px] font-black text-slate-900 uppercase">🏗️ Realisasi RAB Proyek</h5>
                                <p class="text-[10px] text-slate-500 leading-relaxed">
                                    Gunakan menu **RAB Proyek** untuk menganggarkan material, upah tukang, dan infrastruktur. Realisasikan dana RAB untuk langsung mencatat pengeluaran keuangan kas perumahan secara otomatis.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ SECTION -->
                <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 border-b border-slate-50 pb-3">❓ Pertanyaan Sering Diajukan (FAQ)</h3>
                    
                    <div class="divide-y divide-slate-100">
                        <div v-for="(faq, index) in faqs" :key="index" class="py-3.5">
                            <button @click="faq.open = !faq.open" class="flex justify-between items-center w-full text-left text-xs font-black text-slate-800 hover:text-indigo-600 transition-colors">
                                <span>{{ faq.q }}</span>
                                <span class="text-slate-400 font-bold ml-2">{{ faq.open ? '▲' : '▼' }}</span>
                            </button>
                            <p v-if="faq.open" class="text-[11px] text-slate-500 leading-relaxed font-semibold mt-2.5 bg-slate-50/50 p-3.5 rounded-xl border border-slate-100/50">
                                {{ faq.a }}
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- RIGHT CONTAINER: SIDEBAR QUICK TIPS (Outfit Aesthetic) -->
            <div class="space-y-6">
                <!-- Quick Tips Card -->
                <div class="bg-slate-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(99,102,241,0.1),transparent)] pointer-events-none"></div>
                    <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-xl mb-6 select-none">💡</div>
                    <h4 class="font-black mb-2 uppercase tracking-widest text-xs text-indigo-400">💡 Tips Marketing Utama</h4>
                    <p class="text-xs text-slate-350 leading-relaxed font-medium">
                        Kecepatan merespon chat leads berpengaruh besar terhadap keputusan mereka membeli rumah. Selalu nyalakan **AI Autopilot** pada jam-jam luar kerja (malam hari/akhir pekan) agar prospek Anda tidak lepas ke developer kompetitor!
                    </p>
                </div>

                <!-- System Integration Audit Info -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-sm space-y-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-xl select-none">🛡️</div>
                    <h4 class="font-black uppercase tracking-widest text-xs text-slate-800">Keamanan Data</h4>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        Semua perubahan pengaturan sensitif seperti Webhook Key, Verify Token, dan token akses dicatat ke dalam modul Audit Log untuk menjamin ketertelusuran sistem oleh owner.
                    </p>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
