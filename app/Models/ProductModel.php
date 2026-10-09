<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'category_id',
        'name',
        'description',
        'product_code',
        'buying_price',
        'selling_price',
        'quantity',
        'image',
        'status',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'category_id' => 'permit_empty|integer',
        'name' => 'required|max_length[200]',
        'description' => 'permit_empty',
        'product_code' => 'permit_empty|max_length[100]',
        'buying_price' => 'required|decimal',
        'selling_price' => 'required|decimal',
        'quantity' => 'required|integer|greater_than_equal_to[0]',
        'image' => 'permit_empty|max_length[255]',
        'status' => 'required|in_list[active,inactive]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Product name is required.',
            'max_length' => 'Product name cannot exceed 200 characters.',
        ],
        'buying_price' => [
            'required' => 'Buying price is required.',
            'decimal' => 'Buying price must be a valid number.',
        ],
        'selling_price' => [
            'required' => 'Selling price is required.',
            'decimal' => 'Selling price must be a valid number.',
        ],
        'quantity' => [
            'required' => 'Quantity is required.',
            'integer' => 'Quantity must be a whole number.',
            'greater_than_equal_to' => 'Quantity cannot be negative.',
        ],
        'status' => [
            'required' => 'Product status is required.',
            'in_list' => 'Product status must be active or inactive.',
        ],
    ];
}