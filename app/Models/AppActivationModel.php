<?php

namespace App\Models;

use CodeIgniter\Model;

class AppActivationModel extends Model
{
    protected $table = 'app_activation';
    protected $primaryKey = 'id';

    protected $returnType = 'array';

    protected $allowedFields = [
        'activation_code',
        'status',
    ];
}