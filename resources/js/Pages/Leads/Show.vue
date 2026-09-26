<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import KprCalculatorModal from '@/Components/Crm/KprCalculatorModal.vue';
import NegotiationTemplateModal from '@/Components/Negotiations/NegotiationTemplateModal.vue';

const props = defineProps({
    lead: Object,
    units: Array,
    projects: {
        type: Array,
        default: () => []
    },
    agents: Array,
    brokerCompanies: {
        type: Array,
        default: () => []
    },
});

// Active workspace tab: 'workspace' | 'timeline' | 'documents' | 'client'
const activeTab = ref('workspace');

// Transaction single ID
const transactionId = computed(() => {
    return props.lead.transaction_code || `TRX-HOMI-${props.lead.id}`;
});

function copyTransactionId() {
    navigator.clipboard.writeText(transactionId.value);
    alert(`Transaction ID ${transactionId.value} berhasil disalin!`);
}

// Navigation / Steps definition
const statusSteps = [
    { key: 'new', label: 'Baru', short: 'Baru', icon: '✨' },
    { key: 'contacted', label: 'Dihubungi', short: 'Kontak', icon: '📞' },
    { key: 'visited', label: 'Kunjungan', short: 'Visit', icon: '🏠' },
    { key: 'negotiation', label: 'Negosiasi', short: 'Nego', icon: '🤝' },
    { key: 'reservation', label: 'Reservasi Unit', short: 'Reservasi', icon: '🔖' },
    { key: 'booking', label: 'Booking (SPR)', short: 'Booking', icon: '💰' },
    { key: 'won', label: 'Closing (Won)', short: 'Closing', icon: '🏆' },
];

const currentStepIndex = computed(() => {
    const idx = statusSteps.findIndex(s => s.key === props.lead.status);
    return idx >= 0 ? idx : 0;
});

const progressPercentage = computed(() => {
    const s = props.lead.status;
    if (s === 'new') return 15;
    if (s === 'contacted') return 30;
    if (s === 'visited') return 45;
    if (s === 'negotiation') return 60;
    if (s === 'reservation') return 75;
    if (s === 'booking') return 90;
    if (s === 'won') return 100;
    return 10;
});

function changeStatus(newStatus) {
    if (newStatus === props.lead.status) return;
    if (newStatus === 'reservation') {
        if (confirm('Ubah status ke "Reservasi Unit"? Ingin langsung memilih unit kavling & menerbitkan data reservasi?')) {
            router.put(`/leads/${props.lead.id}`, { status: newStatus }, {
                preserveScroll: true,
                onSuccess: () => router.visit(`/reservations/create?lead_id=${props.lead.id}`)
            });
            return;
        }
    }
    router.put(`/leads/${props.lead.id}`, { status: newStatus }, { preserveScroll: true });
}

// Primary objects
const latestBooking = computed(() => props.lead.bookings?.[0] || null);
const latestReservation = computed(() => props.lead.reservations?.[0] || null);
const latestNegotiation = computed(() => props.lead.negotiations?.[0] || null);

const preferredUnit = computed(() => {
    const pId = props.lead.preferences?.preferred_unit_id;
    if (!pId) return null;
    return (props.units || []).find(u => u.id == pId);
});

const primaryUnit = computed(() => {
    return latestBooking.value?.unit || latestReservation.value?.unit || latestNegotiation.value?.unit || preferredUnit.value || null;
});

const brochurePrice = computed(() => {
    return Number(primaryUnit.value?.final_price || primaryUnit.value?.price || 0);
});

const agreedDealPrice = computed(() => {
    if (latestBooking.value?.final_price) return Number(latestBooking.value.final_price);
    if (latestNegotiation.value) {
        return Number(latestNegotiation.value.offered_price || latestNegotiation.value.counter_price || brochurePrice.value);
    }
    return brochurePrice.value;
});

const discountSavings = computed(() => {
    if (brochurePrice.value > 0 && agreedDealPrice.value > 0 && agreedDealPrice.value < brochurePrice.value) {
        return brochurePrice.value - agreedDealPrice.value;
    }
    return 0;
});

const bookingFeeAmount = computed(() => {
    return Number(latestBooking.value?.booking_fee || latestReservation.value?.amount || 0);
});

