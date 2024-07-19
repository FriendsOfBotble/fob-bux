<?php

namespace FriendsOfBotble\Bux\Services\Abstracts;

use FriendsOfBotble\Bux\Services\Bux;
use Botble\Payment\Models\Payment;
use Botble\Payment\Services\Traits\PaymentErrorTrait;
use Botble\Support\Services\ProduceServiceInterface;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Throwable;

abstract class BuxPaymentAbstract implements ProduceServiceInterface
{
    use PaymentErrorTrait;

    protected string $paymentCurrency;

    protected Client $client;

    protected bool $supportRefundOnline;

    public function __construct()
    {
        $this->paymentCurrency = config('plugins.payment.payment.currency');

        $this->supportRefundOnline = false;
    }

    public function getPaymentDetails(Payment $payment): array
    {
        try {
            $result = (new Bux())->callAPI('/check_code', [
                'req_id' => $payment->order_id,
                'mode' => 'API',
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (Throwable $exception) {
            $this->setErrorMessageAndLogging($exception, 1);

            return [];
        }
    }

    public function execute(Request $request)
    {
        try {
            return $this->makePayment($request);
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);

            return false;
        }
    }

    abstract public function makePayment(Request $request);

    abstract public function afterMakePayment(Request $request);
}
