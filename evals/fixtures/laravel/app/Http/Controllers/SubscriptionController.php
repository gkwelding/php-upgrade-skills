<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentDeclined;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
    public function show(Subscription $subscription)
    {
        return [
            'plan' => $subscription->plan,
            'seats' => $subscription->seats,
            'days_left' => $subscription->daysLeft(),
            'auto_renew' => $subscription->options['auto_renew'] ?? false,
        ];
    }

    public function renew(Subscription $subscription)
    {
        if (($subscription->options['card'] ?? null) === 'declined') {
            throw new PaymentDeclined('Card declined');
        }

        $subscription->update(['ends_at' => $subscription->ends_at->addDays(30)]);

        return ['days_left' => $subscription->daysLeft()];
    }
}