// Format currency
const formatCurrency = (val) => {
    if (!val && val !== 0) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

// NEXT ACTION ENGINE (Smart Workflow Guide)
const nextAction = computed(() => {
    const status = props.lead.status;
    const booking = latestBooking.value;
    const reservation = latestReservation.value;
    const nego = latestNegotiation.value;

    if (status === 'new') {
        return {
            stage: 'Tahap 1: Hubungi Calon Pembeli',
            title: 'Lakukan Kontak Pertama via WhatsApp atau Telepon',
            desc: 'Calon pembeli baru masuk ke sistem. Segera bangun komunikasi awal untuk menjadwalkan konsultasi hunian atau survey lokasi.',
            primaryAction: 'wa',
            actionText: '💬 Hubungi via WhatsApp',
            secondaryAction: 'activity_call',
            secondaryText: '📞 Catat Panggilan',
            icon: '✨',
            color: 'from-blue-600 to-indigo-600'
        };
    }

    if (status === 'contacted') {
        return {
            stage: 'Tahap 2: Jadwalkan Survey Lokasi (Visit)',
            title: 'Undang Calon Pembeli Melakukan Site Visit',
            desc: 'Komunikasi awal telah berjalan. Ajak calon pembeli melihat langsung rumah contoh dan lokasi kavling proyek.',
            primaryAction: 'activity_visit',
            actionText: '🏠 Catat Jadwal Visit / Survey',
            secondaryAction: 'wa_visit',
            secondaryText: '💬 Kirim Undangan WA',
            icon: '📞',
            color: 'from-cyan-600 to-blue-600'
        };
    }

    if (status === 'visited') {
        return {
            stage: 'Tahap 3: Pemilihan Unit & Penawaran Nego',
            title: 'Pilih Kavling Unit & Buat Form Negosiasi Resmi',
            desc: 'Konsumen sudah berkunjung ke lokasi. Siapkan link formulir negosiasi resmi atau diskon kesepakatan harga.',
            primaryAction: 'nego_modal',
            actionText: '🤝 Buat Form Negosiasi Resmi',
            secondaryAction: 'res_create',
            secondaryText: '🔖 Langsung Hold Unit (Reservasi)',
            icon: '🏠',
            color: 'from-purple-600 to-indigo-600'
        };
    }

    if (status === 'negotiation') {
        if (!nego) {
            return {
                stage: 'Tahap 4: Negosiasi Harga',
                title: 'Terbitkan Link Form Negosiasi Resmi',
                desc: 'Lead berada di tahap negosiasi namun belum memiliki formulir pengajuan penawaran aktif.',
                primaryAction: 'nego_modal',
                actionText: '🤝 Buat Form Negosiasi Sekarang',
                icon: '🤝',
                color: 'from-amber-600 to-orange-600'
            };
        }
        if (nego.status === 'pending' || nego.status === 'draft') {
            return {
                stage: 'Tahap 4: Review Negosiasi',
                title: 'Review & Setujui (Approve) Harga Pengajuan Developer',
                desc: `Pengajuan harga deal ${formatCurrency(nego.offered_price)} sedang menunggu review developer. Silakan setujui atau berikan counter offer.`,
                primaryAction: 'url',
                actionUrl: `/negotiations/${nego.id}`,
                actionText: '⚖️ Buka Review & Approval Negosiasi',
                icon: '⏳',
                color: 'from-amber-600 to-yellow-600'
            };
        }
        if (nego.status === 'approved') {
            return {
                stage: 'Tahap 4: Negosiasi Disetujui (Deal)',
                title: 'Negosiasi Disetujui! Kunci Kavling & Buat Reservasi',
                desc: `Harga kesepakatan ${formatCurrency(nego.offered_price || nego.counter_price)} telah disetujui. Lanjutkan penguncian kavling dengan Uang Tanda Jadi (UTJ).`,
                primaryAction: 'url',
                actionUrl: `/reservations/create?lead_id=${props.lead.id}&negotiation_id=${nego.id}&unit_id=${nego.unit_id || ''}`,
                actionText: '🔖 Hold Unit & Buat Reservasi (UTJ)',
                secondaryAction: 'url',
                secondaryUrl: `/bookings/create?lead_id=${props.lead.id}&negotiation_id=${nego.id}&unit_id=${nego.unit_id || ''}`,
                secondaryText: '📝 Langsung Buat Booking (SPR)',
                icon: '🎉',
                color: 'from-emerald-600 to-teal-600'
            };
        }
    }

    if (status === 'reservation') {
        if (!reservation) {
            return {
                stage: 'Tahap 5: Reservasi Unit (Hold Unit)',
                title: 'Pilih Kavling Unit & Terbitkan Kwitansi Reservasi',
                desc: 'Konsumen berstatus reservasi namun belum memiliki data transaksi kavling. Pilih unit untuk me-lock stok dan cetak kwitansi.',
                primaryAction: 'url',
                actionUrl: `/reservations/create?lead_id=${props.lead.id}`,
                actionText: '➕ Pilih Unit & Buat Reservasi',
                icon: '🔖',
                color: 'from-teal-600 to-emerald-600'
            };
        }
        if (reservation.status === 'active') {
            return {
                stage: 'Tahap 5: Reservasi Aktif (Hold Unit)',
                title: 'Konversi Reservasi ke Booking & Terbitkan SPR',
                desc: `Uang Tanda Jadi sebesar ${formatCurrency(reservation.amount)} telah diterima. Lanjutkan pembuatan Surat Pesanan Rumah (SPR) resmi.`,
                primaryAction: 'url',
                actionUrl: `/bookings/create?reservation_id=${reservation.id}&unit_id=${reservation.unit_id}&lead_id=${props.lead.id}&reserved_amount=${reservation.amount}`,
                actionText: '➡️ Konversi ke Booking & Susun SPR',
                secondaryAction: 'url_blank',
                secondaryUrl: `/reservations/${reservation.id}/receipt`,
                secondaryText: '📄 Download Kwitansi UTJ',
                icon: '🟢',
                color: 'from-teal-600 to-blue-600'
            };
        }
    }

    if (status === 'booking') {
        if (!booking) {
            return {
                stage: 'Tahap 6: Booking & Penerbitan SPR',
                title: 'Formulir Booking Belum Dibuat',
                desc: 'Lead berstatus booking namun data transaksi pemesanan (SPK/SPR) belum tersimpan.',
                primaryAction: 'url',
                actionUrl: `/bookings/create?lead_id=${props.lead.id}`,
                actionText: '📝 Buat Formulir Booking & SPR',
                icon: '💰',
                color: 'from-blue-600 to-indigo-600'
            };
        }
        if (booking.status === 'pending') {
            return {
                stage: 'Tahap 6: Review & Approval Booking',
                title: 'Approve Booking & Kunci Status Unit (Booked)',
                desc: 'Booking diajukan. Developer perlu menyetujui (Approve) agar jadwal termin pembayaran terbit dan unit terkunci Booked.',
                primaryAction: 'url',
                actionUrl: `/bookings/${booking.id}`,
                actionText: '✅ Approve Booking & Terbitkan Jadwal SPR',
                icon: '⏳',
                color: 'from-amber-600 to-orange-600'
            };
        }
        if (booking.status === 'approved') {
            if (!booking.customer_signed_at) {
                return {
                    stage: 'Tahap 6: Tanda Tangan Digital SPR',
                    title: 'Minta Tanda Tangan Digital Konsumen pada SPR',
                    desc: 'Dokumen SPR resmi telah terbit. Tinjau dokumen SPR, unduh PDF resmi, atau bagikan link tanda tangan digital ke konsumen.',
                    primaryAction: 'preview_spr',
                    actionText: '👁️ Tinjau Dokumen SPR',
                    secondaryAction: 'url_blank',
                    secondaryUrl: `/booking-tracking/${booking.tracking_token}`,
                    secondaryText: '🖋️ Buka Halaman TTD Digital',
                    icon: '📄',
                    color: 'from-indigo-600 to-purple-600'
                };
            } else {
                return {
                    stage: 'Tahap 6: SPR Selesai Ditandatangani',
                    title: 'Verifikasi Pembayaran Termin DP / Akad KPR',
                    desc: 'SPR telah lengkap ditandatangani digital oleh konsumen & developer. Pantau jadwal pembayaran angsuran.',
                    primaryAction: 'preview_spr',
                    actionText: '👁️ Tinjau Dokumen SPR',
                    secondaryAction: 'url',
                    secondaryUrl: `/bookings/${booking.id}`,
                    secondaryText: '💳 Buka Detail Jadwal Pembayaran',
                    icon: '✅',
                    color: 'from-emerald-600 to-green-600'
                };
            }
        }
    }

    if (status === 'won') {
        return {
            stage: 'Tahap 7: Closing Selesai (Won)',
            title: 'Transaksi Sukses & Closing Resmi',
            desc: 'Seluruh tahapan pembelian unit telah closing. Berkas transaksi tersimpan lengkap di Document Center.',
            primaryAction: 'url',
            actionUrl: booking ? `/bookings/${booking.id}` : '#',
            actionText: '📄 Buka Detail Transaksi & SPR',
            icon: '🏆',
            color: 'from-green-600 to-emerald-600'
        };
    }

    return {
        stage: 'Status: ' + status,
        title: 'Perbarui Prospek atau Catat Aktivitas',
        desc: 'Pantau kelanjutan calon pembeli ini.',
        primaryAction: 'wa',
        actionText: '💬 Hubungi via WhatsApp',
        icon: '📋',
        color: 'from-slate-700 to-slate-900'
    };
});

function handleNextAction(type, url) {
    if (type === 'preview_spr') {
        openSprPreview();
    } else if (type === 'url') {
        router.visit(url);
    } else if (type === 'url_blank') {
        window.open(url, '_blank');
    } else if (type === 'wa') {
        openWaModal('perkenalan');
    } else if (type === 'wa_visit') {
        openWaModal('visit');
    } else if (type === 'nego_modal') {
        showNegoModal.value = true;
    } else if (type === 'activity_call') {
        activityForm.type = 'call';
        activeTab.value = 'timeline';
    } else if (type === 'activity_visit') {
        activityForm.type = 'visit';
        activeTab.value = 'timeline';
    } else if (type === 'res_create') {
        router.visit(`/reservations/create?lead_id=${props.lead.id}`);
    }
}

// UNIFIED CHRONOLOGICAL TIMELINE
const unifiedTimeline = computed(() => {
    const list = [];

    // 1. Activities
    (props.lead.activities || []).forEach(act => {
        list.push({
            id: `act-${act.id}`,
            date: new Date(act.created_at),
            type: 'activity',
            badge: act.type,
            icon: activityIcons[act.type] || '📝',
            title: act.description,
            subtitle: act.user?.name ? `Oleh: ${act.user.name}` : '',
            color: 'bg-slate-100 text-slate-700',
        });
    });

    // 2. Negotiations
    (props.lead.negotiations || []).forEach(neg => {
        list.push({
            id: `neg-${neg.id}`,
            date: new Date(neg.created_at),
            type: 'negotiation',
            badge: 'Negosiasi ' + neg.status.toUpperCase(),
            icon: '🤝',
            title: `Pengajuan Negosiasi #${neg.negotiation_number || neg.token}`,
            subtitle: `Harga Penawaran: ${formatCurrency(neg.offered_price || neg.counter_price)} · Unit: ${neg.unit?.code || '-'}`,
            color: neg.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800',
            link: `/negotiations/${neg.id}`
        });
    });

    // 3. Reservations
    (props.lead.reservations || []).forEach(res => {
        list.push({
            id: `res-${res.id}`,
            date: new Date(res.created_at),
            type: 'reservation',
            badge: 'Reservasi ' + res.status.toUpperCase(),
            icon: '🔖',
            title: `Reservasi Unit #${res.reservation_number} (${res.unit?.code || 'Kavling'})`,
            subtitle: `Kredit Biaya Reservasi: ${formatCurrency(res.amount)} · ${res.status === 'active' ? 'Unit Dikunci Hold' : 'Dikonversi'}`,
            color: 'bg-teal-100 text-teal-800',
            link: `/reservations/${res.id}`
        });
    });

    // 4. Bookings
    (props.lead.bookings || []).forEach(book => {
        list.push({
            id: `book-${book.id}`,
            date: new Date(book.created_at),
            type: 'booking',
            badge: 'Booking ' + book.status.toUpperCase(),
            icon: '💰',
            title: `Booking Resmi / SPK #${book.spk_number} (${book.unit?.code || 'Kavling'})`,
            subtitle: `Harga Deal: ${formatCurrency(book.final_price)} · UTJ: ${formatCurrency(book.booking_fee)} · Status: ${book.status}`,
            color: book.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800',
            link: `/bookings/${book.id}`
        });
    });

    // Sort descending (latest first)
    list.sort((a, b) => b.date - a.date);
    return list;
});

// DOCUMENT CENTER
const documentList = computed(() => {
    const docs = [];

    // SPR Document
    if (latestBooking.value) {
        docs.push({
            category: 'Surat Pesanan Rumah (SPR)',
            title: `SPR #${latestBooking.value.spk_number}`,
            unit: latestBooking.value.unit?.code || '-',
            date: latestBooking.value.booking_date || latestBooking.value.created_at,
            status: latestBooking.value.customer_signed_at ? 'Ditandatangani Digital' : (latestBooking.value.status === 'approved' ? 'Terbit (Siap TTD)' : 'Draft Booking'),
            statusColor: latestBooking.value.customer_signed_at ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800',
            actions: [
                { label: '👁️ Tinjau Dokumen', action: 'preview_spr' },
                { label: '📥 Unduh PDF', url: `/bookings/${latestBooking.value.id}/spk`, blank: true },
                { label: '⚙️ Atur Skema & Jadwal', url: `/bookings/${latestBooking.value.id}`, blank: false },
                { label: '🖋️ Link TTD Konsumen', url: `/booking-tracking/${latestBooking.value.tracking_token}`, blank: true },
            ]
        });
    }

    // Reservation Receipt
    if (latestReservation.value) {
        docs.push({
            category: 'Kwitansi Tanda Terima',
            title: `Kwitansi Reservasi #${latestReservation.value.reservation_number}`,
            unit: latestReservation.value.unit?.code || '-',
            date: latestReservation.value.created_at,
            status: 'Garansi 100% Refundable',
            statusColor: 'bg-teal-100 text-teal-800',
            actions: [
                { label: '📄 Cetak Kwitansi PDF', url: `/reservations/${latestReservation.value.id}/receipt`, blank: true },
                { label: '🔍 Detail Reservasi', url: `/reservations/${latestReservation.value.id}`, blank: false },
            ]
        });
    }

    // Negotiation Form
    if (latestNegotiation.value) {
        docs.push({
            category: 'Form Negosiasi Resmi',
            title: `Form #${latestNegotiation.value.negotiation_number || latestNegotiation.value.token}`,
            unit: latestNegotiation.value.unit?.code || 'Semua Unit',
            date: latestNegotiation.value.created_at,
            status: 'Negosiasi: ' + latestNegotiation.value.status.toUpperCase(),
            statusColor: latestNegotiation.value.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800',
            actions: [
                { label: '📋 Buka Form Client', url: `/n/${latestNegotiation.value.token}`, blank: true },
                { label: '⚖️ Detail & Approval', url: `/negotiations/${latestNegotiation.value.id}`, blank: false },
            ]
        });
    }

    return docs;
});

// Modals State
const showNegoModal = ref(false);
const showEditNegoModal = ref(false);
const selectedNego = ref(null);
const showChangeResUnitModal = ref(false);
const selectedRes = ref(null);
const showEditLeadModal = ref(false);
const showReminderModal = ref(false);
const showKprModal = ref(false);
const showUnitPickerModal = ref(false);

// SPR Preview Modal state
const showSprPreviewModal = ref(false);
const sprPreviewTimestamp = ref(Date.now());
function openSprPreview() {
    sprPreviewTimestamp.value = Date.now();
    showSprPreviewModal.value = true;
}

// Quick Assign Agent Modal
const showAssignAgentModal = ref(false);
const assignAgentForm = useForm({
    assigned_to: props.lead.assigned_to_user?.id || (typeof props.lead.assigned_to === 'object' ? props.lead.assigned_to?.id : props.lead.assigned_to) || '',
    broker_company_id: props.lead.broker_company_id || '',
});

function openAssignAgentModal() {
    assignAgentForm.assigned_to = props.lead.assigned_to_user?.id || (typeof props.lead.assigned_to === 'object' ? props.lead.assigned_to?.id : props.lead.assigned_to) || '';
    assignAgentForm.broker_company_id = props.lead.broker_company_id || '';
    showAssignAgentModal.value = true;
}

function submitAssignAgent() {
    assignAgentForm.put(`/leads/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showAssignAgentModal.value = false;
        }
    });
}

// Forms
const activityForm = useForm({ type: 'note', description: '' });
function submitActivity() {
    activityForm.post(`/leads/${props.lead.id}/activity`, {
        preserveScroll: true,
        onSuccess: () => activityForm.reset('description')
    });
}

const reminderForm = useForm({ remind_at: '', message: '' });
function submitReminder() {
    reminderForm.post(`/leads/${props.lead.id}/reminder`, {
        preserveScroll: true,
        onSuccess: () => {
            showReminderModal.value = false;
            reminderForm.reset();
        }
    });
}

// Activity icons
const activityIcons = {
    call: '📞', whatsapp: '💬', email: '📧', visit: '🏠', meeting: '🤝', note: '📝', status_change: '🔄',
};

// WhatsApp template generator
const generatedMessage = ref('');
function generateWaMessage(type) {
    const agentName = props.lead.assigned_to_user?.name || 'Konsultan Homi';
    const projectName = props.lead.project?.name || 'Proyek Homi';
    
    if (type === 'perkenalan') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nPerkenalkan saya *${agentName}* dari Homi Developer. Terima kasih atas ketertarikan Anda pada proyek *${projectName}*.\n\nApakah ada waktu luang untuk berdiskusi mengenai tipe rumah atau denah kavling yang sedang dicari?\n\nSalam,\n*${agentName}* - Homi Developer`;
    } else if (type === 'visit') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nMenyambung pembicaraan kita, kami mengundang Bapak/Ibu untuk berkunjung langsung (*Site Visit*) melihat rumah contoh dan lokasi proyek *${projectName}* pada akhir pekan ini.\n\nApakah hari Sabtu atau Minggu besok ada waktu luang?\n\nTerima kasih,\n*${agentName}*`;
    } else {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nBagaimana kabar rencana hunian impian Anda di *${projectName}*? Jika ada pertanyaan mengenai promo diskon, suku bunga KPR bank partner, atau ingin berkunjung kembali, saya siap membantu.\n\nSalam,\n*${agentName}*`;
    }
}

