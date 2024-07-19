<?php

namespace FriendsOfBotble\Bux\Services\Gateways;

use FriendsOfBotble\Bux\Services\Abstracts\BuxPaymentAbstract;
use Illuminate\Http\Request;

class BuxPaymentService extends BuxPaymentAbstract
{
    public function makePayment(Request $request)
    {
    }

    public function afterMakePayment(Request $request)
    {
    }

    public function supportedCurrencyCodes(): array
    {
        return [
            'PHP',
        ];
    }
}
