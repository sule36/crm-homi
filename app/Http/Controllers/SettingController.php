<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private function getDefaultSignatures(): array
    {
        return [
            'city' => 'Jakarta Selatan',
            'sig1_title' => 'Sales Manager',
            'sig1_name' => 'Dhany Nur',
            'sig1_image' => null,
            'sig2_title' => 'Direktur',
            'sig2_name' => 'Luhur Wira Pramudya',
            'sig2_image' => null,
            'sig3_title' => 'Pemesan Utama',
            'sig3_name' => '',
            'sig3_image' => null,
            'sig4_title' => 'Penanggung Jawab',
            'sig4_name' => '',
            'sig4_image' => null,
        ];
    }

    public function index(Request $request)
    {
        $settingsRaw = Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = Setting::get($s->key);
        }

        // Provide defaults for SPR if not present
        if (!isset($settings['spr_terms_conditions']) || !is_array($settings['spr_terms_conditions'])) {
            $settings['spr_terms_conditions'] = [
                "Pembeli menyatakan telah mengerti dan menyetujui serta akan tunduk kepada persyaratan dan ketentuan serta kebijakan yang ditetapkan oleh Pengembang dalam SPR",
                "Dalam hal pembelian rumah melalui KPR, jumlah DP dan persyaratan KPR lainnya tunduk pada ketentuan Bank pemberi KPR",
                "Dalam hal terjadi penolakan dari pihak Bank atau KPR tidak disetujui, maka Uang Tanda Jadi akan dikembalikan 100%",
                "Dalam hal KPR yang telah disetujui oleh Bank, maka Akad Kredit wajib dilaksanakan selambat-lambatnya 1 bulan sejak diterimanya down payment oleh Pengembang",
                "Dalam hal terjadi pembatalan sepihak oleh pembeli dalam masa pembangunan unit sesuai pilihan pembeli dalam SPR ini, maka seluruh pembayaran dari Pembeli akan hangus 100%",
                "Pembeli diperkenankan untuk memilih cara pembayaran selain KPR dengan syarat dan ketentuan dari Pengembang",
                "Pembayaran segala bentuk cicilan kepada Pengembang yang melebihi waktu yang telah ditentukan dalam SPR ini, akan dikenakan denda sebesar 1% per hari dengan denda maksimal 5% dari jumlah kewajiban yang terlambat",
                "Pembeli tidak diperkenankan untuk mengalihkan pembelian tanah dan bangunan, pengalihan pembelian akan dikenakan denda sebesar 2.5% dari harga jual final",
                "Nilai Uang Tanda Jadi ditetapkan sebesar Rp. 15.000.000,- (lima belas juta rupiah)",
                "Jangka waktu perjanjian ini berakhir sesuai tanggal akhir pelunasan pembayaran oleh Pembeli, kecuali untuk KPR sesuai pelunasan dari Bank setelah Serah Terima unit kepada Pembeli",
                "SPR ini akan batal dengan sendirinya dalam hal terjadinya kondisi yang dijelaskan pada pasal 3 dan 5, Pembatalan SPR dalam bentuk tertulis antara Pengembang dan Pembeli dibuat 3 rangkap dimana 1 rangkapnya milik Pemilik Tanah",
                "Penandatanganan SPR dilakukan setelah seluruh pasal didalamnya disepakati oleh masing-masing pihak"
            ];
        }

        $defaultSigs = $this->getDefaultSignatures();
        $settings['spr_signatures'] = array_merge($defaultSigs, is_array($settings['spr_signatures'] ?? null) ? $settings['spr_signatures'] : []);

        if (!isset($settings['spr_bank_info']) || !is_array($settings['spr_bank_info'])) {
            $settings['spr_bank_info'] = [
                'bank_name' => 'BRI / BCA / BSI',
                'account_number' => '020601014443301',
                'account_holder' => 'PT. Serangkai Roden Development',
            ];
        }

        if (!isset($settings['spr_special_offer']) || !is_array($settings['spr_special_offer'])) {
            $settings['spr_special_offer'] = [
                'enabled' => true,
                'title' => 'Special Offer & Benefit Umala Andara',
                'bonus_furniture' => [
                    'Kitchen Set',
                    'Kitchen Island',
                    'Dinding Feature Wall Backdrop TV (Sesuai rumah contoh)',
                    'Bench',
                    'Wall Cabinet TV',
                ],
                'grand_launching_package' => [
                    'Free BPHTB ((khusus aset perolehan pertama)',
                    'Free AJB',
                    'Free Balik Nama',
                    'Free Biaya Notaris',
                    'Extra Cashback 50 Juta',
                ],
                'promo_valid_until' => '30 September 2024',
            ];
        }

        return Inertia::render('Settings/Index', [
            'settings' => $settings,
            'tokens' => $request->user()->tokens,
            'partner_banks_all' => \App\Models\PartnerBank::latest()->get(),
            'broker_companies_all' => \App\Models\BrokerCompany::latest()->get(),
            'bank_accounts_all' => \App\Models\BankAccount::latest()->get(),
        ]);
    }

    public function generateToken(Request $request)
    {
        $request->user()->tokens()->delete();
        $token = $request->user()->createToken('website-api')->plainTextToken;
        
        return back()->with('api_token', $token);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_logo' => 'nullable|image|max:2048',
            'signature_image1' => 'nullable|image|max:2048',
            'signature_image2' => 'nullable|image|max:2048',
            'signature_image3' => 'nullable|image|max:2048',
            'signature_image4' => 'nullable|image|max:2048',
        ]);

        // 1. Company Logo Upload
        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('settings', 'public');
            Setting::set('company_logo', $path);
        }

        // 2. Signatures Setup & Uploads
        $existingSigs = Setting::get('spr_signatures', []);
        if (!is_array($existingSigs)) {
            $existingSigs = [];
        }
        $defaultSigs = $this->getDefaultSignatures();
        $existingSigs = array_merge($defaultSigs, $existingSigs);

        $inputSig = $request->input('spr_signatures');
        if (is_string($inputSig)) {
            $inputSig = json_decode($inputSig, true);
        }
        if (!is_array($inputSig)) {
            $inputSig = [];
        }

        $mergedSigs = array_merge($existingSigs, $inputSig);

        foreach ([1, 2, 3, 4] as $idx) {
            $key = "signature_image{$idx}";
            $sigKey = "sig{$idx}_image";
            if ($request->hasFile($key)) {
                $mergedSigs[$sigKey] = $request->file($key)->store('settings/signatures', 'public');
            } elseif (!empty($existingSigs[$sigKey])) {
                $mergedSigs[$sigKey] = $existingSigs[$sigKey];
            }
        }

        Setting::set('spr_signatures', $mergedSigs);

        // 3. Save Terms and Conditions array
        if ($request->has('spr_terms_conditions')) {
            $terms = $request->input('spr_terms_conditions');
            if (is_string($terms)) {
                $terms = json_decode($terms, true);
            }
            if (is_array($terms)) {
                Setting::set('spr_terms_conditions', array_values(array_filter($terms)));
            }
        }

        // 4. Save Bank Info array
        if ($request->has('spr_bank_info')) {
            $bank = $request->input('spr_bank_info');
            if (is_string($bank)) {
                $bank = json_decode($bank, true);
            }
            if (is_array($bank)) {
                Setting::set('spr_bank_info', $bank);
            }
        }

        // 5. Save Special Offer array
        if ($request->has('spr_special_offer')) {
            $so = $request->input('spr_special_offer');
            if (is_string($so)) {
                $so = json_decode($so, true);
            }
            if (is_array($so)) {
                Setting::set('spr_special_offer', $so);
            }
        }

        // 6. Handle remaining settings scalar key/value pairs
        $excluded = ['company_logo', 'signature_image1', 'signature_image2', 'signature_image3', 'signature_image4', 'spr_signatures', 'spr_terms_conditions', 'spr_bank_info', 'spr_special_offer', '_token'];
        $settings = $request->except($excluded);
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        return back()->with('success', 'Pengaturan berhasil diperbarui.');
    }
}