function openWaModal(type) {
    generateWaMessage(type);
    const cleanPhone = (props.lead.phone || '').replace(/[^0-9]/g, '');
    const intlPhone = cleanPhone.startsWith('0') ? '62' + cleanPhone.slice(1) : cleanPhone;
    const url = `https://wa.me/${intlPhone}?text=${encodeURIComponent(generatedMessage.value)}`;
    window.open(url, '_blank');
}

// Lead Edit Form
const editForm = useForm({
    name: props.lead.name || '',
    phone: props.lead.phone || '',
    email: props.lead.email || '',
    identity_number: props.lead.identity_number || '',
    npwp: props.lead.npwp || '',
    address: props.lead.address || '',
    job: props.lead.job || '',
    source: props.lead.source || 'walk_in',
    status: props.lead.status || 'new',
    project_id: props.lead.project_id || '',
    broker_company_id: props.lead.broker_company_id || '',
    assigned_to: typeof props.lead.assigned_to === 'object' && props.lead.assigned_to !== null 
        ? props.lead.assigned_to.id 
        : (props.lead.assigned_to_user?.id || props.lead.assigned_to || null),
    notes: props.lead.notes || '',
    preferred_unit_id: props.lead.preferences?.preferred_unit_id || '',
});

function selectUnitForWorkspace(unit) {
    editForm.preferred_unit_id = unit.id;
    editForm.project_id = unit.project_id || editForm.project_id;
    updateLead();
    showUnitPickerModal.value = false;
}

