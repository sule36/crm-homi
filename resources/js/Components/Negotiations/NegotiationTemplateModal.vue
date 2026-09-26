<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    lead: { type: Object, default: null },
    leads: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    negotiation: { type: Object, default: null },
    initialTab: { type: String, default: 'page1' },
});

const emit = defineEmits(['close', 'saved']);
const page = usePage();

const activeTab = ref('page1'); // 'page1', 'page2', 'preview'
const showResultModal = ref(false);
const resultLink = ref('');
const resultToken = ref('');
const resultId = ref(null);

// Default templates matching the uploaded PDF
const defaultPengajuanNotes = [
    "Harga yang diajukan sudah termasuk PPN, AJB, BPHTB, dan biaya balik nama SHM atas nama pembeli. Ketentuan ini berlaku tanpa dipengaruhi status insentif PPN DTP pemerintah pada saat serah terima",
    "Serah terima unit paling lambat 10 (Sepuluh) bulan kalender sejak pembayaran DP. Keterlambatan dikenakan denda 1‰ (satu permil) per hari dari jumlah yang telah dibayarkan pembeli.",
    "Spesifikasi teknis bangunan (merek, tipe, dan ukuran material) dilampirkan dan menjadi bagian tidak terpisahkan dari PPJB.",
    "PBG dan sertifikat induk dalam kondisi bebas hak tanggungan ditunjukkan kepada pembeli sebelum pembayaran DP dilakukan.",
    "Free legalitas (PPN, AJB, BPHTB, SHM) dan bonus (canopy carport, smart door lock) tetap berlaku atas harga yang disetujui.",
    "Luas tanah pada form tertulis 105 m². Mohon konfirmasi angka final sesuai gambar kavling dan hasil pengukuran, untuk dicantumkan dalam PPJB.",
    "Reservasi sebesar Rp 10.000.000,- dikembalikan penuh apabila pengajuan ini tidak disetujui atau persyaratan legalitas tidak terpenuhi."
];

const defaultJawabanNotes = [
    "Booking Fee sebesar Rp17.000.000,- dinyatakan hangus 100% apabila setelah SPR diterbitkan, pembeli membatalkan atau tidak melanjutkan transaksi pembelian unit.",
    "Seluruh biaya all-in mengacu pada pengajuan dan kesepakatan sebelumnya, termasuk PPN, AJB, BPHTB, balik nama SHM, serta biaya legalitas dan biaya terkait lainnya yang telah disepakati. Apabila terdapat custom layout, perubahan desain, atau permintaan khusus dari pembeli, maka seluruh biaya tambahan yang timbul atas permintaan tersebut tidak menjadi tanggungan Developer.",
    "Apabila DP 1 sebesar Rp200.000.000,- telah diterima oleh Developer, namun pembeli membatalkan atau tidak melanjutkan transaksi sampai dengan proses PPJB pada bulan Desember 2026 sebagaimana telah disepakati, maka sebesar 20% dari DP 1 atau Rp40.000.000,- dinyatakan hangus. Potongan sebesar 20% tersebut diperhitungkan atas biaya yang telah timbul, termasuk biaya notaris, biaya desain, fee marketing, serta kehilangan potensi transaksi (lost opportunity) akibat penjadwalan pembayaran hingga Desember 2026. Sisa sebesar Rp160.000.000,- akan dikembalikan kepada pembeli setelah unit tersebut berhasil terjual kembali kepada pembeli berikutnya.",
    "Pembeli diperbolehkan menempati Unit A3 setelah proses penandatanganan PPJB antara pembeli dan Developer telah dilaksanakan, sesuai dengan ketentuan dan kesepakatan para pihak."
];

