<?php

namespace App\Actions\Traders;

use App\Enums\TraderStatus;
use App\Mail\TraderApprovedMail;
use App\Models\PriceTier;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Staff decision on a trader account: approve on a tier (or move an approved trader to another tier),
 * or reject. The trader is emailed the first time they are approved.
 */
class ReviewTraderRequest
{
    public function handle(User $trader, TraderStatus $status, ?PriceTier $tier): void
    {
        $wasApproved = $trader->trader_status === TraderStatus::Approved;

        $trader->forceFill([
            'trader_status' => $status,
            'price_tier_id' => $status === TraderStatus::Approved ? $tier?->id : null,
        ])->save();

        if ($status === TraderStatus::Approved && ! $wasApproved) {
            Mail::to($trader->email, $trader->name)->queue(new TraderApprovedMail($trader, $trader->locale ?: 'ar'));
        }
    }
}
