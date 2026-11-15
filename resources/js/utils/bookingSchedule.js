/**
 * Helper terpadu perhitungan simulasi & termin jadwal pembayaran Booking/SPR.
 * Logika di file ini dibuat 100% identik dengan BookingController::generateSchedules()
 * dan resources/views/pdf/spr.blade.php agar angka di UI Create, Detail Booking,
 * Database PaymentSchedule, SPR, dan SPK selalu sama persis.
 */

export function addMonthsIso(baseDateStr, months) {
    const base = baseDateStr ? new Date(`${baseDateStr}T00:00:00`) : new Date();
    if (Number.isNaN(base.getTime())) return '';
    const d = new Date(base.getTime());
    const targetMonth = d.getMonth() + months;
    d.setMonth(targetMonth);
    return d.toISOString().slice(0, 10);
}

export function addDaysIso(baseDateStr, days) {
    const base = baseDateStr ? new Date(`${baseDateStr}T00:00:00`) : new Date();
    if (Number.isNaN(base.getTime())) return '';
    const d = new Date(base.getTime());
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export function createDefaultCustomTerms(remainingAmount, baseDateStr = '') {
    const total = Math.max(0, Math.round(Number(remainingAmount) || 0));
    if (total <= 0) {
        return [{ label: 'DP 1', amount: 0, due_date: addMonthsIso(baseDateStr, 1) }];
    }
    const dp1 = Math.round(total * 0.3);
    const dp2 = Math.round(total * 0.4);
    const dp3 = Math.max(0, total - dp1 - dp2);
    return [
        { label: 'DP 1', amount: dp1, due_date: addMonthsIso(baseDateStr, 1) },
        { label: 'DP 2', amount: dp2, due_date: addMonthsIso(baseDateStr, 2) },
        { label: 'DP 3 (Pelunasan)', amount: dp3, due_date: addMonthsIso(baseDateStr, 3) },
    ];
}

export function buildScheduleSimulation(input = {}) {
    const basePrice = Math.max(0, Math.round(Number(input.base_price) || 0));
    const finalPriceRaw = Math.max(0, Math.round(Number(input.final_price) || 0));
    const ppn = Math.max(0, Math.round(Number(input.ppn_amount) || 0));
    const bphtb = Math.max(0, Math.round(Number(input.bphtb_amount) || 0));
    const ajb = Math.max(0, Math.round(Number(input.ajb_bbn_amount) || 0));
    const otherLegal = Math.max(0, Math.round(Number(input.other_legal_fees) || 0));
    const taxLegalTotal = ppn + bphtb + ajb + otherLegal;

    const finalPrice = finalPriceRaw > 0 ? finalPriceRaw : (basePrice + taxLegalTotal);
    const bookingFee = Math.max(0, Math.round(Number(input.booking_fee) || 0));
    const unitTargetPrice = (taxLegalTotal > 0 && finalPrice > basePrice && basePrice > 0)
        ? basePrice
        : (finalPrice > 0 ? finalPrice : basePrice);
    const unitAfterUtj = Math.max(0, unitTargetPrice - bookingFee);
    const bookingDate = input.booking_date || new Date().toISOString().slice(0, 10);

    const scheme = input.payment_scheme || 'kpr';
    const scheduleMode = (scheme === 'cash_installment' && input.schedule_mode === 'custom_terms')
        ? 'custom_terms'
        : 'standard';

    const rows = [];

    if (unitTargetPrice <= 0 && bookingFee <= 0 && (!Array.isArray(input.custom_schedules) || input.custom_schedules.length === 0)) {
        return {
            rows: [],
            unitTargetPrice: 0,
            unitAfterUtj: 0,
            taxLegalTotal: 0,
            finalPrice: 0,
            customTermsTotal: 0,
            customDiff: 0,
            totalScheduled: 0,
        };
    }

    rows.push({
        label: 'Booking Fee (UTJ)',
        tag: 'Tanda Jadi',
        amount: bookingFee,
        due_date: bookingDate,
        kind: 'utj',
    });

    let customTermsTotal = 0;

    if (scheme === 'cash') {
        rows.push({
            label: 'Pelunasan Cash Keras',
            tag: 'Maks. 14 Hari',
            amount: unitAfterUtj,
            due_date: addDaysIso(bookingDate, 14),
            kind: 'installment',
        });
    } else if (scheme === 'kpr') {
        const dpInput = Math.max(0, Math.round(Number(input.dp_amount) || 0));
        const dpTotal = dpInput > 0 ? dpInput : Math.round((basePrice || unitTargetPrice) * 0.10);
        const dpMonthsRaw = parseInt(input.dp_installment_months, 10);
        const dpTenor = dpMonthsRaw > 0 ? dpMonthsRaw : 3;
        const dpPerMonth = Math.round(dpTotal / dpTenor);

        for (let i = 1; i <= dpTenor; i++) {
            const amt = i === dpTenor ? (dpTotal - dpPerMonth * (dpTenor - 1)) : dpPerMonth;
            rows.push({
                label: dpTenor > 1 ? `DP ${i}` : 'DP 1',
                tag: `Bulan ke-${i}`,
                amount: Math.max(0, amt),
                due_date: addMonthsIso(bookingDate, i),
                kind: 'dp',
            });
        }

        const kprAmount = Math.max(0, unitAfterUtj - dpTotal);
        rows.push({
            label: 'Pencairan KPR (Bank)',
            tag: 'Saat Akad Kredit',
            amount: kprAmount,
            due_date: addMonthsIso(bookingDate, dpTenor + 1),
            kind: 'kpr',
        });
    } else if (scheduleMode === 'custom_terms') {
        const terms = Array.isArray(input.custom_schedules) ? input.custom_schedules : [];
        terms.forEach((term, idx) => {
            const amt = Math.max(0, Math.round(Number(term?.amount) || 0));
            customTermsTotal += amt;
            const dueDate = term?.due_date || addMonthsIso(bookingDate, idx + 1);
            rows.push({
                label: (term?.label && String(term.label).trim()) ? String(term.label).trim() : `DP ${idx + 1}`,
                tag: term?.due_date ? dueDate : `Bulan ke-${idx + 1}`,
                amount: amt,
                due_date: dueDate,
                kind: 'custom',
            });
        });
    } else {
        // cash_installment standard mode
        const dpTotal = Math.max(0, Math.round(Number(input.dp_amount) || 0));
        const dpMonthsRaw = parseInt(input.dp_installment_months, 10);
        const dpTenor = dpTotal > 0 ? (dpMonthsRaw > 0 ? dpMonthsRaw : 1) : 0;

        if (dpTotal > 0) {
            const dpPerMonth = Math.round(dpTotal / dpTenor);
            for (let d = 1; d <= dpTenor; d++) {
                const amt = d === dpTenor ? (dpTotal - dpPerMonth * (dpTenor - 1)) : dpPerMonth;
                rows.push({
                    label: dpTenor > 1 ? `DP ${d}` : 'DP 1',
                    tag: `Bulan ke-${d}`,
                    amount: Math.max(0, amt),
                    due_date: addMonthsIso(bookingDate, d),
                    kind: 'dp',
                });
            }
        }

        const leftover = Math.max(0, unitAfterUtj - dpTotal);
        const rawInst = input.installment_months;
        const hasExplicitInst = rawInst !== '' && rawInst !== null && rawInst !== undefined;
        const instMonths = hasExplicitInst
            ? Math.max(0, parseInt(rawInst, 10) || 0)
            : (dpTotal > 0 ? 0 : 12);

        if (instMonths > 0 && leftover > 0) {
            const perMonth = Math.round(leftover / instMonths);
            for (let m = 1; m <= instMonths; m++) {
                const amt = m === instMonths ? (leftover - perMonth * (instMonths - 1)) : perMonth;
                rows.push({
                    label: `Cicilan Ke-${m} (dari ${instMonths} Bulan)`,
                    tag: `Bulan ke-${dpTenor + m}`,
                    amount: Math.max(0, amt),
                    due_date: addMonthsIso(bookingDate, dpTenor + m),
                    kind: 'installment',
                });
            }
        } else if (instMonths === 0 && leftover > 0) {
            rows.push({
                label: 'Pelunasan Sisa Cash Bertahap',
                tag: `Bulan ke-${dpTenor + 1}`,
                amount: leftover,
                due_date: addMonthsIso(bookingDate, dpTenor + 1),
                kind: 'installment',
            });
        }
    }

    if (taxLegalTotal > 0) {
        rows.push({
            label: 'Pajak & Biaya Legal (PPN, BPHTB, AJB)',
            tag: 'Maks. 30 Hari',
            amount: taxLegalTotal,
            due_date: addDaysIso(bookingDate, 30),
            kind: 'tax',
        });
    }

    const totalScheduled = rows.reduce((sum, r) => sum + (Number(r.amount) || 0), 0);

    return {
        rows,
        unitTargetPrice,
        unitAfterUtj,
        taxLegalTotal,
        finalPrice,
        customTermsTotal,
        customDiff: unitAfterUtj - customTermsTotal,
        totalScheduled,
    };
}