const form = useForm({
    unit_id: '',
    lead_id: null,
    client_name: '',
    client_phone: '',
    client_email: '',
    status: 'draft',

    // Form data structure matching the PDF
    form_data: {
        kepada: 'PT. Serangkai Roden Development – Alonica Hills',
        dari: '',
        cc: 'KanaHomi – Agent Coordinator',
        tanggal: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),

        project_name: 'Alonica Hills',
        project_address: 'Jl. Bhakti, RT.002/RW.007, Cilandak Tim, Ps Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta. 12560',
        kavling: 'Blok A3',
        type: 'Badan',
        luas_tanah: '105',
        luas_bangunan: '198',
        price_list: '3.840.921.600,-',
        reservasi: '10,000,000,-',
        reservasi_date: '8 Sept 2026',
        diskon: '45,000,000,- + 5,000,000,-',
        free_legalitas: '(PPN, AJB, BPHTB, SHM)',
        bonus: '(Canopy Carport, SmartdoorLock)',

        pengajuan: {
            cara_bayar: 'Cash Bertahap 3X',
            price: '3,550,921,600,-',
            reservasi: '10,000,000,-',
            reservasi_date: '08 Sept 2026',
            booking_fee: '7,000,000,-',
            booking_fee_total: '(Total 17,000,000,-)',
            booking_fee_date: '24 Sept 2026',
            dp1_pct: '%',
            dp1_amount: '200,000,000,-',
            dp1_date: '1 Okt 2026',
            dp2_amount: '1,666,960,800,-',
            dp2_date: '25 Des 2026',
            pelunasan_amount: '1,666,960,800,-',
            pelunasan_date: '25 Des 2027',
            notes: [...defaultPengajuanNotes],
        },

        jawaban: {
            show_jawaban: true,
            cara_bayar: 'Cash Bertahap 3X',
            price: '3,700,000,000,-',
            reservasi: '10,000,000,-',
            reservasi_date: '08 Sept 2026',
            booking_fee: '7,000,000,-',
            booking_fee_total: '(Total 17,000,000,-)',
            booking_fee_date: '24 Sept 2026',
            dp1_pct: '%',
            dp1_amount: '200,000,000,-',
            dp1_date: '1 Okt 2026',
            dp2_amount: '1,741,500,000,-',
            dp2_date: '25 Des 2026',
            pelunasan_amount: '1,741,500,000,-',
            pelunasan_date: '25 Des 2027',
            notes: [...defaultJawabanNotes],
            sig_city_date: 'Jakarta, ' + new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }),
            sig_pengaju_name: '',
            sig_mengetahui_name: 'Maulizar',
            sig_menyetujui_name: 'Ch. Bramantyo P.',
        }
    }
});

// Watch isOpen & initialize data
watch(() => props.isOpen, (open) => {
    if (!open) return;
    activeTab.value = props.initialTab || 'page1';

    if (props.negotiation) {
        // Edit existing negotiation
        const n = props.negotiation;
        form.unit_id = n.unit_id;
        form.lead_id = n.lead_id;
        form.client_name = n.client_name || '';
        form.client_phone = n.client_phone || '';
        form.client_email = n.client_email || '';
        form.status = n.status || 'draft';

        const fd = n.form_details || n.form_data || {};
        if (fd && Object.keys(fd).length > 0) {
            form.form_data = JSON.parse(JSON.stringify(fd));
        }
    } else {
        // Create new
        form.reset();
        if (props.lead) {
            form.lead_id = props.lead.id;
            form.client_name = props.lead.name || '';
            form.client_phone = props.lead.phone || '';
            form.client_email = props.lead.email || '';
            form.form_data.dari = props.lead.name || '';
            form.form_data.jawaban.sig_pengaju_name = props.lead.name || '';
        }
        if (props.units.length > 0) {
            const firstUnit = props.units[0];
            selectUnit(firstUnit);
        }
    }
}, { immediate: true });

function formatNum(val) {
    if (!val) return '0';
    return Number(val).toLocaleString('id-ID');
}

function onUnitChange() {
    const selected = props.units.find(u => u.id == form.unit_id);
    if (selected) {
        selectUnit(selected);
    }
}

function onLeadChange() {
    if (!form.lead_id) return;
    const l = props.leads.find(x => x.id == form.lead_id);
    if (l) {
        form.client_name = l.name || '';
        form.client_phone = l.phone || '';
        form.client_email = l.email || '';
        form.form_data.dari = l.name || '';
        if (form.form_data.jawaban) {
            form.form_data.jawaban.sig_pengaju_name = l.name || '';
        }
    }
}

