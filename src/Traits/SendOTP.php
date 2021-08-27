<?php

namespace Jgu\Wfotp\Traits;

use Jgu\Wfotp\Models\WfoService;
use NotificationChannels\AwsSns\SnsMessage;
use NotificationChannels\AwsSns\Sns;
use Aws\Sns\SnsClient as SnsService;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Jgu\Wfotp\Models\WfoOtp;
use Jgu\Wfotp\Models\WfoResendOtp;

trait SendOTP
{

    public function getClassName()
    {
        return $this->className ?? get_class();
    }

    public function getCreatedBy()
    {
        return $this->created_by ?? 0;
    }

    /**
     * sendSmsBySns function
     *
     * @Description Send Sms to Mobile devices
     * @author Yogesh <yogesh@jgu.edu.in>
     * @param int $mobileNo
     * @param string $message
     * @return boolean
     */
    public function sendSmsBySns($mobileNo, $message)
    {
        // Prepare $message which we need to deliver
        $message = SnsMessage::create([
            'body' => $message,
            'transactional' => true,
            'sender' => 'TOU',
        ]);

        // send the message to the specific mobile number  
        $result =  $this->getSnsClient()->send($message, '+91' . $mobileNo);

        if ($result['@metadata']['statusCode'] === 200) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * getSnsClient function
     * 
     * @Description Create SNS clinet
     * @author Yogesh <yogesh@jgu.edu.in>
     * @return object
     */
    public function getSnsClient()
    {
        // Get clinet credentials from servies config
        $config = array_merge(['version' => 'latest'], ['key' => config('wfo.sns_key'), "secret" => config('wfo.sns_secret'), "region" => config('wfo.sns_region')]);

        // secret key and secret value are required
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        // Instante a new snsClient
        $client = new SnsService($config);

        return new sns($client);
    }

    /**
     * sendOTP function
     * 
     * @Description Send OTP/Public link to mobile devices by using configuration
     * @author Yogesh <yogesh@jgu.edu.in>
     * @param int $modelId
     * @param int $mobileNo
     * @param boolean $resend
     * @return boolean
     */
    public function sendOTP($modelId, $mobileNo, $resend = false)
    {
        $model = $this->getClassName();
        $service = $this->getModelService($model);
        $method = $service->methods[0]->method->method;

        // Expire all previous otps for that model and modelid
        WfoOtp::where("model", $model)->where("model_id", $modelId)->where("is_verified", 0)->update(array("expires_at" => Carbon::now()->toDateTimeString()));

        switch ($method) {
            case 'OTP on Approval':
                $otp = $this->generateOTP();
                $smsService = $this->getSmsService($service);

                switch ($smsService) {
                    case "sns":
                        $message =  $this->getMessageText($service->message_text, $otp);
                        $response = $this->sendSmsBySns($mobileNo, $message);

                        if ($response) {
                            $data = [
                                "model" => $model,
                                "model_id" => $modelId,
                                "otp" => $otp,
                                "expires_at" => Carbon::now()->addMinute($service->expiration_time)->toDateTimeString(),
                                "is_verified" => 0
                            ];

                            return $this->saveOtp($data, $resend);
                        } else {
                            return false;
                        }
                        break;
                    default:
                        return "No SMS Service is configured";
                }

                break;

            case 'Public Link Approval':
                $otp = $this->generateOTP();
                $link = 'model_id=' . $modelId . '&model=' . $model . '&token=' . $otp . '&redirect_to=dashboard';
                $token = $this->secret($link, "encrypt");
                $route = env('APP_URL') . "/wfo-verification/$token";

                $url =  file_get_contents('http://tinyurl.com/api-create.php?url=' . $route);

                $message =  $this->getMessageText($service->message_text, $url);
                $response = $this->sendSmsBySns($mobileNo, $message);

                if ($response) {
                    $data = [
                        "model" => $model,
                        "model_id" => $modelId,
                        "public_link" => $otp,
                        "expires_at" => Carbon::now()->addMinute($service->expiration_time)->toDateTimeString(),
                        "is_verified" => 0
                    ];

                    return  $this->saveOtp($data, $resend);
                } else {
                    return false;
                }

                break;

            default:
                return "No method is configured";
        }
    }

    public function saveOtp($data, $resend = true)
    {
        try {
            $wfoOtp = new WfoOtp();
            $wfoOtp::create($data);

            if ($resend) {
                $wfoResendOtp = new WfoResendOtp();

                $wfoResendOtp->wfo_otp_id = $wfoOtp->id;
                $wfoResendOtp->date_time = Carbon::now()->toDateTimeString();

                $wfoResendOtp->save();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function resendOTP($modelId, $mobileNo)
    {
        return $this->sendOTP($modelId, $mobileNo, true);
    }

    public function isExpired($expires_at)
    {
        return Carbon::now()->gt($expires_at);
    }

    public function getModelService($model)
    {
        $services = WfoService::with(['methods', 'methods.method', 'smsServices', 'smsServices.smsService'])->where('model', $model)->first();
        if ($services) {
            if ($services->methods) {
                return $services;
            } else {
                echo 'No Method is configured';
            }
        } else {
            echo 'No Services are configured';
        }
    }

    public function getSmsService($service)
    {
        if ($service->smsServices) {
            return $service->smsServices[0]->smsService->service_name;
        } else {
            echo "No SMS Service is configured";
        }
    }

    public function getMessageText($message, $otp)
    {
        return str_replace('$otp', $otp, $message);
    }

    public function generateOTP($n = 6)
    {
        $generator = "1357902468";
        $result = "";

        for ($i = 1; $i <= $n; $i++) {
            $result .= substr($generator, (rand() % (strlen($generator))), 1);
        }

        // Return result
        return $result;
    }

    public static function secret($message, $type)
    {
        $ciphering = "AES-128-CTR";
        $key = config('wfo.secret_key');
        $iv = config('wfo.secret_iv');
        $options = 0;

        if ($type == 'encrypt') {
            return openssl_encrypt($message, $ciphering, $key, $options, $iv);
        } elseif ($type == 'decrypt') {
            return openssl_decrypt($message, $ciphering, $key, $options, $iv);
        }
    }
}
