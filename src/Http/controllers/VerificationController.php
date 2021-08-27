<?php
namespace Jgu\Wfotp\Http\Controllers;

use App\Http\Controllers\Controller;
use Jgu\Wfotp\Traits\SendOTP;
use Jgu\Wfotp\Traits\Verification;

class VerificationController extends Controller
{
    use SendOTP,Verification;

    public function index($token)
    {
        $token = $this->secret($token, 'decrypt');

        return $this->verifyLink($token);
        

    }


}