function selectUnit(u) {
    if (!u) return;
    form.unit_id = u.id;
    const block = u.block || '';
    const num = u.number || '';
    const unitLabel = u.unit_number || (block ? `Blok ${block}${num ? ' ' + num : ''}` : (num ? `Unit ${num}` : 'Blok A3'));
    const shortCode = u.unit_number || (block ? `${block}${num}` : (num || 'A3'));
    const typeName = u.unit_type?.name || u.unitType?.name || 'Badan';
    const lt = u.surface_area || u.unit_type?.land_area || u.unitType?.land_area || '105';
    const lb = u.building_area || u.unit_type?.building_area || u.unitType?.building_area || '198';
    const price = u.final_price || u.price || 0;
    const projName = u.project?.name || 'Alonica Hills';
    const projAddr = u.project?.address || 'Jl. Bhakti, RT.002/RW.007, Cilandak Tim, Ps Minggu, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta. 12560';

    form.form_data.project_name = projName;
    form.form_data.project_address = projAddr;
    form.form_data.kepada = `PT. Serangkai Roden Development – ${projName}`;
    form.form_data.kavling = unitLabel;
    form.form_data.type = typeName;
    form.form_data.luas_tanah = String(lt);
    form.form_data.luas_bangunan = String(lb);
    if (price > 0) {
        form.form_data.price_list = `${formatNum(price)},-`;
    }

    // Auto-update note #6 regarding LT and note #4 regarding Unit code
    if (form.form_data.pengajuan?.notes && form.form_data.pengajuan.notes.length >= 6) {
        form.form_data.pengajuan.notes[5] = `Luas tanah pada form tertulis ${lt} m². Mohon konfirmasi angka final sesuai gambar kavling dan hasil pengukuran, untuk dicantumkan dalam PPJB.`;
    }
    if (form.form_data.jawaban?.notes && form.form_data.jawaban.notes.length >= 4) {
        form.form_data.jawaban.notes[3] = `Pembeli diperbolehkan menempati Unit ${shortCode} setelah proses penandatanganan PPJB antara pembeli dan Developer telah dilaksanakan, sesuai dengan ketentuan dan kesepakatan para pihak.`;
    }
}

watch(() => form.client_name, (val) => {
    form.form_data.dari = val;
    form.form_data.jawaban.sig_pengaju_name = val;
});

function addPengajuanNote() {
    form.form_data.pengajuan.notes.push('');
}

function removePengajuanNote(idx) {
    form.form_data.pengajuan.notes.splice(idx, 1);
}

function addJawabanNote() {
    form.form_data.jawaban.notes.push('');
}

function removeJawabanNote(idx) {
    form.form_data.jawaban.notes.splice(idx, 1);
}

function resetPengajuanNotes() {
    form.form_data.pengajuan.notes = [...defaultPengajuanNotes];
}

function resetJawabanNotes() {
    form.form_data.jawaban.notes = [...defaultJawabanNotes];
}