function updateLead() {
    editForm.transform((data) => ({
        ...data,
        preferences: {
            ...(props.lead.preferences || {}),
            preferred_unit_id: data.preferred_unit_id || null,
        }
    })).put(`/leads/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditLeadModal.value = false;
        }
    });
}

// Edit Negotiation
const editNegoForm = useForm({
    unit_id: '',
    client_name: '',
    client_phone: '',
    client_email: '',
    offered_price: '',
    payment_scheme: 'kpr',
    dp_amount: '',
    installment_months: '',
    notes: '',
    status: 'draft',
});

function openEditNegoModal(nego) {
    selectedNego.value = nego;
    editNegoForm.unit_id = nego.unit_id;
    editNegoForm.client_name = nego.client_name || props.lead.name || '';
    editNegoForm.client_phone = nego.client_phone || props.lead.phone || '';
    editNegoForm.client_email = nego.client_email || props.lead.email || '';
    editNegoForm.offered_price = nego.offered_price || '';
    editNegoForm.payment_scheme = nego.payment_scheme || 'kpr';
    editNegoForm.dp_amount = nego.dp_amount || '';
    editNegoForm.installment_months = nego.installment_months || '';
    editNegoForm.notes = nego.notes || '';
    editNegoForm.status = nego.status || 'draft';
    showEditNegoModal.value = true;
}

function submitEditNego() {
    if (!selectedNego.value) return;
    editNegoForm.put(`/negotiations/${selectedNego.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditNegoModal.value = false;
            selectedNego.value = null;
        }
    });
}

// Change Reservation Unit
const changeResUnitForm = useForm({ unit_id: '', reason: '' });
function openChangeResUnitModal(res) {
    selectedRes.value = res;
    changeResUnitForm.unit_id = res.unit_id || '';
    changeResUnitForm.reason = '';
    showChangeResUnitModal.value = true;
}

function submitChangeResUnit() {
    if (!selectedRes.value) return;
    changeResUnitForm.post(`/reservations/${selectedRes.value.id}/change-unit`, {
        preserveScroll: true,
        onSuccess: () => {
            showChangeResUnitModal.value = false;
            selectedRes.value = null;
        }
    });
}
</script>

