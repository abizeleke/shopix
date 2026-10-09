<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use CodeIgniter\API\ResponseTrait;

class CategoryController extends BaseController
{
    use ResponseTrait;

    protected CategoryModel $categoryModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
    }

    /**
     * GET /api/categories
     *
     * Returns all categories.
     *
     * Query parameters:
     * ?status=active
     * ?search=phone
     */
    public function index()
    {
        $status = $this->request->getGet('status');
        $search = trim((string) $this->request->getGet('search'));

        $builder = $this->categoryModel->builder();

        if ($status !== null && $status !== '') {
            if (!in_array($status, ['active', 'inactive'], true)) {
                return $this->failValidationErrors([
                    'status' => 'Status must be active or inactive.',
                ]);
            }

            $builder->where('status', $status);
        }

        if ($search !== '') {
            $builder->like('name', $search);
        }

        $categories = $builder
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * GET /api/categories/{id}
     */
    public function show($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid category ID.',
            ]);
        }

        $category = $this->categoryModel->find((int) $id);

        if (!$category) {
            return $this->failNotFound('Category not found.');
        }

        return $this->respond([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * POST /api/categories
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->failValidationErrors([
                'body' => 'Invalid request body.',
            ]);
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->failValidationErrors([
                'name' => 'Category name is required.',
            ]);
        }

        $description = isset($data['description'])
            ? trim((string) $data['description'])
            : null;

        $threshold = $data['low_stock_threshold'] ?? 10;

        if (!is_numeric($threshold) || (int) $threshold < 0) {
            return $this->failValidationErrors([
                'low_stock_threshold' =>
                    'Low stock threshold must be a whole number greater than or equal to 0.',
            ]);
        }

        $threshold = (int) $threshold;

        $status = $data['status'] ?? 'active';

        if (!in_array($status, ['active', 'inactive'], true)) {
            return $this->failValidationErrors([
                'status' => 'Status must be active or inactive.',
            ]);
        }

        // Prevent duplicate category names.
        $existing = $this->categoryModel
            ->where('LOWER(name)', strtolower($name))
            ->first();

        if ($existing) {
            return $this->failResourceExists(
                'A category with this name already exists.'
            );
        }

        $categoryData = [
            'name' => $name,
            'description' => $description,
            'low_stock_threshold' => $threshold,
            'status' => $status,
        ];

        if (!$this->categoryModel->insert($categoryData)) {
            return $this->failValidationErrors(
                $this->categoryModel->errors()
            );
        }

        $categoryId = $this->categoryModel->getInsertID();

        $category = $this->categoryModel->find($categoryId);

        return $this->respondCreated([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ]);
    }

    /**
     * PUT /api/categories/{id}
     */
    public function update($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid category ID.',
            ]);
        }

        $id = (int) $id;

        $category = $this->categoryModel->find($id);

        if (!$category) {
            return $this->failNotFound('Category not found.');
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->failValidationErrors([
                'body' => 'Invalid request body.',
            ]);
        }

        $updateData = [];

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);

            if ($name === '') {
                return $this->failValidationErrors([
                    'name' => 'Category name is required.',
                ]);
            }

            if (strlen($name) > 100) {
                return $this->failValidationErrors([
                    'name' => 'Category name cannot exceed 100 characters.',
                ]);
            }

            // Check duplicate name, excluding current category.
            $duplicate = $this->categoryModel
                ->where('LOWER(name)', strtolower($name))
                ->where('id !=', $id)
                ->first();

            if ($duplicate) {
                return $this->failResourceExists(
                    'A category with this name already exists.'
                );
            }

            $updateData['name'] = $name;
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] =
                $data['description'] === null
                    ? null
                    : trim((string) $data['description']);
        }

        if (array_key_exists('low_stock_threshold', $data)) {
            $threshold = $data['low_stock_threshold'];

            if (!is_numeric($threshold) || (int) $threshold < 0) {
                return $this->failValidationErrors([
                    'low_stock_threshold' =>
                        'Low stock threshold must be a whole number greater than or equal to 0.',
                ]);
            }

            $updateData['low_stock_threshold'] = (int) $threshold;
        }

        if (array_key_exists('status', $data)) {
            $status = $data['status'];

            if (!in_array($status, ['active', 'inactive'], true)) {
                return $this->failValidationErrors([
                    'status' => 'Status must be active or inactive.',
                ]);
            }

            $updateData['status'] = $status;
        }

        if (empty($updateData)) {
            return $this->failValidationErrors([
                'body' => 'No fields were provided for update.',
            ]);
        }

        if (!$this->categoryModel->update($id, $updateData)) {
            return $this->failValidationErrors(
                $this->categoryModel->errors()
            );
        }

        $updatedCategory = $this->categoryModel->find($id);

        return $this->respond([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $updatedCategory,
        ]);
    }

    /**
     * DELETE /api/categories/{id}
     *
     * A category cannot be deleted if products still use it.
     */
    public function delete($id)
    {
        if (!is_numeric($id)) {
            return $this->failValidationErrors([
                'id' => 'Invalid category ID.',
            ]);
        }

        $id = (int) $id;

        $category = $this->categoryModel->find($id);

        if (!$category) {
            return $this->failNotFound('Category not found.');
        }

        $db = \Config\Database::connect();

        $productCount = $db->table('products')
            ->where('category_id', $id)
            ->countAllResults();

        if ($productCount > 0) {
            return $this->failResourceExists(
                'This category cannot be deleted because products are using it. Move or remove those products first.'
            );
        }

        if (!$this->categoryModel->delete($id)) {
            return $this->failServerError(
                'Unable to delete the category.'
            );
        }

        return $this->respond([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}