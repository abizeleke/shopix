<?php

namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'name',
        'description',
        'low_stock_threshold',
        'status',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'description' => 'permit_empty',
        'low_stock_threshold' => 'required|integer|greater_than_equal_to[0]',
        'status' => 'required|in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Category name is required.',
            'max_length' => 'Category name cannot exceed 100 characters.',
        ],
        'low_stock_threshold' => [
            'required' => 'Low stock threshold is required.',
            'integer' => 'Low stock threshold must be a whole number.',
            'greater_than_equal_to' => 'Low stock threshold cannot be negative.',
        ],
        'status' => [
            'required' => 'Category status is required.',
            'in_list' => 'Category status must be active or inactive.',
        ],
    ];
}