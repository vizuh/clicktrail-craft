<?php

declare(strict_types=1);

namespace ClickTrail\Craft\EventListeners;

use ClickTrail\Conventions\Stable;
use craft\commerce\elements\Order;

/**
 * Commerce order lifecycle -> sale.completed / sale.refunded.
 */
class CommerceOrders
{
    public static function attach(): void
    {
        if (!class_exists('\craft\commerce\Plugin')) {
            return;
        }

        // TODO verify: Commerce fires completion on the Orders service as
        // EVENT_AFTER_COMPLETE_ORDER; confirm constant name for the targeted
        // Commerce 5 minor.
        \yii\base\Event::on(
            \craft\commerce\services\Orders::class,
            \craft\commerce\services\Orders::EVENT_AFTER_COMPLETE_ORDER,
            self::class . '::onOrderComplete'
        );

        // Paid/refunded surface through saved payment transactions.
        // TODO verify: confirm Transactions service event + transaction type check.
        \yii\base\Event::on(
            \craft\commerce\services\Transactions::class,
            \craft\commerce\services\Transactions::EVENT_AFTER_SAVE_TRANSACTION,
            self::class . '::onTransactionSave'
        );
    }

    public static function onOrderComplete(\yii\base\Event $event): void
    {
        self::emitForOrder(Stable::EVENT_SALE_COMPLETED, 'mapCommerceOrders', $event);
    }

    public static function onTransactionSave(\yii\base\Event $event): void
    {
        $transaction = $event->sender ?? null;

        if ($transaction === null || !isset($transaction->type)) {
            return;
        }

        // TODO verify: type constants live on craft\commerce\records\Transaction
        // (TransactionRecord::TYPE_REFUND); confirm before first release.
        $type = (string) ($transaction->type ?? '');
        if ($type === 'refund') {
            self::emitForOrder(Stable::EVENT_SALE_REFUNDED, 'mapRefunds', $event);
        }
    }

    private static function emitForOrder(string $eventName, string $settingKey, \yii\base\Event $event): void
    {
        $plugin = \ClickTrail\Craft\Plugin::getInstance();
        $settings = $plugin->getSettings();

        if (!$settings->$settingKey) {
            return;
        }

        $order = null;

        if (method_exists($event->sender, 'getOrder')) {
            $order = $event->sender->getOrder();
        } elseif ($event->sender instanceof Order) {
            $order = $event->sender;
        } elseif (property_exists($event, 'order')) {
            $order = $event->order;
        }

        if ($order === null) {
            return;
        }

        $extra = [
            'object_type' => 'commerce_order',
            'object_id' => (string) $order->id,
        ];

        if (isset($order->totalPrice)) {
            $extra['value'] = (float) $order->totalPrice;
        }

        if (!empty($order->paymentCurrency)) {
            $extra['currency'] = (string) $order->paymentCurrency;
        }

        $payload = $plugin->get('attribution')->buildPayload(
            $eventName,
            event: [],
            extra: $extra,
        );

        Delivery::send($payload);
    }
}
