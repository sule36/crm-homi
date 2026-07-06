<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::all()->pluck('value', 'key'),
            'tokens' => $request->user()->tokens,
            'partner_banks_all' => \App\Models\PartnerBank::latest()->get(),
            'broker_companies_all' => \App\Models\BrokerCompany::latest()->get(),
            'bank_accounts_all' => \App\Models\BankAccount::latest()->get(),
        ]);
    }

    public function generateToken(Request $request)
    {
        $request->user()->tokens()->delete(); // Limit to 1 token for simplicity
        $token = $request->user()->createToken('website-api')->plainTextToken;
        
        return back()->with('api_token', $token);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_logo' => 'nullable|image|max:2048',
        ]);

        // Handle file upload separately for clarity
        if ($request->hasFile('company_logo')) {
            $oldLogo = Setting::where('key', 'company_logo')->first()?->value;
            if ($oldLogo) Storage::disk('public')->delete($oldLogo);
            
            $path = $request->file('company_logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $path]);
        }

        // Handle other settings
        $settings = $request->except(['company_logo', '_token']);
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        return back()->with('success', 'Pengaturan berhasil diperbarui.');
    }
}
