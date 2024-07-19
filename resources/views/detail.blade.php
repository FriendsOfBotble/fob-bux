@if ($payment)
    <p>
        <span>{{ trans('plugins/payment::payment.payment_id') }}: </span>
        {{ $payment['ref_code'] }}
    </p>
    <p>{{ trans('plugins/payment::payment.amount') }}: {{ $payment['amount']}}</p>
    <hr>

    @include('plugins/payment::partials.view-payment-source')
@endif
