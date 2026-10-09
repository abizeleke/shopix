<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class SalesPeopleController extends BaseController
{
    use ResponseTrait;

    // ============================================================
    // LIST SALES PEOPLE
    // GET /api/sales-people
    // ============================================================

    public function index()
    {
        try {
            $db = Database::connect();

            $builder = $db->table('users');

            $builder->select(
                'id,
                 full_name,
                 username,
                 phone,
                 role,
                 status,
                 created_at,
                 updated_at'
            );

            // Only users with the sales role
            $builder->where('role', 'sales');

            $builder->orderBy('created_at', 'DESC');

            $users = $builder
                ->get()
                ->getResultArray();

            $salesPeople = [];

            foreach ($users as $user) {
                $salesPeople[] = [
                    'id' => (int) $user['id'],

                    'full_name' =>
                        (string) $user['full_name'],

                    'username' =>
                        (string) $user['username'],

                    'phone' =>
                        (string) ($user['phone'] ?? ''),

                    'status' =>
                        (string) ($user['status'] ?? 'active'),

                    'created_at' =>
                        $user['created_at'] ?? null,

                    'updated_at' =>
                        $user['updated_at'] ?? null,
                ];
            }

            return $this->response->setJSON([
                'success' => true,
                'sales_people' => $salesPeople,
                'count' => count($salesPeople),
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' =>
                        'Could not load sales people.',
                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }

    // ============================================================
    // CREATE SALES PERSON
    // POST /api/sales-people
    // ============================================================

    public function create()
    {
        try {

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Invalid request.',
                    ]);
            }

            $fullName = trim(
                (string) ($data['full_name'] ?? '')
            );

            $username = trim(
                (string) ($data['username'] ?? '')
            );

            $phone = trim(
                (string) ($data['phone'] ?? '')
            );

            $status = strtolower(
                trim(
                    (string) (
                        $data['status'] ?? 'active'
                    )
                )
            );

            // ----------------------------------------------------
            // VALIDATION
            // ----------------------------------------------------

            if ($fullName === '') {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Full name is required.',
                    ]);
            }

            if ($username === '') {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Username is required.',
                    ]);
            }

            if (!in_array(
                $status,
                ['active', 'inactive'],
                true
            )) {
                $status = 'active';
            }

            $db = Database::connect();

            // ----------------------------------------------------
            // CHECK USERNAME
            // ----------------------------------------------------

            $existingUsername = $db
                ->table('users')
                ->where('username', $username)
                ->get()
                ->getRowArray();

            if ($existingUsername) {
                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Username already exists.',
                    ]);
            }

            // ----------------------------------------------------
            // CHECK PHONE
            // ----------------------------------------------------

            if ($phone !== '') {

                $existingPhone = $db
                    ->table('users')
                    ->where('phone', $phone)
                    ->get()
                    ->getRowArray();

                if ($existingPhone) {
                    return $this->response
                        ->setStatusCode(409)
                        ->setJSON([
                            'success' => false,
                            'message' =>
                                'Phone number already exists.',
                        ]);
                }
            }

            // ----------------------------------------------------
            // DEFAULT PASSWORD
            // ----------------------------------------------------

            $passwordHash = password_hash(
                '1234',
                PASSWORD_DEFAULT
            );

            // ----------------------------------------------------
            // INSERT
            // ----------------------------------------------------

            $insertData = [
                'full_name' =>
                    $fullName,

                'username' =>
                    $username,

                'password_hash' =>
                    $passwordHash,

                'role' =>
                    'sales',

                'phone' =>
                    $phone !== ''
                        ? $phone
                        : null,

                'status' =>
                    $status,

                'created_at' =>
                    date('Y-m-d H:i:s'),

                'updated_at' =>
                    date('Y-m-d H:i:s'),
            ];

            $db->table('users')->insert(
                $insertData
            );

            $id = $db->insertID();

            if (!$id) {
                throw new \RuntimeException(
                    'Could not create sales person.'
                );
            }

            return $this->response
                ->setStatusCode(201)
                ->setJSON([
                    'success' => true,

                    'message' =>
                        'Sales person created successfully.',

                    'sales_person' => [
                        'id' =>
                            (int) $id,

                        'full_name' =>
                            $fullName,

                        'username' =>
                            $username,

                        'phone' =>
                            $phone,

                        'status' =>
                            $status,
                    ],

                    'default_password' =>
                        '1234',
                ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,

                    'message' =>
                        'Could not create sales person.',

                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }

    // ============================================================
    // UPDATE SALES PERSON
    // PUT /api/sales-people/{id}
    // ============================================================

    public function update($id)
    {
        try {

            $userId = (int) $id;

            if ($userId <= 0) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid sales person ID.',
                    ]);
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid request.',
                    ]);
            }

            $db = Database::connect();

            // ----------------------------------------------------
            // FIND SALES PERSON
            // ----------------------------------------------------

            $person = $db
                ->table('users')
                ->where('id', $userId)
                ->where('role', 'sales')
                ->get()
                ->getRowArray();

            if (!$person) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Sales person not found.',
                    ]);
            }

            // ----------------------------------------------------
            // DATA
            // ----------------------------------------------------

            $fullName = trim(
                (string) (
                    $data['full_name']
                    ?? $person['full_name']
                )
            );

            $username = trim(
                (string) (
                    $data['username']
                    ?? $person['username']
                )
            );

            $phone = trim(
                (string) (
                    $data['phone']
                    ?? ($person['phone'] ?? '')
                )
            );

            $status = strtolower(
                trim(
                    (string) (
                        $data['status']
                        ?? ($person['status'] ?? 'active')
                    )
                )
            );

            // ----------------------------------------------------
            // VALIDATION
            // ----------------------------------------------------

            if ($fullName === '') {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Full name is required.',
                    ]);
            }

            if ($username === '') {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Username is required.',
                    ]);
            }

            if (!in_array(
                $status,
                ['active', 'inactive'],
                true
            )) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid status.',
                    ]);
            }

            // ----------------------------------------------------
            // CHECK USERNAME
            // ----------------------------------------------------

            $duplicate = $db
                ->table('users')
                ->where('username', $username)
                ->where('id !=', $userId)
                ->get()
                ->getRowArray();

            if ($duplicate) {
                return $this->response
                    ->setStatusCode(409)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Username already exists.',
                    ]);
            }

            // ----------------------------------------------------
            // CHECK PHONE
            // ----------------------------------------------------

            if ($phone !== '') {

                $duplicatePhone = $db
                    ->table('users')
                    ->where('phone', $phone)
                    ->where('id !=', $userId)
                    ->get()
                    ->getRowArray();

                if ($duplicatePhone) {
                    return $this->response
                        ->setStatusCode(409)
                        ->setJSON([
                            'success' => false,
                            'message' =>
                                'Phone number already exists.',
                        ]);
                }
            }

            // ----------------------------------------------------
            // UPDATE
            // ----------------------------------------------------

            $db->table('users')
                ->where('id', $userId)
                ->update([
                    'full_name' =>
                        $fullName,

                    'username' =>
                        $username,

                    'phone' =>
                        $phone !== ''
                            ? $phone
                            : null,

                    'status' =>
                        $status,

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]);

            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    'Sales person updated successfully.',
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,

                    'message' =>
                        'Could not update sales person.',

                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }

    // ============================================================
    // RESET PASSWORD
    // POST /api/sales-people/{id}/reset-password
    // ============================================================

    public function resetPassword($id)
    {
        try {

            $userId = (int) $id;

            if ($userId <= 0) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid sales person ID.',
                    ]);
            }

            $db = Database::connect();

            // ----------------------------------------------------
            // FIND SALES PERSON
            // ----------------------------------------------------

            $person = $db
                ->table('users')
                ->where('id', $userId)
                ->where('role', 'sales')
                ->get()
                ->getRowArray();

            if (!$person) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Sales person not found.',
                    ]);
            }

            // ----------------------------------------------------
            // RESET TO 1234
            // ----------------------------------------------------

            $passwordHash = password_hash(
                '1234',
                PASSWORD_DEFAULT
            );

            $db->table('users')
                ->where('id', $userId)
                ->update([
                    'password_hash' =>
                        $passwordHash,

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]);

            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    'Password reset successfully.',

                'default_password' =>
                    '1234',
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,

                    'message' =>
                        'Could not reset password.',

                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }

    // ============================================================
    // ENABLE / DISABLE
    // PUT /api/sales-people/{id}/status
    // ============================================================

    public function status($id)
    {
        try {

            $userId = (int) $id;

            if ($userId <= 0) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid sales person ID.',
                    ]);
            }

            $data = $this->request->getJSON(true);

            if (!is_array($data)) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Invalid request.',
                    ]);
            }

            $status = strtolower(
                trim(
                    (string) (
                        $data['status'] ?? ''
                    )
                )
            );

            if (!in_array(
                $status,
                ['active', 'inactive'],
                true
            )) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Status must be active or inactive.',
                    ]);
            }

            $db = Database::connect();

            // ----------------------------------------------------
            // FIND SALES PERSON
            // ----------------------------------------------------

            $person = $db
                ->table('users')
                ->where('id', $userId)
                ->where('role', 'sales')
                ->get()
                ->getRowArray();

            if (!$person) {
                return $this->response
                    ->setStatusCode(404)
                    ->setJSON([
                        'success' => false,
                        'message' =>
                            'Sales person not found.',
                    ]);
            }

            // ----------------------------------------------------
            // UPDATE STATUS
            // ----------------------------------------------------

            $db->table('users')
                ->where('id', $userId)
                ->update([
                    'status' =>
                        $status,

                    'updated_at' =>
                        date('Y-m-d H:i:s'),
                ]);

            return $this->response->setJSON([
                'success' => true,

                'message' =>
                    $status === 'active'
                        ? 'Sales person enabled.'
                        : 'Sales person disabled.',

                'status' =>
                    $status,
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,

                    'message' =>
                        'Could not update status.',

                    'error' =>
                        $e->getMessage(),
                ]);
        }
    }
}