function submitForm(asStatus = null) {
    if (asStatus) {
        form.status = asStatus;
    }
    if (props.negotiation) {
        form.put(`/negotiations/${props.negotiation.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                closeModal();
            }
        });
    } else {
        form.post('/negotiations', {
            preserveScroll: true,
            onSuccess: (pageRes) => {
                const flash = pageRes.props.flash || {};
                resultLink.value = flash.negotiation_link || '';
                resultToken.value = flash.negotiation_token || '';
                resultId.value = flash.negotiation_id || null;
                showResultModal.value = true;
                emit('saved');
            }
        });
    }
}

function closeModal() {
    emit('close');
}

function closeResultModal() {
    showResultModal.value = false;
    emit('close');
}

function copyLink() {
    if (!resultLink.value && resultToken.value) {
        resultLink.value = `${window.location.origin}/nego/${resultToken.value}`;
    }
    navigator.clipboard.writeText(resultLink.value);
    alert('Link Negosiasi berhasil disalin ke clipboard:\n' + resultLink.value);
}

function shareWa() {
    const url = resultLink.value || `${window.location.origin}/nego/${resultToken.value}`;
    const msg = `Halo Bapak/Ibu *${form.client_name || 'Client'}*,\n\nBerikut kami lampirkan Surat & Form Pengajuan Negosiasi untuk unit *${form.form_data.kavling}* di *${form.form_data.project_name}*:\n\n🔗 ${url}\n\nSilakan dibuka dan disesuaikan. Dokumen PDF resmi juga dapat langsung diunduh melalui link tersebut.\n\nTerima kasih!\n*PT. Serangkai Roden Development*`;
    const phone = (form.client_phone || '').replace(/[^0-9]/g, '');
    const wa = phone.startsWith('0') ? '62' + phone.substring(1) : phone;
    window.open(`https://wa.me/${wa}?text=${encodeURIComponent(msg)}`, '_blank');
}

function openPdf() {
    if (resultId.value) {
        window.open(`/negotiations/${resultId.value}/pdf`, '_blank');
    } else if (resultToken.value) {
        window.open(`/nego/${resultToken.value}/pdf`, '_blank');
    } else if (props.negotiation) {
        window.open(`/negotiations/${props.negotiation.id}/pdf`, '_blank');
    }
}
</script>

<template>
    <!-- MAIN MODAL FORM -->
    <teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="closeModal"></div>
            
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[92vh] overflow-hidden border border-slate-200">
                
                <!-- HEADER -->
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-xl shadow-md">
                            🤝
                        </div>
                        <div>
                            <h2 class="text-base font-black text-white flex items-center gap-2">
                                <span>{{ negotiation ? 'Edit Form Pengajuan Negosiasi' : 'Kirim & Sesuaikan Form Negosiasi' }}</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-400 text-slate-950 font-bold uppercase tracking-wider">Format PDF Resmi</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Sesuaikan data kavling, harga, termin pembayaran, serta klausul hukum sebelum dikirim ke calon pembeli.
                            </p>
                        </div>
                    </div>
                    <button @click="closeModal" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-lg font-bold transition-colors">
                        ✕
                    </button>
                </div>

                <!-- TABS NAVIGATION -->
                <div class="px-6 bg-slate-100 border-b border-slate-200 flex items-center gap-3 shrink-0 overflow-x-auto text-xs font-bold">
                    <button 
                        @click="activeTab = 'page1'"
                        class="py-3 px-4 border-b-2 transition-all flex items-center gap-2"
                        :class="activeTab === 'page1' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-900'"
                    >
                        <span>📄 Halaman 1: Pengajuan Customer</span>
                    </button>
                    <button 
                        @click="activeTab = 'page2'"
                        class="py-3 px-4 border-b-2 transition-all flex items-center gap-2"
                        :class="activeTab === 'page2' ? 'border-purple-600 text-purple-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-900'"
                    >
                        <span>📜 Halaman 2: Jawaban & Tanda Tangan</span>
                    </button>
                    <button 
                        @click="activeTab = 'preview'"
                        class="py-3 px-4 border-b-2 transition-all flex items-center gap-2 ml-auto"
                        :class="activeTab === 'preview' ? 'border-emerald-600 text-emerald-600 font-black' : 'border-transparent text-slate-500 hover:text-slate-900'"
                    >
                        <span>👁️ Live Preview Layout PDF</span>
                    </button>
                </div>

                <!-- FORM CONTENT (SCROLLABLE) -->
                <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto p-6 space-y-6 text-xs text-slate-800">

                    <!-- ========================================== -->
                    <!-- TAB 1: HALAMAN 1 (FORM REQUEST CUSTOMER) -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'page1'" class="space-y-6">
                        
                        <!-- 1. IDENTITAS UNIT & CLIENT -->
                        <div class="p-4 bg-blue-50/60 border border-blue-200/80 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-blue-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🏠</span> 1. Pilih Unit & Identitas Calon Pembeli
                                </span>
                            </div>

                            <div v-if="leads && leads.length > 0" class="mb-3">
                                <label class="block font-bold text-slate-600 mb-1">Ambil dari Data Calon Pembeli (Lead) <span class="text-slate-400 font-normal">(Opsional)</span></label>
                                <select v-model="form.lead_id" @change="onLeadChange" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Input Manual / Calon Pembeli Baru --</option>
                                    <option v-for="l in leads" :key="l.id" :value="l.id">
                                        {{ l.name }} ({{ l.phone || 'Tanpa No. HP' }})
                                    </option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-600 mb-1">Pilih Unit Properti <span class="text-rose-500">*</span></label>
                                    <select v-model="form.unit_id" @change="onUnitChange" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs focus:ring-2 focus:ring-blue-500">
                                        <option value="">-- Pilih Unit --</option>
                                        <option v-for="u in units" :key="u.id" :value="u.id">
                                            {{ u.unit_number ? ('Unit ' + u.unit_number) : ('Blok ' + (u.block || '') + ' ' + (u.number || '')) }} - {{ u.unit_type?.name || u.unitType?.name || 'Tipe Standar' }} (Rp {{ formatNum(u.final_price || u.price) }})
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-600 mb-1">Nama Calon Pembeli <span class="text-rose-500">*</span></label>
                                    <input v-model="form.client_name" type="text" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs focus:ring-2 focus:ring-blue-500" placeholder="Eko Sukaryanto" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-600 mb-1">No. WhatsApp <span class="text-rose-500">*</span></label>
                                    <input v-model="form.client_phone" type="text" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs focus:ring-2 focus:ring-blue-500" placeholder="08123456789" />
                                </div>
                            </div>
                        </div>

                        <!-- 2. KOP & HEADER SURAT -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                                📋 2. Informasi Header Surat
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Kepada (Tujuan)</label>
                                    <input v-model="form.form_data.kepada" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">CC (Tembusan)</label>
                                    <input v-model="form.form_data.cc" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Dari (Pemohon)</label>
                                    <input v-model="form.form_data.dari" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Tanggal Surat</label>
                                    <input v-model="form.form_data.tanggal" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" />
                                </div>
                            </div>
                        </div>

                        <!-- 3. RINCIAN SPESIFIKASI PROYEK & KAVLING -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                                🏗️ 3. Rincian Unit & Price List
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Nama Proyek</label>
                                    <input v-model="form.form_data.project_name" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Kavling</label>
                                    <input v-model="form.form_data.kavling" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" placeholder="Blok A3" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Tipe Unit</label>
                                    <input v-model="form.form_data.type" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="Badan / Sudut" />
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Alamat Proyek</label>
                                    <input v-model="form.form_data.project_address" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-medium text-xs" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Luas Tanah (m²)</label>
                                    <input v-model="form.form_data.luas_tanah" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" placeholder="105" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Luas Bangunan (m²)</label>
                                    <input v-model="form.form_data.luas_bangunan" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" placeholder="198" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Price List Resmi</label>
                                    <input v-model="form.form_data.price_list" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-black text-xs font-mono text-slate-900" placeholder="3,840,921,600,-" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Reservasi Awal</label>
                                    <input v-model="form.form_data.reservasi" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="10,000,000,-" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Tanggal Reservasi</label>
                                    <input v-model="form.form_data.reservasi_date" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="8 Sept 2026" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Diskon Yang Diajukan</label>
                                    <input v-model="form.form_data.diskon" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="45,000,000,- + 5,000,000,-" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Free Legalitas</label>
                                    <input v-model="form.form_data.free_legalitas" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="(PPN, AJB, BPHTB, SHM)" />
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block font-bold text-slate-500 text-[11px] mb-1">Bonus Fasilitas</label>
                                    <input v-model="form.form_data.bonus" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="(Canopy Carport, SmartdoorLock)" />
                                </div>
                            </div>
                        </div>

                        <!-- 4. RINCIAN PENGAJUAN (TERMIN PEMBAYARAN) -->
                        <div class="p-4 bg-amber-50/60 border border-amber-200 rounded-2xl space-y-3">
                            <span class="font-black text-amber-950 text-xs uppercase tracking-wider block">
                                💰 4. Rincian Termin Pengajuan Pembayaran
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-600 text-[11px] mb-1">Cara Bayar</label>
                                    <input v-model="form.form_data.pengajuan.cara_bayar" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" placeholder="Cash Bertahap 3X" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-600 text-[11px] mb-1">Pengajuan Price (Nilai Akhir)</label>
                                    <input v-model="form.form_data.pengajuan.price" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-black text-xs font-mono text-emerald-700" placeholder="3,550,921,600,-" />
                                </div>
                            </div>

                            <div class="space-y-2 pt-2 border-t border-amber-200/60">
                                <div class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 px-1">
                                    <div class="col-span-3">Termin</div>
                                    <div class="col-span-5">Nominal / Keterangan</div>
                                    <div class="col-span-4">Tanggal Pembayaran</div>
                                </div>

                                <!-- Reservasi -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Reservasi</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.pengajuan.reservasi" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.pengajuan.reservasi_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>

                                <!-- Booking Fee -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Booking Fee</div>
                                    <div class="col-span-5 flex gap-1">
                                        <input v-model="form.form_data.pengajuan.booking_fee" type="text" class="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" placeholder="7,000,000,-" />
                                        <input v-model="form.form_data.pengajuan.booking_fee_total" type="text" class="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px]" placeholder="(Total 17,000,000,-)" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.pengajuan.booking_fee_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" placeholder="24 Sept 2026" />
                                    </div>
                                </div>

                                <!-- DP1 % -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">DP1 %</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.pengajuan.dp1_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" placeholder="200,000,000,-" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.pengajuan.dp1_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" placeholder="1 Okt 2026" />
                                    </div>
                                </div>

                                <!-- DP2 -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">DP2</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.pengajuan.dp2_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" placeholder="1,666,960,800,-" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.pengajuan.dp2_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" placeholder="25 Des 2026" />
                                    </div>
                                </div>

                                <!-- Pelunasan -->
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Pelunasan</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.pengajuan.pelunasan_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" placeholder="1,666,960,800,-" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.pengajuan.pelunasan_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" placeholder="25 Des 2027" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. NOTE / KLAUSUL PENGAJUAN (7 POIN) -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                                    📝 5. Klausul Ketentuan Pengajuan (Note Poin 1 s/d {{ form.form_data.pengajuan.notes.length }})
                                </span>
                                <div class="flex gap-2">
                                    <button type="button" @click="resetPengajuanNotes" class="px-2.5 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 text-[10px] font-bold rounded-lg transition-colors">
                                        ↺ Reset Default PDF
                                    </button>
                                    <button type="button" @click="addPengajuanNote" class="px-2.5 py-1 bg-blue-100 hover:bg-blue-200 text-blue-700 text-[10px] font-bold rounded-lg transition-colors">
                                        + Tambah Poin
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-for="(n, idx) in form.form_data.pengajuan.notes" :key="idx" class="flex items-start gap-2">
                                    <span class="w-6 h-6 rounded bg-white border border-slate-200 flex items-center justify-center font-bold text-xs shrink-0 mt-1">
                                        {{ idx + 1 }}
                                    </span>
                                    <textarea 
                                        v-model="form.form_data.pengajuan.notes[idx]" 
                                        rows="2" 
                                        class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs leading-relaxed"
                                    ></textarea>
                                    <button type="button" @click="removePengajuanNote(idx)" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg" title="Hapus poin">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: HALAMAN 2 (JAWABAN & TANDA TANGAN) -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'page2'" class="space-y-6">
                        
                        <div class="p-4 bg-purple-50/70 border border-purple-200 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-purple-950 text-xs uppercase tracking-wider block">
                                    🔄 1. Data Lembar JAWABAN (Counter Offer / Hasil Disetujui)
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-600 text-[11px] mb-1">Cara Bayar Jawaban</label>
                                    <input v-model="form.form_data.jawaban.cara_bayar" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold text-xs" placeholder="Cash Bertahap 3X" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-600 text-[11px] mb-1">Harga Disetujui / Counter Developer</label>
                                    <input v-model="form.form_data.jawaban.price" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-black text-xs font-mono text-purple-900" placeholder="3,700,000,000,-" />
                                </div>
                            </div>

                            <!-- Termin Jawaban -->
                            <div class="space-y-2 pt-2 border-t border-purple-200/60">
                                <div class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 px-1">
                                    <div class="col-span-3">Termin</div>
                                    <div class="col-span-5">Nominal / Keterangan</div>
                                    <div class="col-span-4">Tanggal Pembayaran</div>
                                </div>

                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Reservasi</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.jawaban.reservasi" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.jawaban.reservasi_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Booking Fee</div>
                                    <div class="col-span-5 flex gap-1">
                                        <input v-model="form.form_data.jawaban.booking_fee" type="text" class="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                        <input v-model="form.form_data.jawaban.booking_fee_total" type="text" class="w-1/2 px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px]" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.jawaban.booking_fee_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">DP1 %</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.jawaban.dp1_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.jawaban.dp1_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">DP2</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.jawaban.dp2_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.jawaban.dp2_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-3 font-bold text-slate-700">Pelunasan</div>
                                    <div class="col-span-5">
                                        <input v-model="form.form_data.jawaban.pelunasan_amount" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-semibold text-xs" />
                                    </div>
                                    <div class="col-span-4">
                                        <input v-model="form.form_data.jawaban.pelunasan_date" type="text" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KLAUSUL NOTE JAWABAN (4 POIN) -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                                    ⚖️ 2. Klausul Ketentuan Developer (Note Poin 1 s/d {{ form.form_data.jawaban.notes.length }})
                                </span>
                                <div class="flex gap-2">
                                    <button type="button" @click="resetJawabanNotes" class="px-2.5 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 text-[10px] font-bold rounded-lg transition-colors">
                                        ↺ Reset Default PDF
                                    </button>
                                    <button type="button" @click="addJawabanNote" class="px-2.5 py-1 bg-purple-100 hover:bg-purple-200 text-purple-700 text-[10px] font-bold rounded-lg transition-colors">
                                        + Tambah Poin
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-for="(jn, idx) in form.form_data.jawaban.notes" :key="idx" class="flex items-start gap-2">
                                    <span class="w-6 h-6 rounded bg-white border border-slate-200 flex items-center justify-center font-bold text-xs shrink-0 mt-1">
                                        {{ idx + 1 }}
                                    </span>
                                    <textarea 
                                        v-model="form.form_data.jawaban.notes[idx]" 
                                        rows="3" 
                                        class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs leading-relaxed"
                                    ></textarea>
                                    <button type="button" @click="removeJawabanNote(idx)" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg" title="Hapus poin">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 3. OTENTIKASI & TANDA TANGAN (3 PIHAK) -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                            <span class="font-black text-slate-900 text-xs uppercase tracking-wider block">
                                ✍️ 3. Lembar Tanda Tangan (3 Pihak)
                            </span>

                            <div>
                                <label class="block font-bold text-slate-500 text-[11px] mb-1">Kota & Tanggal TTD</label>
                                <input v-model="form.form_data.jawaban.sig_city_date" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-semibold text-xs" placeholder="Jakarta, 24 September 2026" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                                <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">1. Yang Mengajukan (Client)</span>
                                    <input v-model="form.form_data.jawaban.sig_pengaju_name" type="text" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg font-bold text-xs" placeholder="Nama Client" />
                                </div>
                                <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">2. Mengetahui</span>
                                    <input v-model="form.form_data.jawaban.sig_mengetahui_name" type="text" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg font-bold text-xs" placeholder="Maulizar" />
                                    <p class="text-[9px] text-emerald-600 font-semibold">✓ TTD Digital terlampir di PDF</p>
                                </div>
                                <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1">
                                    <span class="block text-[10px] font-bold text-slate-400 uppercase">3. Menyetujui (Developer)</span>
                                    <input v-model="form.form_data.jawaban.sig_menyetujui_name" type="text" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg font-bold text-xs" placeholder="Ch. Bramantyo P." />
                                    <p class="text-[9px] text-emerald-600 font-semibold">✓ TTD Digital terlampir di PDF</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: LIVE PREVIEW LAYOUT PDF -->
                    <!-- ========================================== -->
                    <div v-show="activeTab === 'preview'" class="space-y-6">
                        <div class="bg-slate-100 p-4 rounded-2xl flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">Simulasi Tampilan Dokumen PDF Resmi</h4>
                                <p class="text-[11px] text-slate-500">Hasil cetak PDF akan dirender persis seperti lembar di bawah ini.</p>
                            </div>
                            <span class="px-3 py-1 bg-white border border-slate-200 text-slate-700 font-bold rounded-lg text-xs">
                                2 Halaman (A4 Portrait)
                            </span>
                        </div>

                        <!-- Sheet 1 Preview -->
                        <div class="bg-white border-2 border-slate-300 rounded-xl p-8 shadow-sm max-w-2xl mx-auto space-y-4 font-sans text-[11px] text-slate-900 leading-normal">
                            <div class="w-20 mb-2">
                                <img src="/images/alonica_logo.png" class="h-12 object-contain" alt="Alonica" />
                            </div>

                            <div class="bg-[#b8cce4] border border-[#5b7999] py-1 text-center font-black tracking-wider uppercase text-xs">
                                FORM REQUEST CUSTOMER
                            </div>

                            <table class="w-full text-xs">
                                <tr>
                                    <td class="w-24 text-slate-700">Kepada</td>
                                    <td class="w-4">:</td>
                                    <td class="font-bold">{{ form.form_data.kepada }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Dari</td>
                                    <td>:</td>
                                    <td class="font-bold">{{ form.form_data.dari }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">CC</td>
                                    <td>:</td>
                                    <td class="font-bold">{{ form.form_data.cc }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Tanggal</td>
                                    <td>:</td>
                                    <td class="font-bold">{{ form.form_data.tanggal }}</td>
                                </tr>
                            </table>

                            <div class="border-b-2 border-black my-2"></div>

                            <table class="w-full text-xs leading-relaxed">
                                <tr>
                                    <td class="w-28 text-slate-700">Project</td>
                                    <td class="w-4">:</td>
                                    <td class="font-bold" colspan="2">{{ form.form_data.project_name }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Alamat</td>
                                    <td>:</td>
                                    <td class="font-normal" colspan="2">{{ form.form_data.project_address }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Kavling</td>
                                    <td>:</td>
                                    <td class="font-bold" colspan="2">{{ form.form_data.kavling }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Type</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.type }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Luas Tanah</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.luas_tanah }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Luas Bangunan</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.luas_bangunan }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Price list</td>
                                    <td>:</td>
                                    <td class="font-bold" colspan="2">{{ form.form_data.price_list }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Reservasi</td>
                                    <td>:</td>
                                    <td class="w-44">{{ form.form_data.reservasi }}</td>
                                    <td>Tanggal : {{ form.form_data.reservasi_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Diskon</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.diskon }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Free Legalitas</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.free_legalitas }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Bonus</td>
                                    <td>:</td>
                                    <td colspan="2">{{ form.form_data.bonus }}</td>
                                </tr>
                            </table>

                            <div class="border-b-2 border-black my-2"></div>

                            <div class="font-black text-xs uppercase">PENGAJUAN</div>
                            <table class="w-full text-xs leading-relaxed">
                                <tr>
                                    <td class="w-28 text-slate-700">Cara bayar</td>
                                    <td class="w-4">:</td>
                                    <td class="font-bold" colspan="2">{{ form.form_data.pengajuan.cara_bayar }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Pengajuan Price</td>
                                    <td>:</td>
                                    <td class="font-bold" colspan="2">{{ form.form_data.pengajuan.price }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Reservasi</td>
                                    <td>:</td>
                                    <td class="w-44">{{ form.form_data.pengajuan.reservasi }}</td>
                                    <td>Tanggal : {{ form.form_data.pengajuan.reservasi_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Booking Fee</td>
                                    <td>:</td>
                                    <td>{{ form.form_data.pengajuan.booking_fee }} {{ form.form_data.pengajuan.booking_fee_total }}</td>
                                    <td>Tanggal : {{ form.form_data.pengajuan.booking_fee_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">DP1 %</td>
                                    <td>:</td>
                                    <td>{{ form.form_data.pengajuan.dp1_amount }}</td>
                                    <td>Tanggal : {{ form.form_data.pengajuan.dp1_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">DP2</td>
                                    <td>:</td>
                                    <td>{{ form.form_data.pengajuan.dp2_amount }}</td>
                                    <td>Tanggal : {{ form.form_data.pengajuan.dp2_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-slate-700">Pelunasan</td>
                                    <td>:</td>
                                    <td>{{ form.form_data.pengajuan.pelunasan_amount }}</td>
                                    <td>Tanggal : {{ form.form_data.pengajuan.pelunasan_date }}</td>
                                </tr>
                            </table>

                            <div class="border-b-2 border-black my-2"></div>

                            <div class="font-bold text-xs mb-1">Note :</div>
                            <ol class="list-decimal pl-5 space-y-1 text-[10px] text-slate-700 text-justify">
                                <li v-for="(n, i) in form.form_data.pengajuan.notes" :key="i">{{ n }}</li>
                            </ol>
                        </div>
                    </div>

                    <!-- FOOTER ACTIONS -->
                    <div class="flex items-center justify-between pt-4 border-t border-slate-200 mt-6 shrink-0">
                        <div class="flex items-center gap-2">
                            <button 
                                v-if="activeTab === 'page1'" 
                                type="button" 
                                @click="activeTab = 'page2'" 
                                class="px-4 py-2.5 bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold rounded-xl text-xs transition-colors flex items-center gap-1.5"
                            >
                                <span>💼</span> Ke Halaman 2 (Jawaban Developer) →
                            </button>
                            <button 
                                v-if="activeTab === 'page2'" 
                                type="button" 
                                @click="activeTab = 'page1'" 
                                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors"
                            >
                                ← Kembali ke Halaman 1 (Pengajuan)
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeModal" class="px-4 py-2.5 bg-slate-100 text-slate-600 font-bold rounded-xl text-xs hover:bg-slate-200 transition-colors">
                                Batal
                            </button>
                            
                            <!-- Approve button when on page 2 (Jawaban) or editing -->
                            <button 
                                v-if="negotiation && activeTab === 'page2'" 
                                type="button" 
                                @click="submitForm('approved')" 
                                :disabled="form.processing" 
                                class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-black rounded-xl text-xs shadow-lg shadow-emerald-500/25 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                <span>✅</span>
                                <span>Simpan & SETUJUI Negosiasi</span>
                            </button>

                            <button 
                                type="button" 
                                @click="submitForm()" 
                                :disabled="form.processing" 
                                class="px-5 py-2.5 bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-white font-black rounded-xl text-xs shadow-lg shadow-amber-500/25 transition-all flex items-center gap-2 disabled:opacity-50"
                            >
                                <span>💾</span>
                                <span>{{ negotiation ? (activeTab === 'page2' ? 'Simpan Draft Jawaban' : 'Simpan Perubahan Dokumen') : 'Simpan & Dapatkan Link Negosiasi' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </teleport>

    <!-- SUCCESS SHARE LINK MODAL -->
    <teleport to="body">
        <div v-if="showResultModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="closeResultModal"></div>
            <div class="relative bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl animate-in zoom-in duration-150">
                <div class="text-center">
                    <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3 shadow-inner">
                        ✅
                    </div>
                    <h3 class="text-base font-black text-slate-900">Form Negosiasi Berhasil Disimpan!</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Draft surat & formulir negosiasi telah disesuaikan dengan template resmi. Anda dapat langsung mengunduh PDF atau mengirim link ke client via WhatsApp.
                    </p>
                </div>

                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Link Form Negosiasi</p>
                    <div class="flex items-center gap-2">
                        <input :value="resultLink" readonly class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-mono text-blue-600 truncate" />
                        <button @click="copyLink" class="px-3 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-bold hover:bg-slate-800 transition-all shrink-0">
                            📋 Salin
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button @click="shareWa" class="col-span-2 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
                        <span>💬</span> <span>Kirim Form via WhatsApp</span>
                    </button>
                    <button @click="openPdf" class="py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
                        <span>📄</span> <span>Buka / Cetak PDF</span>
                    </button>
                    <button @click="closeResultModal" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all">
                        Selesai / Tutup
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>
