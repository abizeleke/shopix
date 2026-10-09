<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class SalesController extends BaseController
{
    use ResponseTrait;

    // ============================================================
    // DATABASE ERROR HELPER
    // ============================================================

    private function databaseError($db): string
    {
        $error = $db->error();

        if (is_array($error) && !empty($error['message'])) {
            return (string) $error['message'];
        }

        return 'Database query failed.';
    }


    // ============================================================
    // LIST SALES
    // ============================================================

    public function index()
    {
        try {

            $db = Database::connect();

            $startDate = trim(
                (string) ($this->request->getGet('start_date') ?? '')
            );

            $endDate = trim(
                (string) ($this->request->getGet('end_date') ?? '')
            );

            $search = trim(
                (string) ($this->request->getGet('search') ?? '')
            );


            // ----------------------------------------------------
            // SALES QUERY
            // ----------------------------------------------------

            $builder = $db->table('sales s');

            $builder->select(
                's.id,
                 s.sale_number,
                 s.user_id,
                 s.customer_name,
                 s.total_amount,
                 s.total_profit,
                 s.payment_method,
                 s.status,
                 s.cancelled_by,
                 s.cancelled_at,
                 s.created_at,
                 s.updated_at,
                 u.full_name AS sold_by'
            );

            $builder->join(
                'users u',
                'u.id = s.user_id',
                'left'
            );


            // ----------------------------------------------------
            // DATE FILTER
            // ----------------------------------------------------

            if ($startDate !== '') {

                $builder->where(
                    's.created_at >=',
                    $startDate . ' 00:00:00'
                );
            }


            if ($endDate !== '') {

                $builder->where(
                    's.created_at <=',
                    $endDate . ' 23:59:59'
                );
            }


            // ----------------------------------------------------
            // SEARCH
            // ----------------------------------------------------

            if ($search !== '') {

                $builder->groupStart()

                    ->like(
                        's.sale_number',
                        $search
                    )

                    ->orLike(
                        's.customer_name',
                        $search
                    )

                    ->orLike(
                        'u.full_name',
                        $search
                    )

                    ->groupEnd();
            }


            // ----------------------------------------------------
            // ORDER
            // ----------------------------------------------------

            $builder->orderBy(
                's.created_at',
                'DESC'
            );


            // ----------------------------------------------------
            // EXECUTE QUERY SAFELY
            // ----------------------------------------------------

            $query = $builder->get();

            if ($query === false) {

                throw new \RuntimeException(
                    $this->databaseError($db)
                );
            }

            $sales = $query->getResultArray();


            // ----------------------------------------------------
            // ADD SALE ITEMS
            // ----------------------------------------------------

            foreach ($sales as &$sale) {

                $itemsBuilder = $db->table('sale_items si');

                $itemsBuilder->select(
                    'si.id,
                     si.product_id,
                     p.name AS product_name,
                     si.quantity,
                     si.buying_price,
                     si.selling_price,
                     si.profit'
                );

                $itemsBuilder->join(
                    'products p',
                    'p.id = si.product_id',
                    'left'
                );

                $itemsBuilder->where(
                    'si.sale_id',
                    $sale['id']
                );


                $itemsQuery = $itemsBuilder->get();

                if ($itemsQuery === false) {

                    throw new \RuntimeException(
                        $this->databaseError($db)
                    );
                }


                $sale['items'] = $itemsQuery->getResultArray();
            }

            unset($sale);


            // ----------------------------------------------------
            // RESPONSE
            // ----------------------------------------------------

            return $this->response->setJSON([
                'success' => true,
                'sales' => $sales,
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Could not load sales.',
                    'error' => $e->getMessage(),
                ]);
        }
    }


    // ============================================================
    // CREATE SALE
    // ============================================================

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid request.',
                ]);
        }


        // --------------------------------------------------------
        // AUTHENTICATED USER
        // --------------------------------------------------------

        $userId = (int) ($this->request->userId ?? 0);

        if ($userId <= 0) {

            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication required.',
                ]);
        }


        // --------------------------------------------------------
        // REQUEST DATA
        // --------------------------------------------------------

        $items = $data['items'] ?? [];

        $paymentMethod = strtolower(
            trim(
                (string) (
                    $data['payment_method'] ?? 'cash'
                )
            )
        );

        $customerName = trim(
            (string) (
                $data['customer_name'] ?? ''
            )
        );


        // --------------------------------------------------------
        // VALIDATION
        // --------------------------------------------------------

        if (!is_array($items) || count($items) === 0) {

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'At least one product is required.',
                ]);
        }


        if (!in_array(
            $paymentMethod,
            ['cash', 'bank'],
            true
        )) {

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid payment method.',
                ]);
        }


        if (
            $customerName !== '' &&
            mb_strlen($customerName) > 150
        ) {

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Customer name is too long.',
                ]);
        }


        // --------------------------------------------------------
        // DATABASE
        // --------------------------------------------------------

        $db = Database::connect();

        $db->transBegin();


        try {

            $totalAmount = 0;

            $totalProfit = 0;

            $preparedItems = [];


            // ====================================================
            // VALIDATE EVERY PRODUCT
            // ====================================================

            foreach ($items as $item) {

                $productId = (int) (
                    $item['product_id'] ?? 0
                );

                $quantity = (int) (
                    $item['quantity'] ?? 0
                );

                $sellingPrice = (float) (
                    $item['selling_price'] ?? 0
                );


                if ($productId <= 0) {

                    throw new \RuntimeException(
                        'Invalid product.'
                    );
                }


                if ($quantity <= 0) {

                    throw new \RuntimeException(
                        'Quantity must be greater than zero.'
                    );
                }


                if ($sellingPrice <= 0) {

                    throw new \RuntimeException(
                        'Selling price must be greater than zero.'
                    );
                }


                // ------------------------------------------------
                // LOCK PRODUCT
                // ------------------------------------------------

                $productQuery = $db->query(
                    'SELECT *
                     FROM products
                     WHERE id = ?
                     FOR UPDATE',
                    [$productId]
                );


                if ($productQuery === false) {

                    throw new \RuntimeException(
                        'Could not read product from database: ' .
                        $this->databaseError($db)
                    );
                }


                $product = $productQuery->getRowArray();


                if (!$product) {

                    throw new \RuntimeException(
                        'Product not found.'
                    );
                }


                // ------------------------------------------------
                // ACTIVE CHECK
                // ------------------------------------------------

                if (
                    strtolower(
                        (string) $product['status']
                    ) !== 'active'
                ) {

                    throw new \RuntimeException(
                        'Product "' .
                        $product['name'] .
                        '" is not active.'
                    );
                }


                // ------------------------------------------------
                // STOCK CHECK
                // ------------------------------------------------

                $stock = (int) $product['quantity'];


                if ($quantity > $stock) {

                    throw new \RuntimeException(
                        'Not enough stock for "' .
                        $product['name'] .
                        '". Available: ' .
                        $stock .
                        '.'
                    );
                }


                // ------------------------------------------------
                // BUYING PRICE
                // ------------------------------------------------

                $buyingPrice = (float) $product['buying_price'];


                // ------------------------------------------------
                // PRICE VALIDATION
                // ------------------------------------------------

                if ($sellingPrice < $buyingPrice) {

                    throw new \RuntimeException(
                        'Selling price for "' .
                        $product['name'] .
                        '" cannot be below the buying price.'
                    );
                }


                // ------------------------------------------------
                // CALCULATE
                // ------------------------------------------------

                $lineTotal =
                    $sellingPrice *
                    $quantity;

                $lineProfit =
                    (
                        $sellingPrice -
                        $buyingPrice
                    ) *
                    $quantity;


                $totalAmount += $lineTotal;

                $totalProfit += $lineProfit;


                // ------------------------------------------------
                // PREPARE
                // ------------------------------------------------

                $preparedItems[] = [
                    'product' => $product,

                    'product_id' => $productId,

                    'quantity' => $quantity,

                    'buying_price' => $buyingPrice,

                    'selling_price' => $sellingPrice,

                    'profit' => $lineProfit,
                ];
            }


            // ====================================================
            // GENERATE SALE NUMBER
            // ====================================================

            $saleNumber = $this->generateSaleNumber($db);


            // ====================================================
            // CREATE SALE
            // ====================================================

            $saleInserted = $db->table('sales')->insert([

                'sale_number' => $saleNumber,

                'user_id' => $userId,

                'customer_name' =>
                    $customerName !== ''
                        ? $customerName
                        : null,

                'total_amount' => number_format(
                    $totalAmount,
                    2,
                    '.',
                    ''
                ),

                'total_profit' => number_format(
                    $totalProfit,
                    2,
                    '.',
                    ''
                ),

                'payment_method' => $paymentMethod,

                'status' => 'completed',
            ]);


            if ($saleInserted === false) {

                throw new \RuntimeException(
                    'Could not create sale: ' .
                    $this->databaseError($db)
                );
            }


            $saleId = $db->insertID();


            if (!$saleId) {

                throw new \RuntimeException(
                    'Could not create sale.'
                );
            }


            // ====================================================
            // CREATE ITEMS + REDUCE STOCK
            // ====================================================

            foreach ($preparedItems as $item) {

                $itemInserted = $db->table('sale_items')->insert([

                    'sale_id' =>
                        $saleId,

                    'product_id' =>
                        $item['product_id'],

                    'quantity' =>
                        $item['quantity'],

                    'buying_price' =>
                        number_format(
                            $item['buying_price'],
                            2,
                            '.',
                            ''
                        ),

                    'selling_price' =>
                        number_format(
                            $item['selling_price'],
                            2,
                            '.',
                            ''
                        ),

                    'profit' =>
                        number_format(
                            $item['profit'],
                            2,
                            '.',
                            ''
                        ),
                ]);


                if ($itemInserted === false) {

                    throw new \RuntimeException(
                        'Could not create sale item: ' .
                        $this->databaseError($db)
                    );
                }


                // ----------------------------------------------
                // REDUCE STOCK
                // ----------------------------------------------

                $newQuantity =
                    (int) $item['product']['quantity'] -
                    $item['quantity'];


                $updated = $db->table('products')
                    ->where(
                        'id',
                        $item['product_id']
                    )
                    ->update([
                        'quantity' =>
                            $newQuantity,

                        'updated_at' =>
                            date('Y-m-d H:i:s'),
                    ]);


                if ($updated === false) {

                    throw new \RuntimeException(
                        'Could not update product stock: ' .
                        $this->databaseError($db)
                    );
                }
            }


            // ====================================================
            // TRANSACTION CHECK
            // ====================================================

            if ($db->transStatus() === false) {

                throw new \RuntimeException(
                    'Sale transaction failed.'
                );
            }


            $db->transCommit();


            // ====================================================
            // SUCCESS
            // ====================================================

            return $this->response
                ->setStatusCode(201)
                ->setJSON([
                    'success' => true,

                    'message' =>
                        'Sale completed successfully.',

                    'sale' => [

                        'id' =>
                            $saleId,

                        'sale_number' =>
                            $saleNumber,

                        'customer_name' =>
                            $customerName !== ''
                                ? $customerName
                                : null,

                        'total_amount' =>
                            $totalAmount,

                        'total_profit' =>
                            $totalProfit,

                        'payment_method' =>
                            $paymentMethod,

                        'status' =>
                            'completed',
                    ],
                ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
        }
    }


    // ============================================================
    // CANCEL SALE
    // ============================================================

    public function cancel($id)
    {
        $userId = (int) (
            $this->request->userId ?? 0
        );


        if ($userId <= 0) {

            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication required.',
                ]);
        }


        $db = Database::connect();


        // --------------------------------------------------------
        // CHECK USER ROLE
        // --------------------------------------------------------

        $userQuery = $db->table('users')
            ->select('role')
            ->where('id', $userId)
            ->get();


        if ($userQuery === false) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' =>
                        'Could not verify user role.',
                    'error' =>
                        $this->databaseError($db),
                ]);
        }


        $user = $userQuery->getRowArray();


        if (
            !$user ||
            strtolower(
                (string) $user['role']
            ) !== 'admin'
        ) {

            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'message' =>
                        'Only administrators can cancel sales.',
                ]);
        }


        // --------------------------------------------------------
        // SALE ID
        // --------------------------------------------------------

        $saleId = (int) $id;


        if ($saleId <= 0) {

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid sale ID.',
                ]);
        }


        $db->transBegin();


        try {

            // ----------------------------------------------------
            // LOCK SALE
            // ----------------------------------------------------

            $saleQuery = $db->query(
                'SELECT *
                 FROM sales
                 WHERE id = ?
                 FOR UPDATE',
                [$saleId]
            );


            if ($saleQuery === false) {

                throw new \RuntimeException(
                    'Could not read sale: ' .
                    $this->databaseError($db)
                );
            }


            $sale = $saleQuery->getRowArray();


            if (!$sale) {

                throw new \RuntimeException(
                    'Sale not found.'
                );
            }


            // ----------------------------------------------------
            // ALREADY CANCELLED
            // ----------------------------------------------------

            if (
                strtolower(
                    (string) $sale['status']
                ) === 'cancelled'
            ) {

                throw new \RuntimeException(
                    'This sale has already been cancelled.'
                );
            }


            // ----------------------------------------------------
            // GET ITEMS
            // ----------------------------------------------------

            $itemsQuery = $db->table('sale_items')
                ->where(
                    'sale_id',
                    $saleId
                )
                ->get();


            if ($itemsQuery === false) {

                throw new \RuntimeException(
                    'Could not load sale items: ' .
                    $this->databaseError($db)
                );
            }


            $items = $itemsQuery->getResultArray();


            // ----------------------------------------------------
            // RESTORE STOCK
            // ----------------------------------------------------

            foreach ($items as $item) {

                $productQuery = $db->query(
                    'SELECT quantity
                     FROM products
                     WHERE id = ?
                     FOR UPDATE',
                    [
                        $item['product_id']
                    ]
                );


                if ($productQuery === false) {

                    throw new \RuntimeException(
                        'Could not read product while cancelling sale: ' .
                        $this->databaseError($db)
                    );
                }


                $product = $productQuery->getRowArray();


                if (!$product) {

                    throw new \RuntimeException(
                        'Product associated with the sale was not found.'
                    );
                }


                $newQuantity =
                    (int) $product['quantity'] +
                    (int) $item['quantity'];


                $updated = $db->table('products')
                    ->where(
                        'id',
                        $item['product_id']
                    )
                    ->update([
                        'quantity' =>
                            $newQuantity,

                        'updated_at' =>
                            date('Y-m-d H:i:s'),
                    ]);


                if ($updated === false) {

                    throw new \RuntimeException(
                        'Could not restore product stock: ' .
                        $this->databaseError($db)
                    );
                }
            }


            // ----------------------------------------------------
            // MARK SALE CANCELLED
            // ----------------------------------------------------

            $updatedSale = $db->table('sales')
                ->where(
                    'id',
                    $saleId
                )
                ->update([
                    'status' =>
                        'cancelled',

                    'cancelled_by' =>
                        $userId,

                    'cancelled_at' =>
                        date('Y-m-d H:i:s'),

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]);


            if ($updatedSale === false) {

                throw new \RuntimeException(
                    'Could not cancel sale: ' .
                    $this->databaseError($db)
                );
            }


            // ----------------------------------------------------
            // TRANSACTION CHECK
            // ----------------------------------------------------

            if ($db->transStatus() === false) {

                throw new \RuntimeException(
                    'Could not cancel sale.'
                );
            }


            $db->transCommit();


            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    'Sale cancelled successfully.',
            ]);

        } catch (\Throwable $e) {

            $db->transRollback();

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]);
        }
    }


    // ============================================================
    // SALE NUMBER
    // ============================================================

    private function generateSaleNumber($db): string
    {
        do {

            $number =
                'SALE-' .
                date('YmdHis') .
                '-' .
                strtoupper(
                    substr(
                        bin2hex(
                            random_bytes(3)
                        ),
                        0,
                        6
                    )
                );


            $exists =
                $db->table('sales')
                    ->where(
                        'sale_number',
                        $number
                    )
                    ->countAllResults() > 0;

        } while ($exists);


        return $number;
    }

    // ============================================================
