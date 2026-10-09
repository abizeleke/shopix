<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ProductModel;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class ProductController extends BaseController
{
    use ResponseTrait;

    protected ProductModel $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    /**
     * GET /api/products
     *
     * Optional query parameters:
     *
     * ?search=iphone
     * ?category_id=1
     * ?status=active
     */
    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $categoryId = $this->request->getGet('category_id');
        $status = $this->request->getGet('status');

        $builder = $this->productModel
            ->select(
                'products.*, categories.name AS category_name'
            )
            ->join(
                'categories',
                'categories.id = products.category_id',
                'left'
            );

        /*
         * Search
         */
        if ($search !== '') {
            $builder->groupStart()
                ->like('products.name', $search)
                ->orLike('products.product_code', $search)
                ->orLike('products.description', $search)
                ->orLike('categories.name', $search)
                ->groupEnd();
        }

        /*
         * Category filter
         *
         * category_id=1
         */
        if ($categoryId !== null && $categoryId !== '') {

            if (!is_numeric($categoryId)) {
                return $this->failValidationErrors([
                    'category_id' => 'Invalid category ID.',
                ]);
            }

            $builder->where(
                'products.category_id',
                (int) $categoryId
            );
        }

        /*
         * Status filter
         */
        if ($status !== null && $status !== '') {

            if (!in_array($status, ['active', 'inactive'], true)) {
                return $this->failValidationErrors([
                    'status' => 'Status must be active or inactive.',
                ]);
            }

            $builder->where(
                'products.status',
                $status
            );
        }

        $products = $builder
            ->orderBy('products.name', 'ASC')
            ->findAll();

        return $this->respond([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * GET /api/products/{id}
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid product ID.',
            ]);
        }

        $product = $this->productModel
            ->select(
                'products.*, categories.name AS category_name'
            )
            ->join(
                'categories',
                'categories.id = products.category_id',
                'left'
            )
            ->where(
                'products.id',
                (int) $id
            )
            ->first();

        if (!$product) {
            return $this->failNotFound(
                'Product not found.'
            );
        }

        return $this->respond([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * POST /api/products
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->failValidationErrors([
                'body' => 'Invalid request body.',
            ]);
        }

        /*
         * Name
         */
        $name = trim(
            (string) ($data['name'] ?? '')
        );

        if ($name === '') {
            return $this->failValidationErrors([
                'name' => 'Product name is required.',
            ]);
        }

        if (strlen($name) > 200) {
            return $this->failValidationErrors([
                'name' => 'Product name cannot exceed 200 characters.',
            ]);
        }

        /*
         * Category
         *
         * NULL means No Category.
         */
        $categoryId = null;

        if (
            array_key_exists('category_id', $data) &&
            $data['category_id'] !== null &&
            $data['category_id'] !== ''
        ) {
            if (!is_numeric($data['category_id'])) {
                return $this->failValidationErrors([
                    'category_id' => 'Invalid category ID.',
                ]);
            }

            $categoryId = (int) $data['category_id'];

            $categoryExists = Database::connect()
                ->table('categories')
                ->where('id', $categoryId)
                ->countAllResults();

            if ($categoryExists === 0) {
                return $this->failValidationErrors([
                    'category_id' => 'Selected category does not exist.',
                ]);
            }
        }

        /*
         * Description
         */
        $description = null;

        if (
            array_key_exists('description', $data) &&
            $data['description'] !== null
        ) {
            $description = trim(
                (string) $data['description']
            );
        }

        /*
         * Product code
         *
         * Product code is optional.
         */
        $productCode = null;

        if (
            array_key_exists('product_code', $data) &&
            $data['product_code'] !== null &&
            trim((string) $data['product_code']) !== ''
        ) {
            $productCode = trim(
                (string) $data['product_code']
            );

            if (strlen($productCode) > 100) {
                return $this->failValidationErrors([
                    'product_code' =>
                        'Product code cannot exceed 100 characters.',
                ]);
            }

            $existingCode = $this->productModel
                ->where('product_code', $productCode)
                ->first();

            if ($existingCode) {
                return $this->failResourceExists(
                    'A product with this product code already exists.'
                );
            }
        }

        /*
         * Buying price
         */
        $buyingPrice = $data['buying_price'] ?? null;

        if (
            $buyingPrice === null ||
            !is_numeric($buyingPrice) ||
            (float) $buyingPrice < 0
        ) {
            return $this->failValidationErrors([
                'buying_price' =>
                    'Buying price must be a valid number greater than or equal to 0.',
            ]);
        }

        $buyingPrice = round(
            (float) $buyingPrice,
            2
        );

        /*
         * Selling price
         */
        $sellingPrice = $data['selling_price'] ?? null;

        if (
            $sellingPrice === null ||
            !is_numeric($sellingPrice) ||
            (float) $sellingPrice < 0
        ) {
            return $this->failValidationErrors([
                'selling_price' =>
                    'Selling price must be a valid number greater than or equal to 0.',
            ]);
        }

        $sellingPrice = round(
            (float) $sellingPrice,
            2
        );

        /*
         * Quantity
         */
        $quantity = $data['quantity'] ?? 0;

        if (
            !is_numeric($quantity) ||
            (int) $quantity < 0 ||
            (int) $quantity != $quantity
        ) {
            return $this->failValidationErrors([
                'quantity' =>
                    'Quantity must be a whole number greater than or equal to 0.',
            ]);
        }

        $quantity = (int) $quantity;

        /*
         * Image
         *
         * For now this accepts an image path/string.
         * Actual multipart image upload can be added separately.
         */
        $image = null;

        if (
            array_key_exists('image', $data) &&
            $data['image'] !== null &&
            trim((string) $data['image']) !== ''
        ) {
            $image = trim(
                (string) $data['image']
            );

            if (strlen($image) > 255) {
                return $this->failValidationErrors([
                    'image' =>
                        'Image path cannot exceed 255 characters.',
                ]);
            }
        }

        /*
         * Status
         */
        $status = $data['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->failValidationErrors([
                'status' =>
                    'Status must be active or inactive.',
            ]);
        }

        /*
         * Create product
         */
        $productData = [
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'product_code' => $productCode,
            'buying_price' => $buyingPrice,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'image' => $image,
            'status' => $status,
        ];

        if (!$this->productModel->insert($productData)) {
            return $this->failValidationErrors(
                $this->productModel->errors()
            );
        }

        $productId = $this->productModel->getInsertID();

        $product = $this->getProductWithCategory(
            (int) $productId
        );

        return $this->respondCreated([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ]);
    }

    /**
     * PUT /api/products/{id}
     */
    public function update($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid product ID.',
            ]);
        }

        $id = (int) $id;

        $existingProduct = $this->productModel->find($id);

        if (!$existingProduct) {
            return $this->failNotFound(
                'Product not found.'
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->failValidationErrors([
                'body' => 'Invalid request body.',
            ]);
        }

        $updateData = [];

        /*
         * Category
         */
        if (array_key_exists('category_id', $data)) {

            if (
                $data['category_id'] === null ||
                $data['category_id'] === ''
            ) {
                $updateData['category_id'] = null;
            } else {

                if (!is_numeric($data['category_id'])) {
                    return $this->failValidationErrors([
                        'category_id' =>
                            'Invalid category ID.',
                    ]);
                }

                $categoryId = (int) $data['category_id'];

                $categoryExists = Database::connect()
                    ->table('categories')
                    ->where('id', $categoryId)
                    ->countAllResults();

                if ($categoryExists === 0) {
                    return $this->failValidationErrors([
                        'category_id' =>
                            'Selected category does not exist.',
                    ]);
                }

                $updateData['category_id'] =
                    $categoryId;
            }
        }

        /*
         * Name
         */
        if (array_key_exists('name', $data)) {

            $name = trim(
                (string) $data['name']
            );

            if ($name === '') {
                return $this->failValidationErrors([
                    'name' =>
                        'Product name is required.',
                ]);
            }

            if (strlen($name) > 200) {
                return $this->failValidationErrors([
                    'name' =>
                        'Product name cannot exceed 200 characters.',
                ]);
            }

            $updateData['name'] = $name;
        }

        /*
         * Description
         */
        if (array_key_exists('description', $data)) {

            $updateData['description'] =
                $data['description'] === null
                    ? null
                    : trim(
                        (string) $data['description']
                    );
        }

        /*
         * Product code
         */
        if (array_key_exists('product_code', $data)) {

            $productCode = null;

            if (
                $data['product_code'] !== null &&
                trim((string) $data['product_code']) !== ''
            ) {
                $productCode = trim(
                    (string) $data['product_code']
                );

                if (strlen($productCode) > 100) {
                    return $this->failValidationErrors([
                        'product_code' =>
                            'Product code cannot exceed 100 characters.',
                    ]);
                }

                $duplicate = $this->productModel
                    ->where(
                        'product_code',
                        $productCode
                    )
                    ->where('id !=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->failResourceExists(
                        'A product with this product code already exists.'
                    );
                }
            }

            $updateData['product_code'] =
                $productCode;
        }

        /*
         * Buying price
         */
        if (array_key_exists('buying_price', $data)) {

            if (
                !is_numeric($data['buying_price']) ||
                (float) $data['buying_price'] < 0
            ) {
                return $this->failValidationErrors([
                    'buying_price' =>
                        'Buying price must be a valid number greater than or equal to 0.',
                ]);
            }

            $updateData['buying_price'] =
                round(
                    (float) $data['buying_price'],
                    2
                );
        }

        /*
         * Selling price
         */
        if (array_key_exists('selling_price', $data)) {

            if (
                !is_numeric($data['selling_price']) ||
                (float) $data['selling_price'] < 0
            ) {
                return $this->failValidationErrors([
                    'selling_price' =>
                        'Selling price must be a valid number greater than or equal to 0.',
                ]);
            }

            $updateData['selling_price'] =
                round(
                    (float) $data['selling_price'],
                    2
                );
        }

        /*
         * Quantity
         */
        if (array_key_exists('quantity', $data)) {

            if (
                !is_numeric($data['quantity']) ||
                (int) $data['quantity'] < 0 ||
                (int) $data['quantity'] != $data['quantity']
            ) {
                return $this->failValidationErrors([
                    'quantity' =>
                        'Quantity must be a whole number greater than or equal to 0.',
                ]);
            }

            $updateData['quantity'] =
                (int) $data['quantity'];
        }

        /*
         * Image
         */
        if (array_key_exists('image', $data)) {

            $image = null;

            if (
                $data['image'] !== null &&
                trim((string) $data['image']) !== ''
            ) {
                $image = trim(
                    (string) $data['image']
                );

                if (strlen($image) > 255) {
                    return $this->failValidationErrors([
                        'image' =>
                            'Image path cannot exceed 255 characters.',
                    ]);
                }
            }

            $updateData['image'] = $image;
        }

        /*
         * Status
         */
        if (array_key_exists('status', $data)) {

            $status = $data['status'];

            if (!in_array(
                $status,
                ['active', 'inactive'],
                true
            )) {
                return $this->failValidationErrors([
                    'status' =>
                        'Status must be active or inactive.',
                ]);
            }

            $updateData['status'] = $status;
        }

        if (empty($updateData)) {
            return $this->failValidationErrors([
                'body' =>
                    'No fields were provided for update.',
            ]);
        }

        if (!$this->productModel->update(
            $id,
            $updateData
        )) {
            return $this->failValidationErrors(
                $this->productModel->errors()
            );
        }

        $product = $this->getProductWithCategory($id);

        return $this->respond([
            'success' => true,
            'message' =>
                'Product updated successfully.',
            'data' => $product,
        ]);
    }

    /**
     * DELETE /api/products/{id}
     *
     * Products referenced by sales cannot be physically
     * deleted because sale_items keeps the historical record.
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid product ID.',
            ]);
        }

        $id = (int) $id;

        $product = $this->productModel->find($id);

        if (!$product) {
            return $this->failNotFound(
                'Product not found.'
            );
        }

        /*
         * Check whether this product has already
         * appeared in a sale.
         */
        $db = Database::connect();

        $saleItemCount = $db->table('sale_items')
            ->where('product_id', $id)
            ->countAllResults();

        if ($saleItemCount > 0) {

            return $this->failResourceExists(
                'This product cannot be deleted because it has already been used in a sale. Deactivate it instead.'
            );
        }

        if (!$this->productModel->delete($id)) {
            return $this->failServerError(
                'Unable to delete the product.'
            );
        }

        return $this->respond([
            'success' => true,
            'message' =>
                'Product deleted successfully.',
        ]);
    }

    /**
     * Return a product with its category name.
     */
    private function getProductWithCategory(
        int $id
    ): ?array {
        return $this->productModel
            ->select(
                'products.*, categories.name AS category_name'
            )
            ->join(
                'categories',
                'categories.id = products.category_id',
                'left'
            )
            ->where(
                'products.id',
                $id
            )
            ->first();
    }
}