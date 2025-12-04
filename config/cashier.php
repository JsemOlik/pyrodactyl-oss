<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key give you access to Stripe's
    | API. The "publishable" key is typically used when you need to expose
    | client-side code, while the "secret" key accesses all of your server-side
    | Stripe resources.
    |
    */

    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => [
            'payment_intent.succeeded',
            'checkout.session.completed',
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various world currencies that are supported via Stripe.
    |
    */

    'currency' => env('CASHIER_CURRENCY', 'usd'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to represent a customer when interacting
    | with Cashier. You may change this model if you need to extend or replace
    | the default model.
    |
    */

    'model' => env('CASHIER_MODEL', \Pterodactyl\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Subscription Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to represent a subscription when
    | interacting with Cashier. You may change this model if you need to extend
    | or replace the default model.
    |
    */

    'subscription_model' => \Pterodactyl\Models\Subscription::class,

    /*
    |--------------------------------------------------------------------------
    | Invoice Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to represent an invoice when
    | interacting with Cashier. You may change this model if you need to extend
    | or replace the default model.
    |
    */

    'invoice_model' => \Laravel\Cashier\Invoice::class,

    /*
    |--------------------------------------------------------------------------
    | Payment Method Model
    |--------------------------------------------------------------------------
    |
    | This is the model that will be used to represent a payment method when
    | interacting with Cashier. You may change this model if you need to extend
    | or replace the default model.
    |
    */

    'payment_method_model' => \Laravel\Cashier\PaymentMethod::class,
];

