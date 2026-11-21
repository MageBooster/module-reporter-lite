<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\ReporterLite\Model;

/**
 * Starter queries and the help texts of the "New report" screen.
 */
class Examples
{
    /**
     * Example queries (the first one is the blank editor)
     *
     * @return array<int,array{key:string,icon:string,name:string,sql:string,what:string,cols:string,tip:string}>
     */
    public function all(): array
    {
        return [
            [
                'key' => 'blank', 'icon' => 'pencil',
                'name' => (string)__('Blank — write my own'),
                'sql' => '',
                'what' => (string)__('An empty editor. Type any SELECT query; the table list and the steps on the right help you find the right columns. Parameters you write (:from, :to, :anything) get their own fields automatically.'),
                'cols' => (string)__('whatever you SELECT'),
                'tip' => (string)__('Stuck? Pick an example above, Run it, then change one line at a time.'),
            ],
            [
                'key' => 'sales_by_day', 'icon' => 'chart',
                'name' => (string)__('Sales by day'),
                'sql' => <<<'SQL'
SELECT DATE(created_at)        AS day,
       COUNT(*)                AS orders,
       SUM(base_grand_total)   AS revenue
FROM sales_order
WHERE created_at BETWEEN :from AND :to
  AND state NOT IN ('canceled', 'closed')
GROUP BY day
ORDER BY day
SQL,
                'what' => (string)__('One row per day with the number of orders and the revenue. Cancelled and fully refunded orders are left out.'),
                'cols' => 'day, orders, revenue',
                'tip' => (string)__('Group by week with YEARWEEK(created_at) or by month with DATE_FORMAT(created_at, \'%Y-%m\').'),
            ],
            [
                'key' => 'best_sellers', 'icon' => 'star',
                'name' => (string)__('Best sellers'),
                'sql' => <<<'SQL'
SELECT i.sku,
       i.name,
       SUM(i.qty_ordered)     AS qty,
       SUM(i.base_row_total)  AS revenue
FROM sales_order_item i
JOIN sales_order o ON o.entity_id = i.order_id
WHERE o.created_at BETWEEN :from AND :to
  AND o.state <> 'canceled'
  AND i.parent_item_id IS NULL
GROUP BY i.sku, i.name
ORDER BY qty DESC
LIMIT 20
SQL,
                'what' => (string)__('The 20 products sold most in the chosen period, with quantity and revenue. "parent_item_id IS NULL" stops configurable and bundle products being counted twice.'),
                'cols' => 'sku, name, qty, revenue',
                'tip' => (string)__('Sort by revenue instead: ORDER BY revenue DESC. Show more rows: LIMIT 50.'),
            ],
            [
                'key' => 'customers_no_orders', 'icon' => 'user',
                'name' => (string)__('Customers without orders'),
                'sql' => <<<'SQL'
SELECT c.email,
       c.firstname,
       c.lastname,
       c.created_at AS registered
FROM customer_entity c
LEFT JOIN sales_order o ON o.customer_id = c.entity_id
WHERE o.entity_id IS NULL
  AND c.created_at BETWEEN :from AND :to
ORDER BY c.created_at DESC
SQL,
                'what' => (string)__('People who opened an account in the period but never placed an order. Good for a welcome or first-order campaign.'),
                'cols' => 'email, firstname, lastname, registered',
                'tip' => (string)__('LEFT JOIN + "IS NULL" is the pattern for "has no …". Use it for products never sold, too.'),
            ],
            [
                'key' => 'low_stock', 'icon' => 'box',
                'name' => (string)__('Low stock'),
                'sql' => <<<'SQL'
SELECT p.sku,
       s.qty
FROM cataloginventory_stock_item s
JOIN catalog_product_entity p ON p.entity_id = s.product_id
WHERE s.qty <= :threshold
  AND s.is_in_stock = 1
  AND p.type_id = 'simple'
ORDER BY s.qty
SQL,
                'what' => (string)__('Simple products still in stock but at or below the threshold you enter. Schedule it daily with "Don\'t send when there are no rows".'),
                'cols' => 'sku, qty',
                'tip' => (string)__('Stores with several warehouses (MSI) keep stock per source in inventory_source_item (sku, source_code, quantity).'),
                'params' => ['threshold' => '5'],
            ],
            [
                'key' => 'abandoned_carts', 'icon' => 'cart',
                'name' => (string)__('Abandoned carts'),
                'sql' => <<<'SQL'
SELECT q.customer_email,
       q.items_count,
       q.base_grand_total AS cart_value,
       q.updated_at       AS last_activity
FROM quote q
WHERE q.is_active = 1
  AND q.items_count > 0
  AND q.customer_email IS NOT NULL
  AND q.updated_at BETWEEN :from AND :to
  AND q.updated_at < NOW() - INTERVAL 1 HOUR
ORDER BY q.base_grand_total DESC
SQL,
                'what' => (string)__('Carts with products and a known e-mail that were not turned into an order and have been idle for over an hour.'),
                'cols' => 'customer_email, items_count, cart_value, last_activity',
                'tip' => (string)__('Guests appear only if they typed their e-mail at checkout. Change the idle time with INTERVAL 1 DAY.'),
            ],
            [
                'key' => 'coupon_usage', 'icon' => 'tag',
                'name' => (string)__('Coupon usage'),
                'sql' => <<<'SQL'
SELECT coupon_code,
       COUNT(*)                   AS orders,
       SUM(base_discount_amount)  AS discount,
       SUM(base_grand_total)      AS revenue
FROM sales_order
WHERE coupon_code IS NOT NULL
  AND created_at BETWEEN :from AND :to
  AND state <> 'canceled'
GROUP BY coupon_code
ORDER BY orders DESC
SQL,
                'what' => (string)__('How often each coupon code was used, how much discount it gave and how much revenue came with it. Discount is shown as a negative number.'),
                'cols' => 'coupon_code, orders, discount, revenue',
                'tip' => (string)__('Look at one code only: replace "IS NOT NULL" with = :code.'),
            ],
        ];
    }

