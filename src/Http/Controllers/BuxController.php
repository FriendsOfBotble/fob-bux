<?php

namespace FriendsOfBotble\Bux\Http\Controllers;

use FriendsOfBotble\Bux\Services\Bux;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Supports\PaymentHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class BuxController extends BaseController
{
    public function getCallback(Request $request, BaseHttpResponse $response, Bux $bux): BaseHttpResponse
    {
        try {
            $result = $bux->callAPI('/check_code', [
                'req_id' => Arr::first((array)$request->input('order_ids', [])),
                'mode' => 'API',
            ]);

            $data = json_decode($result->getBody()->getContents(), true);

            if (in_array(strtolower($data['status']), ['success', 'paid', 'pending'])) {
                do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
                    'amount' => $data['amount'],
                    'currency' => 'PHP',
                    'charge_id' => $data['ref_code'],
                    'payment_channel' => BUX_PAYMENT_METHOD_NAME,
                    'status' => strtolower($data['status']) == 'pending' ? PaymentStatusEnum::PENDING : PaymentStatusEnum::COMPLETED,
                    'customer_id' => $request->input('customer_id'),
                    'customer_type' => $request->input('customer_type'),
                    'payment_type' => 'direct',
                    'order_id' => (array)$request->input('order_ids'),
                ], $request);

                $nextUrl = PaymentHelper::getRedirectURL($request->input('checkout_token'));

                if (is_plugin_active('job-board') || is_plugin_active('real-estate')) {
                    $nextUrl = $nextUrl . '?charge_id=' . $data['ref_code'];
                }

                return $response
                    ->setNextUrl($nextUrl)
                    ->setMessage(__('Checkout successfully!'));
            }

            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage($data['message'] ?? __('Payment failed!'));
        } catch (Throwable $exception) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->setMessage($exception->getMessage());
        }
    }

    public function getNotifications(BaseHttpResponse $response): BaseHttpResponse
    {
        return $response;
    }
}
