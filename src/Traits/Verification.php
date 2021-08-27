<?php

namespace Jgu\Wfotp\Traits;

use Carbon\Carbon;
use Jgu\Wfotp\Models\WfoOtp;

trait Verification
{

    // Verify OTP for specific model
    public function verifyOTP($modelId, $otp)
    {
        $otp = WfoOtp::where('model', $this->getClassName())
            ->where('model_id', $modelId)
            ->where('otp', $otp)
            ->where('is_verified', 0)
            ->latest('id')->first();
        
        if ($otp) {
            if(!$this->isExpired($otp->expires_at)) {
                $otp->is_verified = 1;
                $otp->verification_date_time =  Carbon::now()->toDateTimeString();
                $otp->save();

                return true;
            } else {
                return 'expired';
            }           
        } else {
            return false;
        }
    }

    public function verifyLink($link) {
        $tokenArr = explode("&", $link);
       
        $modelId = explode("=", $tokenArr[0])[1];
        $model =  explode("=", $tokenArr[1])[1];
        $otp = explode("=", $tokenArr[2])[1];
        $redirectTo = explode("=", $tokenArr[3])[1];

        $otp = WfoOtp::where('model', $model)
            ->where('model_id', $modelId)
            ->where('public_link', $otp)
            ->where('is_verified', 0)
            ->latest('id')->first();
        
        if ($otp) {
            if(!$this->isExpired($otp->expires_at)) {
                $otp->is_verified = 1;
                $otp->verification_date_time =  Carbon::now()->toDateTimeString();
                $otp->save();

                return view('Wfotp::verify', ["status" => "verified"]);
            } else {
                return view('Wfotp::verify', ["status" => "expired"]);
            }           
        } else {
            return false;
        }

    }

}
