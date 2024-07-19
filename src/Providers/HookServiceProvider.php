<?php

namespace FriendsOfBotble\Bux\Providers;

use FriendsOfBotble\Bux\Services\Bux;
use FriendsOfBotble\Bux\Services\Gateways\BuxPaymentService;
use Botble\Ecommerce\Models\Currency;
use Botble\Payment\Enums\PaymentMethodEnum;
use Html;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Throwable;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, [$this, 'registerBuxMethod'], 19, 2);

        $this->app->booted(function () {
            add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, [$this, 'checkoutWithBux'], 19, 2);
        });

        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, [$this, 'addPaymentSettings'], 93, 1);

        add_filter(BASE_FILTER_ENUM_ARRAY, function ($values, $class) {
            if ($class == PaymentMethodEnum::class) {
                $values['BUX'] = BUX_PAYMENT_METHOD_NAME;
            }

            return $values;
        }, 32, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == BUX_PAYMENT_METHOD_NAME) {
                $value = 'Bux';
            }

            return $value;
        }, 32, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == BUX_PAYMENT_METHOD_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )
                    ->toHtml();
            }

            return $value;
        }, 32, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function ($data, $value) {
            if ($value == BUX_PAYMENT_METHOD_NAME) {
                $data = BuxPaymentService::class;
            }

            return $data;
        }, 32, 2);

        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, function ($data, $payment) {
            if ($payment->payment_channel == BUX_PAYMENT_METHOD_NAME) {
                $paymentService = (new BuxPaymentService());
                $paymentDetail = $paymentService->getPaymentDetails($payment);
                if ($paymentDetail) {
                    $data = view(
                        'plugins/bux::detail',
                        ['payment' => $paymentDetail, 'paymentModel' => $payment]
                    )->render();
                }
            }

            return $data;
        }, 32, 2);
    }

    public function addPaymentSettings(?string $settings): string
    {
        return $settings . view('plugins/bux::settings')->render();
    }

    public function registerBuxMethod(?string $html, array $data): string
    {
        return $html . view('plugins/bux::methods', $data)->render();
    }

    public function checkoutWithBux(array $data, Request $request): array
    {
        if ($data['type'] !== BUX_PAYMENT_METHOD_NAME) {
            return $data;
        }

        $currentCurrency = get_application_currency();

        $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

        if (strtoupper($currentCurrency->title) !== 'PHP') {
            $supportedCurrency = Currency::query()->where('title', 'PHP')->first();

            if ($supportedCurrency) {
                $paymentData['currency'] = strtoupper($supportedCurrency->title);
                if ($currentCurrency->is_default) {
                    $paymentData['amount'] = $paymentData['amount'] * $supportedCurrency->exchange_rate;
                } else {
                    $paymentData['amount'] = format_price(
                        $paymentData['amount'] / $currentCurrency->exchange_rate,
                        $currentCurrency,
                        true
                    );
                }
            }
        }

        $supportedCurrencies = (new BuxPaymentService())->supportedCurrencyCodes();

        if (! in_array($paymentData['currency'], $supportedCurrencies)) {
            $data['error'] = true;
            $data['message'] = __(
                ":name doesn't support :currency. List of currencies supported by :name: :currencies.",
                ['name' => 'Bux', 'currency' => $data['currency'], 'currencies' => implode(', ', $supportedCurrencies)]
            );

            return $data;
        }

        try {
            $orderIds = $paymentData['order_id'];

            $params = [
                'req_id' => Arr::first($orderIds),
                'amount' => $paymentData['amount'],
                'description' => $paymentData['description'],
                'expiry' => 2,
                'email' => $paymentData['address']['email'],
                'contact' => $paymentData['address']['phone'],
                'name' => $paymentData['address']['name'],
                'notification_url' => route('bux.payment.notifications', [
                    'checkout_token' => $paymentData['checkout_token'],
                    'order_ids' => $orderIds,
                    'customer_id' => $paymentData['customer_id'],
                    'customer_type' => $paymentData['customer_type'],
                ]),
                'redirect_url' => route('bux.payment.callback', [
                    'checkout_token' => $paymentData['checkout_token'],
                    'order_ids' => $orderIds,
                    'customer_id' => $paymentData['customer_id'],
                    'customer_type' => $paymentData['customer_type'],
                ]),
                'param1' => json_encode($orderIds),
                'param2' => $paymentData['checkout_token'],
            ];

            $response = (new Bux())->callAPI('/open/checkout', $params);

            $response = json_decode($response->getBody()->getContents(), true);

            if ($response['status'] === 'success') {
                $data['checkoutUrl'] = $response['checkout_url'];

                return $data;
            }

            $data['error'] = true;
            $data['message'] = $response['message'];
        } catch (Throwable $exception) {
            $data['error'] = true;
            $data['message'] = json_encode($exception->getMessage());
        }

        return $data;
    }
}
