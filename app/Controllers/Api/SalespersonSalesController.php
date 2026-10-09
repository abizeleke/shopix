<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;

class SalespersonSalesController extends BaseController
{
    use ResponseTrait;

    // ============================================================
    // GET MY SALES
    // ============================================================

    public function index()
    {
        $userId = $this->request->userId ?? null;

        if ($userId === null) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Authentication required.',
                ],
                401
            );
        }

        $db = \Config\Database::connect();

        try {
            /*
             * IMPORTANT:
             *
             * We do NOT accept user_id from Flutter.
             *
             * The authenticated user's ID comes from AuthFilter.
             *
             * Therefore this endpoint can only return the
             * currently logged-in salesperson's sales.
             */

            $sales = $db
                ->table('sales')
                ->where('sales.user_id', $userId)
                ->orderBy('sales.created_at', 'DESC')
                ->orderBy('sales.id', 'DESC')
                ->get()
                ->getResultArray();

            $result = [];

            foreach ($sales as $sale) {
                $items = $db
                    ->table('sale_items')
                    ->select(
                        'sale_items.product_id,
                         sale_items.quantity,
                         sale_items.buying_price,
                         sale_items.selling_price,
                         sale_items.profit,
                         products.name AS product_name'
                    )
                    ->join(
                        'products',
                        'products.id = sale_items.product_id',
                        'left'
                    )
                    ->where(
                        'sale_items.sale_id',
                        $sale['id']
                    )
                    ->orderBy(
                        'sale_items.id',
                        'ASC'
                    )
                    ->get()
                    ->getResultArray();

                $sale['items'] = $items;

                $result[] = $sale;
            }

            return $this->respond(
                [
                    'success' => true,
                    'sales' => $result,
                ],
                200
            );
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Failed to load salesperson sales: ' .
                $e->getMessage()
            );

            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Could not load sales.',
                ],
                500
            );
        }
    }

    // ============================================================
    // CREATE SALE
    // ============================================================

    public function create()
    {
        $userId = $this->request->userId ?? null;

        if ($userId === null) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Authentication required.',
                ],
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Invalid request body.',
                ],
                400
            );
        }

        $items = $data['items'] ?? null;

        if (!is_array($items) || count($items) === 0) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'At least one sale item is required.',
                ],
                400
            );
        }

        $paymentMethod = strtolower(
            trim((string) ($data['payment_method'] ?? ''))
        );

        if (!in_array($paymentMethod, ['cash', 'bank'], true)) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Payment method must be cash or bank.',
                ],
                400
            );
        }

        $customerName = $data['customer_name'] ?? null;

        if ($customerName !== null) {
            $customerName = trim(
                (string) $customerName
            );

            if ($customerName === '') {
                $customerName = null;
            }
        }

        $db = \Config\Database::connect();

        $db->transBegin();

        try {
            $totalAmount = 0;
            $totalProfit = 0;

            $processedItems = [];

            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    throw new \RuntimeException(
                        'Invalid sale item at position ' .
                        ($index + 1) .
                        '.'
                    );
                }

                $productId = isset($item['product_id'])
                    ? (int) $item['product_id']
                    : 0;

                if ($productId <= 0) {
                    throw new \RuntimeException(
                        'Invalid product ID at item ' .
                        ($index + 1) .
                        '.'
                    );
                }

                $quantity = isset($item['quantity'])
                    ? (int) $item['quantity']
                    : 0;

                if ($quantity <= 0) {
                    throw new \RuntimeException(
                        'Quantity must be greater than 0.'
                    );
                }

                if (
                    !isset($item['selling_price']) ||
                    !is_numeric($item['selling_price'])
                ) {
                    throw new \RuntimeException(
                        'Invalid selling price.'
                    );
                }

                $sellingPrice =
                    (float) $item['selling_price'];

                if ($sellingPrice <= 0) {
                    throw new \RuntimeException(
                        'Selling price must be greater than 0.'
                    );
                }

                $product = $db
                    ->table('products')
                    ->where('id', $productId)
                    ->where('status', 'active')
                    ->get()
                    ->getRowArray();

                if (!$product) {
                    throw new \RuntimeException(
                        'Product with ID ' .
                        $productId .
                        ' was not found or is inactive.'
                    );
                }

                $buyingPrice =
                    (float) $product['buying_price'];

                $stock =
                    (int) $product['quantity'];

                if ($stock <= 0) {
                    throw new \RuntimeException(
                        $product['name'] .
                        ' is out of stock.'
                    );
                }

                if ($quantity > $stock) {
                    throw new \RuntimeException(
                        'Only ' .
                        $stock .
                        ' item(s) of ' .
                        $product['name'] .
                        ' are available.'
                    );
                }

                if ($sellingPrice < $buyingPrice) {
                    throw new \RuntimeException(
                        'Selling price for ' .
                        $product['name'] .
                        ' cannot be lower than buying price.'
                    );
                }

                $itemTotal =
                    $quantity *
                    $sellingPrice;

                $itemProfit =
                    $quantity *
                    ($sellingPrice -
                        $buyingPrice);

                $totalAmount += $itemTotal;
                $totalProfit += $itemProfit;

                $processedItems[] = [
                    'product_id' =>
                        $productId,
                    'quantity' =>
                        $quantity,
                    'buying_price' =>
                        $buyingPrice,
                    'selling_price' =>
                        $sellingPrice,
                    'profit' =>
                        $itemProfit,
                ];
            }

            $saleNumber =
                'SALE-' .
                date('YmdHis') .
                '-' .
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(4)
                        ),
                        0,
                        8
                    )
                );

            $saleData = [
                'sale_number' =>
                    $saleNumber,
                'user_id' =>
                    $userId,
                'total_amount' =>
                    round(
                        $totalAmount,
                        2
                    ),
                'total_profit' =>
                    round(
                        $totalProfit,
                        2
                    ),
                'status' =>
                    'completed',
                'customer_name' =>
                    $customerName,
                'payment_method' =>
                    $paymentMethod,
            ];

            $db
                ->table('sales')
                ->insert($saleData);

            $saleId =
                $db->insertID();

            if (!$saleId) {
                throw new \RuntimeException(
                    'Could not create sale record.'
                );
            }

            foreach (
                $processedItems
                as $processedItem
            ) {
                $saleItemData = [
                    'sale_id' =>
                        $saleId,
                    'product_id' =>
                        $processedItem[
                            'product_id'
                        ],
                    'quantity' =>
                        $processedItem[
                            'quantity'
                        ],
                    'buying_price' =>
                        round(
                            $processedItem[
                                'buying_price'
                            ],
                            2
                        ),
                    'selling_price' =>
                        round(
                            $processedItem[
                                'selling_price'
                            ],
                            2
                        ),
                    'profit' =>
                        round(
                            $processedItem[
                                'profit'
                            ],
                            2
                        ),
                ];

                $db
                    ->table('sale_items')
                    ->insert(
                        $saleItemData
                    );

                $builder =
                    $db->table(
                        'products'
                    );

                $builder
                    ->set(
                        'quantity',
                        'quantity - ' .
                        (int)
                            $processedItem[
                                'quantity'
                            ],
                        false
                    )
                    ->set(
                        'updated_at',
                        date(
                            'Y-m-d H:i:s'
                        )
                    )
                    ->where(
                        'id',
                        $processedItem[
                            'product_id'
                        ]
                    )
                    ->where(
                        'quantity >=',
                        $processedItem[
                            'quantity'
                        ]
                    )
                    ->update();

                if (
                    $db->affectedRows() !== 1
                ) {
                    throw new \RuntimeException(
                        'Stock changed while processing the sale. Please try again.'
                    );
                }
            }

            if (
                $db->transStatus() === false
            ) {
                throw new \RuntimeException(
                    'Database transaction failed.'
                );
            }

            $db->transCommit();

            return $this->respond(
                [
                    'success' => true,
                    'message' =>
                        'Sale completed successfully.',
                    'sale' => [
                        'id' =>
                            $saleId,
                        'sale_number' =>
                            $saleNumber,
                        'user_id' =>
                            $userId,
                        'total_amount' =>
                            round(
                                $totalAmount,
                                2
                            ),
                        'total_profit' =>
                            round(
                                $totalProfit,
                                2
                            ),
                        'customer_name' =>
                            $customerName,
                        'payment_method' =>
                            $paymentMethod,
                        'status' =>
                            'completed',
                    ],
                ],
                201
            );
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message(
                'error',
                'Salesperson sale creation failed: ' .
                $e->getMessage()
            );

            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        $e->getMessage(),
                ],
                400
            );
        }
    }
}