// SALES PERSON DASHBOARD
// ============================================================


public function dashboard()
{
    /*
     * Authentication is still required because the route
     * uses the auth filter.
     *
     * However, the dashboard intentionally does NOT filter
     * by user_id.
     *
     * This dashboard shows overall/company-wide sales data.
     */

    try {
        $db = Database::connect();

        // --------------------------------------------------------
        // TODAY'S OVERALL SALES SUMMARY
        // --------------------------------------------------------

        $summary = $db->query(
            "
            SELECT
                COUNT(*) AS total_sales,

                COALESCE(
                    SUM(total_amount),
                    0
                ) AS total_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN LOWER(TRIM(payment_method)) = 'cash'
                            THEN total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS cash_amount,

                COALESCE(
                    SUM(
                        CASE
                            WHEN LOWER(TRIM(payment_method)) = 'bank'
                            THEN total_amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS bank_amount

            FROM sales

            WHERE created_at >= CURRENT_DATE

              AND created_at < CURRENT_DATE + INTERVAL '1 day'

              AND LOWER(TRIM(status)) = 'completed'
            "
        )->getRowArray();

        // --------------------------------------------------------
        // RECENT OVERALL SALES
        // --------------------------------------------------------

        $sales = $db->query(
            "
            SELECT
                s.id,
                s.sale_number,
                s.customer_name,
                s.total_amount,
                s.payment_method,
                s.status,
                s.created_at,

                u.full_name AS salesperson_name

            FROM sales s

            LEFT JOIN users u
                ON u.id = s.user_id

            WHERE LOWER(TRIM(s.status)) = 'completed'

            ORDER BY s.created_at DESC

            LIMIT 5
            "
        )->getResultArray();

        // --------------------------------------------------------
        // GET ITEMS FOR EACH RECENT SALE
        // --------------------------------------------------------

        foreach ($sales as &$sale) {
            $items = $db->query(
                "
                SELECT
                    si.product_id,
                    p.name AS product_name,
                    si.quantity,
                    si.selling_price

                FROM sale_items si

                LEFT JOIN products p
                    ON p.id = si.product_id

                WHERE si.sale_id = ?

                ORDER BY si.id ASC
                ",
                [$sale['id']]
            )->getResultArray();

            $sale['items'] = $items;
        }

        unset($sale);

        // --------------------------------------------------------
        // RESPONSE
        // --------------------------------------------------------

        return $this->response->setJSON([
            'success' => true,

            'stats' => [
                'today_sales' => (int) (
                    $summary['total_sales'] ?? 0
                ),

                'sales_amount' => (float) (
                    $summary['total_amount'] ?? 0
                ),

                'cash' => (float) (
                    $summary['cash_amount'] ?? 0
                ),

                'bank' => (float) (
                    $summary['bank_amount'] ?? 0
                ),
            ],

            'recent_sales' => $sales,
        ]);

    } catch (\Throwable $e) {

        return $this->response
            ->setStatusCode(500)
            ->setJSON([
                'success' => false,
                'message' => 'Could not load dashboard.',
                'error' => $e->getMessage(),
            ]);
    }
}
}