    /**
     * "How to write a report query"
     *
     * @return array<int,array{icon:string,title:string,text:string}>
     */
    public function steps(): array
    {
        return [
            ['icon' => 'row', 'title' => (string)__('Decide what one row is'),
                'text' => (string)__('One day? One product? One customer? This decides your GROUP BY.')],
            ['icon' => 'columns', 'title' => (string)__('Pick the columns'),
                'text' => (string)__('Give each column a clear name with AS — that name becomes the header in Excel and CSV.')],
            ['icon' => 'sliders', 'title' => (string)__('Add parameters'),
                'text' => (string)__('Write :from, :to, :store or any :name. A field for each one appears under the editor, and the schedule fills dates in for you.')],
            ['icon' => 'filter', 'title' => (string)__('Filter the noise'),
                'text' => (string)__('Leave out cancelled orders, test orders and child items of configurable products.')],
            ['icon' => 'play', 'title' => (string)__('Run and check'),
                'text' => (string)__('Run shows the first 100 rows. Compare one number with the Magento admin before you trust it.')],
            ['icon' => 'clock', 'title' => (string)__('Save, then schedule'),
                'text' => (string)__('Saved reports can be run by hand; the Schedule tab sends them by e-mail.')],
        ];
    }

    /**
     * "Where the data is"
     *
     * @return array<int,array{table:string,text:string}>
     */
    public function tables(): array
    {
        return [
            ['table' => 'sales_order', 'text' => (string)__('Orders: created_at, state, status, base_grand_total, customer_id, customer_email, coupon_code, store_id')],
            ['table' => 'sales_order_item', 'text' => (string)__('Order lines: order_id, sku, name, qty_ordered, base_row_total, parent_item_id')],
            ['table' => 'customer_entity', 'text' => (string)__('Customers: entity_id, email, firstname, lastname, created_at, group_id')],
            ['table' => 'catalog_product_entity', 'text' => (string)__('Products: entity_id, sku, type_id (names and prices live in EAV tables)')],
            ['table' => 'cataloginventory_stock_item', 'text' => (string)__('Stock: product_id, qty, is_in_stock')],
            ['table' => 'quote', 'text' => (string)__('Carts: is_active, items_count, base_grand_total, customer_email, updated_at')],
            ['table' => 'sales_creditmemo', 'text' => (string)__('Refunds: order_id, base_grand_total, created_at')],
        ];
    }

    /**
     * "Common mistakes"
     *
     * @return string[]
     */
    public function traps(): array
    {
        return [
            (string)__('Dates are stored in UTC. A "day" can shift by your time zone offset — the parameters handle this for you.'),
            (string)__('Use base_ columns (base_grand_total) when you sell in more than one currency.'),
            (string)__('Without parent_item_id IS NULL, configurable products are counted twice.'),
            (string)__('Only SELECT works. UPDATE, DELETE or INSERT are refused by the read-only user.'),
        ];
    }
}
