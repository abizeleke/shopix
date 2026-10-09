<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class DatabaseTest extends ResourceController
{
    public function index()
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Activation controller is working!'
        ]);
    }
}