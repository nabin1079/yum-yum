<?php
/*
 * Order rules shared by the buyer and seller pages.
 *
 * Cash on delivery flow:
 *   pending -> confirmed -> preparing -> out_for_delivery -> delivered
 *   The seller moves the order forward and marks it PAID when the cash is
 *   received. The buyer confirms RECEIVED when the food arrives, which also
 *   marks it delivered. An order is complete when it is delivered and paid.
 *   The buyer can cancel while the order is pending; the seller can cancel
 *   until it is out for delivery.
 */

const ORDER_STATUSES = [
    'pending'          => 'Pending',
    'confirmed'        => 'Confirmed',
    'preparing'        => 'Preparing',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];

// Next statuses a seller may choose from each status.
const SELLER_TRANSITIONS = [
    'pending'          => ['confirmed', 'cancelled'],
    'confirmed'        => ['preparing', 'cancelled'],
    'preparing'        => ['out_for_delivery', 'cancelled'],
    'out_for_delivery' => ['delivered'],
    'delivered'        => [],
    'cancelled'        => [],
];

function seller_can_move(string $from, string $to): bool {
    return in_array($to, SELLER_TRANSITIONS[$from] ?? [], true);
}

function buyer_can_cancel(array $order): bool {
    return $order['status'] === 'pending';
}

function buyer_can_confirm_received(array $order): bool {
    return in_array($order['status'], ['out_for_delivery', 'delivered'], true) && $order['received_at'] === null;
}

function seller_can_mark_paid(array $order): bool {
    return in_array($order['status'], ['out_for_delivery', 'delivered'], true) && $order['payment_status'] === 'unpaid';
}

function order_is_complete(array $order): bool {
    return $order['status'] === 'delivered' && $order['payment_status'] === 'paid';
}

function status_badge(string $status): string {
    $map = [
        'pending'          => ['#f97316', 'rgba(249,115,22,0.1)'],
        'confirmed'        => ['#3b82f6', 'rgba(59,130,246,0.1)'],
        'preparing'        => ['#8b5cf6', 'rgba(139,92,246,0.1)'],
        'out_for_delivery' => ['#0ea5e9', 'rgba(14,165,233,0.1)'],
        'delivered'        => ['#16a34a', 'rgba(34,197,94,0.1)'],
        'cancelled'        => ['#ef4444', 'rgba(239,68,68,0.1)'],
    ];
    [$color, $bg] = $map[$status] ?? ['#6b7280', 'rgba(107,114,128,0.1)'];
    $label = ORDER_STATUSES[$status] ?? ucfirst($status);
    return "<span class=\"order-badge\" style=\"background:$bg;color:$color;\">● " . e($label) . '</span>';
}

function payment_badge(array $order): string {
    if ($order['status'] === 'cancelled') {
        return '';
    }
    return $order['payment_status'] === 'paid'
        ? '<span class="order-badge" style="background:rgba(34,197,94,0.1);color:#16a34a;">Cash paid</span>'
        : '<span class="order-badge" style="background:rgba(107,114,128,0.1);color:#57534e;">Cash on delivery · unpaid</span>';
}

function seller_display_name(array $row, string $prefix = 'seller_'): string {
    $kitchen = trim((string)($row[$prefix . 'kitchen'] ?? ''));
    $name    = trim(($row[$prefix . 'first'] ?? '') . ' ' . ($row[$prefix . 'last'] ?? ''));
    return $kitchen !== '' ? $kitchen : $name;
}