<template>
    <Head :title="`Workspace: ${lead.name} (${transactionId})`" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/leads" class="text-slate-400 hover:text-blue-600 transition-colors">Leads</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Transaction Workspace</span>
        </template>

        <div class="max-w-7xl mx-auto space-y-6 pb-12">
            <!-- 1. WORKSPACE HEADER: CLIENT, TRANSACTION ID, UNIT, SCORE -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <!-- Single Transaction ID Badge -->
                            <button @click="copyTransactionId" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-mono font-black bg-slate-900 text-blue-300 hover:bg-slate-800 transition-all cursor-pointer shadow-xs" title="Klik untuk salin Transaction ID">
                                <span>🏷️</span>
                                <span>{{ transactionId }}</span>
                                <span class="text-[10px] text-slate-400">📋</span>
                            </button>

                            <!-- Score Badge -->
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-gradient-to-r text-white"
                                :class="lead.score >= 80 ? 'from-emerald-500 to-green-600' : (lead.score >= 50 ? 'from-amber-500 to-orange-500' : 'from-rose-500 to-red-500')">
                                Score {{ lead.score }}% {{ lead.score >= 80 ? '🔥 Hot' : '' }}
                            </span>

                            <!-- Stage Status -->
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-blue-100 text-blue-800">
                                {{ lead.status.replace('_', ' ') }}
                            </span>
                        </div>

                        <div class="flex items-baseline gap-3">
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ lead.name }}</h1>
                            <span class="text-xs text-slate-400 font-medium">📱 {{ lead.phone }}</span>
                        </div>

                        <!-- Key Properties Metadata -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 mt-2 font-medium">
                            <span class="flex items-center gap-1">
                                <span>🏢</span> <strong>{{ lead.project?.name || 'Belum Pilih Proyek' }}</strong>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="flex items-center gap-1">
                                <span>🏠</span> 
                                <strong v-if="primaryUnit" class="text-slate-900">
                                    Blok {{ primaryUnit.block }}{{ primaryUnit.number }} (Tipe {{ primaryUnit.unit_type?.name || 'Standard' }})
                                </strong>
                                <span v-else class="text-slate-400 italic">Belum Memilih Kavling</span>
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-xl border border-slate-200/80">
                                <span>👤</span> Agen: 
                                <strong :class="lead.assigned_to_user ? 'text-slate-900' : 'text-amber-600 font-bold'">
                                    {{ lead.assigned_to_user?.name || 'Belum Ditugaskan' }}
                                </strong>
                                <button type="button" @click="openAssignAgentModal" class="ml-1 px-2 py-0.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-[10px] font-bold transition-all cursor-pointer shadow-xs flex items-center gap-1">
                                    <span>✏️</span> {{ lead.assigned_to_user ? 'Ubah' : 'Tugaskan' }}
                                </button>
                            </span>
                        </div>
                    </div>

                    <!-- Header Quick Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <button @click="openWaModal('perkenalan')" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5 cursor-pointer">
                            <span>💬</span> WhatsApp
                        </button>
                        <a :href="`tel:${lead.phone}`" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                            <span>📞</span> Telepon
                        </a>
                        <button @click="showEditLeadModal = true" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all cursor-pointer">
                            ✏️ Edit Profil
                        </button>
                    </div>
                </div>

                <!-- 2. PIPELINE FUNNEL TRACKER -->
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Progress Transaksi</span>
                        <span class="text-xs font-black text-blue-600">{{ progressPercentage }}% Selesai</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                        <button v-for="(step, idx) in statusSteps" :key="step.key"
                            type="button"
                            @click="changeStatus(step.key)"
                            :class="[
                                currentStepIndex >= idx 
                                    ? (currentStepIndex === idx ? 'bg-blue-600 text-white font-black ring-2 ring-blue-300 shadow-md' : 'bg-blue-50 text-blue-900 border border-blue-200 font-bold') 
                                    : 'bg-slate-50 text-slate-400 hover:bg-slate-100 border border-slate-200/80 font-medium'
                            ]"
                            class="px-2 py-2 rounded-xl text-xs text-left transition-all cursor-pointer flex flex-col justify-between">
                            <div class="flex items-center justify-between text-[10px]">
                                <span>{{ idx + 1 }}</span>
                                <span v-if="currentStepIndex > idx">✓</span>
                                <span v-else-if="currentStepIndex === idx" class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                            </div>
                            <div class="text-[11px] truncate mt-1">{{ step.label }}</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. NEXT ACTION ENGINE (Smart Dynamic Guidance Card) -->
            <div class="p-5 rounded-3xl bg-gradient-to-r text-white shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
                :class="nextAction.color || 'from-blue-600 to-indigo-600'">
                <div class="flex items-start gap-4">
                    <span class="text-3xl p-3 bg-white/10 rounded-2xl shrink-0">{{ nextAction.icon || '🚀' }}</span>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-white/20 text-white">
                                {{ nextAction.stage }}
                            </span>
                        </div>
                        <h2 class="text-lg font-black tracking-tight text-white">{{ nextAction.title }}</h2>
                        <p class="text-xs text-white/80 mt-0.5 max-w-2xl leading-relaxed">{{ nextAction.desc }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0 w-full md:w-auto">
                    <button type="button" @click="handleNextAction(nextAction.primaryAction, nextAction.actionUrl)"
                        class="w-full md:w-auto px-5 py-3 bg-white text-slate-900 hover:bg-slate-100 rounded-xl text-xs font-black shadow-md transition-all active:scale-95 cursor-pointer flex items-center justify-center gap-1.5">
                        {{ nextAction.actionText }}
                    </button>
                    <Link v-if="latestBooking && (lead.status === 'booking' || lead.status === 'won')" :href="`/bookings/${latestBooking.id}`"
                        class="w-full md:w-auto px-4 py-3 bg-white/20 hover:bg-white/30 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                        <span>⚙️</span> Atur Skema & Jadwal
                    </Link>
                    <a v-if="latestBooking && (lead.status === 'booking' || lead.status === 'won')" :href="`/bookings/${latestBooking.id}/spk`" target="_blank"
                        class="w-full md:w-auto px-4 py-3 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                        <span>📥</span> Unduh PDF SPR
                    </a>
                    <button v-if="nextAction.secondaryAction" type="button" @click="handleNextAction(nextAction.secondaryAction, nextAction.secondaryUrl)"
                        class="w-full md:w-auto px-4 py-3 bg-white/15 hover:bg-white/25 text-white rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center justify-center gap-1.5">
                        {{ nextAction.secondaryText }}
                    </button>
                </div>
            </div>

            <!-- 4. WORKSPACE NAVIGATION TABS -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                <button type="button" @click="activeTab = 'workspace'"
                    :class="activeTab === 'workspace' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0 cursor-pointer">
                    <span>📋</span> <span>Tahapan Transaksi</span>
                </button>
                <button type="button" @click="activeTab = 'timeline'"
                    :class="activeTab === 'timeline' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0 cursor-pointer">
                    <span>⏱️</span> <span>Unified Timeline ({{ unifiedTimeline.length }})</span>
                </button>
                <button type="button" @click="activeTab = 'documents'"
                    :class="activeTab === 'documents' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0 cursor-pointer">
                    <span>📁</span> <span>Document Center ({{ documentList.length }})</span>
                </button>
                <button type="button" @click="activeTab = 'client'"
                    :class="activeTab === 'client' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
                    class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0 cursor-pointer">
                    <span>👤</span> <span>Data Konsumen & Pemesan 2</span>
                </button>
            </div>

            <!-- 5. MAIN CONTENT LAYOUT: WORKSPACE (LEFT 2 COLS) + TRANSACTION SUMMARY (RIGHT 1 COL) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- LEFT CONTENT AREA -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- TAB 1: WORKSPACE / TAHAPAN TRANSAKSI -->
                    <div v-if="activeTab === 'workspace'" class="space-y-6">
                        <!-- UNIT SELECTION CARD -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>🏠</span> Kavling Unit Properti
                                </h3>
                                <button type="button" @click="showUnitPickerModal = true" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-black transition-all cursor-pointer flex items-center gap-1">
                                    <span>🎯</span> <span>Pilih / Ganti Kavling Unit</span>
                                </button>
                            </div>

                            <div v-if="primaryUnit" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg font-black text-slate-900">Blok {{ primaryUnit.block }}{{ primaryUnit.number }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase" :class="primaryUnit.status === 'booked' ? 'bg-blue-100 text-blue-800' : (primaryUnit.status === 'hold' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')">
                                            {{ primaryUnit.status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ lead.project?.name }} · Tipe {{ primaryUnit.unit_type?.name }} (LT {{ primaryUnit.unit_type?.land_area || 0 }}m² / LB {{ primaryUnit.unit_type?.building_area || 0 }}m²)
                                    </p>
                                </div>

                                <div class="text-left sm:text-right">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Harga Brosur Unit</p>
                                    <p class="text-base font-black text-slate-900">{{ formatCurrency(brochurePrice) }}</p>
                                </div>
                            </div>
                            <div v-else class="p-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl text-center">
                                <p class="text-xs text-slate-500 mb-2">Konsumen ini belum memilih kavling unit.</p>
                                <button type="button" @click="showUnitPickerModal = true" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                                    + Pilih Kavling dari Site Plan / Daftar Unit
                                </button>
                            </div>
                        </div>

                        <!-- STAGE: NEGOTIATION -->
                        <div class="bg-white rounded-2xl border border-amber-200 p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs">🤝</span>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Tahap Negosiasi Harga</h3>
                                </div>
                                <button type="button" @click="showNegoModal = true" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold shadow-xs cursor-pointer">
                                    + Form Negosiasi Baru
                                </button>
                            </div>

                            <div v-if="lead.negotiations?.length" class="space-y-3">
                                <div v-for="neg in lead.negotiations" :key="neg.id" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900">#{{ neg.negotiation_number || neg.token }}</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="neg.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                                {{ neg.status }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Penawaran: <strong class="text-emerald-700">{{ formatCurrency(neg.offered_price || neg.counter_price) }}</strong>
                                            · Skema: <span class="uppercase font-semibold">{{ neg.payment_scheme }}</span>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Link :href="`/negotiations/${neg.id}`" class="px-2.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 rounded-lg text-[11px] font-bold text-blue-600">
                                            Detail →
                                        </Link>
                                        <a :href="`/n/${neg.token}`" target="_blank" class="px-2.5 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100 rounded-lg text-[11px] font-bold">
                                            Link Konsumen
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic p-3 bg-slate-50 rounded-xl">
                                Belum ada pengajuan negosiasi harga.
                            </div>
                        </div>

                        <!-- STAGE: RESERVATION (UTJ) -->
                        <div class="bg-white rounded-2xl border border-teal-200 p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center text-xs">🔖</span>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Tahap Reservasi Unit (Hold Unit)</h3>
                                </div>
                                <Link :href="`/reservations/create?lead_id=${lead.id}`" class="px-3 py-1 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-bold shadow-xs">
                                    + Buat Reservasi
                                </Link>
                            </div>

                            <div v-if="lead.reservations?.length" class="space-y-3">
                                <div v-for="res in lead.reservations" :key="res.id" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-900">#{{ res.reservation_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-teal-100 text-teal-800">
                                                {{ res.status }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Unit: <strong>{{ res.unit?.code || '-' }}</strong> · Uang Tanda Jadi: <strong class="text-emerald-700">{{ formatCurrency(res.amount) }}</strong>
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <Link :href="`/reservations/${res.id}`" class="px-2.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-100 rounded-lg text-[11px] font-bold text-blue-600">
                                            Detail →
                                        </Link>
                                        <a :href="`/reservations/${res.id}/receipt`" target="_blank" class="px-2.5 py-1.5 bg-slate-900 text-white hover:bg-slate-800 rounded-lg text-[11px] font-bold">
                                            📄 Kwitansi UTJ
                                        </a>
                                        <button v-if="res.status === 'active'" type="button" @click="openChangeResUnitModal(res)" class="px-2 py-1.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-lg text-[11px] font-bold">
                                            🔄 Pindah Unit
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic p-3 bg-slate-50 rounded-xl">
                                Belum ada data reservasi kavling.
                            </div>
                        </div>

                        <!-- STAGE: BOOKING & SPR -->
                        <div class="bg-white rounded-2xl border border-blue-200 p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-xs">📄</span>
                                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Tahap Booking & Surat Pesanan Rumah (SPR)</h3>
                                </div>
                                <Link :href="`/bookings/create?lead_id=${lead.id}`" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-xs">
                                    + Formulir Booking Baru
                                </Link>
                            </div>

                            <div v-if="lead.bookings?.length" class="space-y-4">
                                <div v-for="book in lead.bookings" :key="book.id" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-sm text-slate-900">SPR #{{ book.spk_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="book.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                                {{ book.status }}
                                            </span>
                                        </div>
                                        <Link :href="`/bookings/${book.id}`" class="text-xs font-bold text-blue-600 hover:underline">
                                            Kelola Booking & Pembayaran →
                                        </Link>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                        <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">Harga Deal SPR</p>
                                            <p class="font-black text-slate-900 mt-0.5">{{ formatCurrency(book.final_price) }}</p>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">Booking Fee (UTJ)</p>
                                            <p class="font-black text-emerald-700 mt-0.5">{{ formatCurrency(book.booking_fee) }}</p>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">Skema Bayar</p>
                                            <p class="font-black text-slate-900 uppercase mt-0.5">{{ book.payment_scheme }}</p>
                                        </div>
                                        <div class="bg-white p-2.5 rounded-xl border border-slate-200">
                                            <p class="text-[10px] text-slate-400 font-bold uppercase">TTD Digital SPR</p>
                                            <p class="font-black mt-0.5" :class="book.customer_signed_at ? 'text-emerald-700' : 'text-amber-600'">
                                                {{ book.customer_signed_at ? '✓ Lengkap' : 'Menunggu' }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200">
                                        <button type="button" @click="openSprPreview" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-xs">
                                            <span>👁️</span> Tinjau Dokumen SPR
                                        </button>
                                        <Link :href="`/bookings/${book.id}`" class="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100 rounded-xl text-xs font-bold transition-all flex items-center gap-1">
                                            <span>⚙️</span> Atur Skema & Jadwal
                                        </Link>
                                        <a :href="`/bookings/${book.id}/spk`" target="_blank" class="px-3 py-1.5 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-all flex items-center gap-1">
                                            <span>📥</span> Unduh PDF SPR
                                        </a>
                                        <a :href="`/booking-tracking/${book.tracking_token}`" target="_blank" class="px-3 py-1.5 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl text-xs font-bold hover:bg-blue-100 transition-all flex items-center gap-1">
                                            <span>🖋️</span> Link TTD Digital
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic p-3 bg-slate-50 rounded-xl">
                                Belum ada dokumen Booking / SPR yang dibuat.
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: UNIFIED CHRONOLOGICAL TIMELINE -->
                    <div v-if="activeTab === 'timeline'" class="space-y-6">
                        <!-- Add Activity Box -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-3">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Catat Aktivitas Kontak</h3>
                            <form @submit.prevent="submitActivity" class="space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="t in ['call','whatsapp','email','visit','meeting','note']" :key="t" type="button"
                                        @click="activityForm.type = t"
                                        :class="activityForm.type === t ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all capitalize cursor-pointer">
                                        {{ activityIcons[t] }} {{ t }}
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    <input v-model="activityForm.description" type="text" placeholder="Deskripsi aktivitas atau hasil komunikasi..." class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20" />
                                    <button type="submit" :disabled="activityForm.processing" class="px-5 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50 cursor-pointer">Kirim</button>
                                </div>
                            </form>
                        </div>

                        <!-- Chronological Stream -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Linimasa Lengkap Transaksi</h3>
                            <div v-if="unifiedTimeline.length" class="space-y-4 relative before:absolute before:inset-0 before:left-3.5 before:w-0.5 before:bg-slate-100">
                                <div v-for="item in unifiedTimeline" :key="item.id" class="flex items-start gap-3 relative">
                                    <div class="w-7 h-7 rounded-full bg-white border-2 border-slate-200 flex items-center justify-center text-xs shrink-0 z-10">
                                        {{ item.icon }}
                                    </div>
                                    <div class="flex-1 p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="item.color">
                                                {{ item.badge }}
                                            </span>
                                            <span class="text-[10px] text-slate-400">{{ formatDate(item.date) }}</span>
                                        </div>
                                        <p class="font-bold text-slate-900">{{ item.title }}</p>
                                        <p v-if="item.subtitle" class="text-[11px] text-slate-500 mt-0.5">{{ item.subtitle }}</p>
                                        <Link v-if="item.link" :href="item.link" class="text-[10px] font-bold text-blue-600 hover:underline mt-1 inline-block">
                                            Buka Detail →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-xs text-slate-400 italic">Belum ada riwayat aktivitas.</p>
                        </div>
                    </div>

                    <!-- TAB 3: DOCUMENT CENTER -->
                    <div v-if="activeTab === 'documents'" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>📁</span> Document Center
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Semua berkas resmi yang dihasilkan dari transaksi lead ini terkumpul otomatis di sini.</p>
                            </div>
                        </div>

                        <div v-if="documentList.length" class="divide-y divide-slate-100">
                            <div v-for="(doc, idx) in documentList" :key="idx" class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-sm text-slate-900">{{ doc.title }}</span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="doc.statusColor">
                                            {{ doc.status }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 mt-0.5">
                                        Kategori: <strong>{{ doc.category }}</strong> · Unit: <strong>{{ doc.unit }}</strong> · Tanggal: {{ formatDate(doc.date) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <template v-for="(act, aIdx) in doc.actions" :key="aIdx">
                                        <button v-if="act.action === 'preview_spr'" type="button" @click="openSprPreview"
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-all cursor-pointer shadow-2xs">
                                            {{ act.label }}
                                        </button>
                                        <a v-else :href="act.url" :target="act.blank ? '_blank' : '_self'"
                                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-lg text-xs font-bold transition-all">
                                            {{ act.label }}
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-slate-400 italic p-6 text-center bg-slate-50 rounded-xl">
                            Belum ada dokumen transaksi yang diterbitkan untuk lead ini.
                        </div>
                    </div>

                    <!-- TAB 4: CLIENT PROFILE & SECONDARY BUYER -->
                    <div v-if="activeTab === 'client'" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                <span>👤</span> Identitas Konsumen & Pemesan 2
                            </h3>
                            <button @click="showEditLeadModal = true" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-xl text-xs font-bold">
                                ✏️ Edit Data Konsumen
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">NIK / No. KTP</p>
                                <p class="font-black text-slate-900 mt-0.5">{{ lead.identity_number || latestBooking?.buyer_nik || '-' }}</p>
                            </div>
                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">NPWP</p>
                                <p class="font-black text-slate-900 mt-0.5">{{ lead.npwp || latestBooking?.buyer_npwp || '-' }}</p>
                            </div>
                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Pekerjaan</p>
                                <p class="font-black text-slate-900 mt-0.5">{{ lead.job || latestBooking?.buyer_job || '-' }}</p>
                            </div>
                            <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Alamat KTP</p>
                                <p class="font-black text-slate-900 mt-0.5">{{ lead.address || latestBooking?.buyer_address || '-' }}</p>
                            </div>
                        </div>

                        <!-- Pemesan 2 -->
                        <div v-if="latestBooking?.secondary_name" class="p-4 bg-amber-50/50 border border-amber-200 rounded-2xl space-y-2 text-xs">
                            <h4 class="font-black text-slate-900 uppercase text-[11px]">Pemesan 2 / Penanggung Jawab</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>Nama: <strong>{{ latestBooking.secondary_name }}</strong></div>
                                <div>Hubungan: <strong>{{ latestBooking.secondary_relationship }}</strong></div>
                                <div>No. KTP 2: <strong>{{ latestBooking.secondary_nik || '-' }}</strong></div>
                                <div>No. Telp 2: <strong>{{ latestBooking.secondary_phone || '-' }}</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDEBAR: REALTIME TRANSACTION SUMMARY & QUICK ACTION -->
                <div class="space-y-6">
                    <!-- REALTIME TRANSACTION SUMMARY CARD -->
                    <div class="bg-slate-900 text-white rounded-3xl p-6 shadow-xl space-y-5">
                        <div class="flex items-center justify-between pb-3 border-b border-white/10">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400">Ringkasan Transaksi</h3>
                            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white/10 text-slate-300">
                                {{ transactionId }}
                            </span>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Calon Pembeli</p>
                                <p class="font-black text-sm text-white mt-0.5">{{ lead.name }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-white/10">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Proyek</p>
                                    <p class="font-bold text-white mt-0.5">{{ lead.project?.name || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Kavling Unit</p>
                                    <p class="font-bold text-blue-400 mt-0.5">{{ primaryUnit ? `Blok ${primaryUnit.block}${primaryUnit.number}` : '-' }}</p>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-white/10 space-y-1.5">
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400">Harga Brosur:</span>
                                    <span class="font-bold text-slate-300">{{ formatCurrency(brochurePrice) }}</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-blue-300 font-bold">Harga Deal Akhir:</span>
                                    <span class="font-black text-blue-400 text-sm">{{ formatCurrency(agreedDealPrice) }}</span>
                                </div>
                                <div v-if="discountSavings > 0" class="flex justify-between text-xs text-emerald-400 font-semibold">
                                    <span>Hemat / Diskon:</span>
                                    <span>-{{ formatCurrency(discountSavings) }}</span>
                                </div>
                                <div class="flex justify-between text-xs pt-1 border-t border-white/5">
                                    <span class="text-slate-400">Booking Fee (UTJ):</span>
                                    <span class="font-bold text-emerald-400">{{ formatCurrency(bookingFeeAmount) }}</span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-white/10 flex justify-between items-center text-xs">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Status Transaksi</p>
                                    <p class="font-black uppercase text-amber-400 mt-0.5">{{ lead.status.replace('_', ' ') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Konsultan / Agen</p>
                                    <div class="flex items-center justify-end gap-1.5 mt-0.5">
                                        <p class="font-bold text-slate-200">{{ lead.assigned_to_user?.name || 'Belum Ditugaskan' }}</p>
                                        <button type="button" @click="openAssignAgentModal" class="text-[10px] text-blue-400 hover:text-blue-300 font-bold underline cursor-pointer">
                                            ({{ lead.assigned_to_user ? 'Ubah' : 'Tugaskan' }})
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Shortcut Buttons -->
                        <div class="pt-4 border-t border-white/10 space-y-2">
                            <button type="button" @click="showKprModal = true" class="w-full py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>🧮</span> Kalkulator Simulasi KPR
                            </button>
                            <button type="button" @click="showReminderModal = true" class="w-full py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>⏰</span> Set Pengingat Follow-Up
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: UNIT PICKER FROM ACTIVE INVENTORY -->
        <teleport to="body">
            <div v-if="showUnitPickerModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showUnitPickerModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>🎯</span> Pilih Kavling Unit untuk Transaksi Ini
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Pilih dari master stok unit aktif {{ lead.project?.name || '' }}</p>
                        </div>
                        <button @click="showUnitPickerModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                    </div>

                    <div class="overflow-y-auto py-4 space-y-2 flex-1">
                        <div v-for="u in units" :key="u.id"
                            @click="selectUnitForWorkspace(u)"
                            class="p-3.5 rounded-2xl border border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 transition-all cursor-pointer flex items-center justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-sm text-slate-900">Blok {{ u.block }}{{ u.number }}</span>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" :class="u.status === 'available' ? 'bg-emerald-100 text-emerald-800' : (u.status === 'hold' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600')">
                                        {{ u.status }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Tipe {{ u.unit_type?.name }} · LT {{ u.unit_type?.land_area || 0 }}m² / LB {{ u.unit_type?.building_area || 0 }}m²
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Harga Brosur</p>
                                <p class="font-black text-sm text-blue-900">{{ formatCurrency(u.final_price || u.price) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-right">
                        <button type="button" @click="showUnitPickerModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-bold rounded-xl">Batal</button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- MODAL: GENERATE NEGOSIASI (KOMPONEN RESMI) -->
        <NegotiationTemplateModal
            :is-open="showNegoModal"
            :lead="lead"
            :units="units"
            @close="showNegoModal = false"
            @created="showNegoModal = false"
        />

        <!-- MODAL: EDIT LEAD -->
        <teleport to="body">
            <div v-if="showEditLeadModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showEditLeadModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h2 class="text-base font-black text-slate-900">Edit Profil & Data Konsumen</h2>
                        <button @click="showEditLeadModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <form @submit.prevent="updateLead" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap</label>
                            <input v-model="editForm.name" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-xl" required />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">No. WhatsApp/HP</label>
                                <input v-model="editForm.phone" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-xl" required />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Email</label>
                                <input v-model="editForm.email" type="email" class="w-full px-3 py-2 border border-slate-200 rounded-xl" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">NIK (KTP)</label>
                                <input v-model="editForm.identity_number" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-xl" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">NPWP</label>
                                <input v-model="editForm.npwp" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-xl" />
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Pekerjaan</label>
                            <input v-model="editForm.job" type="text" class="w-full px-3 py-2 border border-slate-200 rounded-xl" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Alamat KTP</label>
                            <textarea v-model="editForm.address" rows="2" class="w-full px-3 py-2 border border-slate-200 rounded-xl resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Tugaskan Agen / Sales</label>
                                <select v-model="editForm.assigned_to" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                    <option :value="null">-- Belum Ditugaskan --</option>
                                    <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                        {{ agent.name }} ({{ agent.agent_type || 'Internal' }}{{ agent.broker_company ? ' - ' + agent.broker_company.name : '' }})
                                    </option>
                                </select>
                            </div>
                            <div v-if="brokerCompanies && brokerCompanies.length > 0">
                                <label class="block font-bold text-slate-700 mb-1">Kantor Agensi / Broker</label>
                                <select v-model="editForm.broker_company_id" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-medium bg-slate-50 focus:bg-white text-slate-800">
                                    <option value="">-- Tanpa Agensi / In-House --</option>
                                    <option v-for="bc in brokerCompanies" :key="bc.id" :value="bc.id">
                                        {{ bc.name }} ({{ bc.code }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showEditLeadModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-black">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- MODAL: TUGASKAN AGEN -->
        <teleport to="body">
            <div v-if="showAssignAgentModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAssignAgentModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>👤</span> Tugaskan Agen / Sales Properti
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Tentukan konsultan atau broker penanggung jawab untuk {{ lead.name }}</p>
                        </div>
                        <button @click="showAssignAgentModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
                    </div>

                    <form @submit.prevent="submitAssignAgent" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Pilih Agen / Sales <span class="text-rose-500">*</span></label>
                            <select v-model="assignAgentForm.assigned_to" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800 focus:ring-2 focus:ring-blue-500/20">
                                <option value="">-- Belum Ditugaskan / Hapus Penugasan --</option>
                                <option v-for="agent in agents" :key="agent.id" :value="agent.id">
                                    {{ agent.name }} ({{ agent.agent_type || 'Internal' }}{{ agent.broker_company ? ' - ' + agent.broker_company.name : '' }})
                                </option>
                            </select>
                        </div>

                        <div v-if="brokerCompanies && brokerCompanies.length > 0">
                            <label class="block font-bold text-slate-700 mb-1">Kantor Agensi / Broker (Opsional)</label>
                            <select v-model="assignAgentForm.broker_company_id" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-medium bg-slate-50 focus:bg-white text-slate-800 focus:ring-2 focus:ring-blue-500/20">
                                <option value="">-- Tanpa Agensi / Agen In-House Developer --</option>
                                <option v-for="bc in brokerCompanies" :key="bc.id" :value="bc.id">
                                    {{ bc.name }} ({{ bc.code }})
                                </option>
                            </select>
                        </div>

                        <div class="p-3 bg-blue-50 border border-blue-100 rounded-2xl text-[11px] text-blue-700 leading-relaxed">
                            💡 Agen yang ditugaskan akan otomatis dicatat sebagai penanggung jawab lead, tampil di laporan performa tim, dan dihubungkan pada Surat Pemesanan Rumah (SPR).
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showAssignAgentModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                            <button type="submit" :disabled="assignAgentForm.processing" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black shadow-md shadow-blue-500/20">
                                {{ assignAgentForm.processing ? 'Menyimpan...' : 'Simpan Penugasan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- MODAL: PRATINJAU DOKUMEN SPR -->
        <teleport to="body">
            <div v-if="showSprPreviewModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSprPreviewModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col p-6 md:p-8">
                    <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 shrink-0">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Pratinjau Surat Pemesanan Rumah (SPR)</h3>
                            <p class="text-[10px] text-slate-400">Pratinjau dokumen resmi pemesanan unit kavling {{ primaryUnit?.block }}{{ primaryUnit?.number }} - {{ lead.name }}</p>
                        </div>
                        <button @click="showSprPreviewModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 font-bold text-lg">&times;</button>
                    </div>

                    <!-- Live Document Stream Iframe -->
                    <div class="flex-1 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-inner">
                        <iframe v-if="latestBooking" :src="`/bookings/${latestBooking.id}/spk/view?html=1&_t=${sprPreviewTimestamp}`" class="w-full h-full min-h-[500px] border-0 rounded-2xl"></iframe>
                        <div v-else class="p-12 text-center text-slate-400 text-xs font-semibold">
                            Data transaksi booking belum dibuat.
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-3 shrink-0 pt-2">
                        <Link v-if="latestBooking" :href="`/bookings/${latestBooking.id}`" class="px-4 py-2.5 bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
                            <span>⚙️</span> Atur Skema & Termin Pembayaran
                        </Link>
                        <div v-else></div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showSprPreviewModal = false" class="px-5 py-2.5 text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">Tutup</button>
                            <a v-if="latestBooking" :href="`/bookings/${latestBooking.id}/spk`" target="_blank" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-slate-800 transition-all flex items-center gap-2">
                                📥 Download PDF Resmi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- MODAL: SET REMINDER -->
        <teleport to="body">
            <div v-if="showReminderModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showReminderModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <span>⏰</span> Set Pengingat Follow-Up
                        </h2>
                        <button @click="showReminderModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <form @submit.prevent="submitReminder" class="space-y-3 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Waktu Pengingat</label>
                            <input v-model="reminderForm.remind_at" type="datetime-local" class="w-full px-3 py-2 border border-slate-200 rounded-xl" required />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Pesan / Agenda Follow Up</label>
                            <textarea v-model="reminderForm.message" rows="3" placeholder="Contoh: Telepon untuk konfirmasi jadwal survey hari Sabtu..." class="w-full px-3 py-2 border border-slate-200 rounded-xl resize-none" required></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showReminderModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                            <button type="submit" :disabled="reminderForm.processing" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-black">Pasang Pengingat</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- CHANGE RESERVATION UNIT MODAL -->
        <teleport to="body">
            <div v-if="showChangeResUnitModal && selectedRes" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showChangeResUnitModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>🔄</span> Pindah / Ganti Unit Reservasi
                            </h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Reservasi #{{ selectedRes.reservation_number }} ({{ lead.name }})</p>
                        </div>
                        <button @click="showChangeResUnitModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                    </div>

                    <form @submit.prevent="submitChangeResUnit" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">
                                Pilih Unit Baru (Tersedia) <span class="text-rose-500">*</span>
                            </label>
                            <select v-model="changeResUnitForm.unit_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="" disabled>-- Pilih Unit Pengganti --</option>
                                <option v-for="u in units" :key="u.id" :value="u.id" :disabled="u.status !== 'available' && u.id !== selectedRes.unit_id">
                                    {{ u.block }} {{ u.number }} - {{ u.unit_type?.name || 'Standard' }} (Rp {{ Number(u.final_price || u.price || 0).toLocaleString('id-ID') }}) [{{ u.status }}]
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Pindah Unit (Opsional)</label>
                            <textarea v-model="changeResUnitForm.reason" rows="2" placeholder="Alasan customer meminta ganti unit..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500/20 resize-none"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showChangeResUnitModal = false" class="px-5 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Batal</button>
                            <button type="submit" :disabled="changeResUnitForm.processing" class="px-6 py-2.5 bg-indigo-600 text-white text-xs font-black rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-50">
                                🔄 Konfirmasi Pindah Unit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <KprCalculatorModal :show="showKprModal" @close="showKprModal = false" />
    </CrmLayout>
</template>
