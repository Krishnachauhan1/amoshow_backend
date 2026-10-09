<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class RewardsController extends Controller
{
    public function show()
    {
        return response()->json(AppSetting::asMap());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'creator_share_percent' => 'required|integer|min:1|max:99',
            'platform_share_percent' => 'required|integer|min:1|max:99',
            'referral_percent_of_platform' => 'required|numeric|min:0|max:100',
            'min_payout_rupees' => 'required|numeric|min:0',
            'payout_hold_days' => 'required|integer|min:0|max:365',
            'install_voucher_code' => 'required|string|max:40',
            'install_voucher_amount' => 'required|numeric|min:0',
            'install_voucher_valid_days' => 'required|integer|min:1|max:365',
            'channel_offer_credit' => 'required|numeric|min:0',
            'channel_offer_expiry_days' => 'required|integer|min:1|max:365',
        ]);

        if ((int) $data['creator_share_percent'] + (int) $data['platform_share_percent'] !== 100) {
            return response()->json([
                'message' => 'Creator share and platform share must add up to 100%',
            ], 422);
        }

        $data['install_voucher_code'] = strtoupper(trim($data['install_voucher_code']));

        return response()->json(AppSetting::putMany($data));
    }
}
