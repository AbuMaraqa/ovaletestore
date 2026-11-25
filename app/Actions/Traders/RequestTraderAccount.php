<?php

namespace App\Actions\Traders;

use App\Enums\TraderStatus;
use App\Models\User;
use App\Notifications\TraderRequested;
use Illuminate\Support\Facades\Notification;

/**
 * A customer asks for trader prices (at sign-up or later from their account). The request waits for a
 * staff member with `traders.approve`; everyone allowed to review it gets a notification in the admin.
 */
class RequestTraderAccount
{
    public function handle(User $user, string $businessName): void
    {
        if ($user->trader_status === TraderStatus::Approved) {
            return;
        }

        $user->forceFill([
            'trader_status' => TraderStatus::Pending,
            'business_name' => $businessName,
            'trader_requested_at' => now(),
            'price_tier_id' => null,
        ])->save();

        $reviewers = User::query()->where('is_active', true)
            ->where(fn ($q) => $q->permission('traders.approve')->orWhereHas('roles', fn ($r) => $r->where('name', 'super-admin')))
            ->get();
        Notification::send($reviewers, new TraderRequested($user));
    }
}
