<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Staff notifications (new orders). Users can only touch their own notifications. */
class NotificationController extends Controller
{
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $orderId = $notification->data['order_id'] ?? null;

        return $orderId && $request->user()->can('orders.view')
            ? to_route('admin.orders.show', $orderId)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
