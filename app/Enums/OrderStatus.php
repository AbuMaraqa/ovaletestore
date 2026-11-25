<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** The normal path of an order, in order. Cancelled is a side exit. */
    public const FLOW = [self::Pending, self::Confirmed, self::Processing, self::Shipped, self::Completed];

    /**
     * Forward to any later stage (staff may skip steps, e.g. pending → completed for a counter sale),
     * or cancel while the goods have not left (cancelling restocks). Never backwards: completed and
     * cancelled are final, so stock and company balances cannot be counted twice.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        if ($this === self::Completed || $this === self::Cancelled) {
            return [];
        }

        $later = array_slice(self::FLOW, array_search($this, self::FLOW, true) + 1);

        return $this === self::Shipped ? $later : [...$later, self::Cancelled];
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
