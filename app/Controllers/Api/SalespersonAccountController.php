<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class SalespersonAccountController extends BaseController
{
    use ResponseTrait;

    // ============================================================
    // GET MY ACCOUNT
    // ============================================================

    public function index()
    {
        $userId = $this->request->userId ?? null;

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Authenticated user not found.',
                ],
                401
            );
        }

        $userModel = new UserModel();

        $user = $userModel
            ->select(
                'id, full_name, username, role, phone, status, created_at'
            )
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'User account not found.',
                ],
                404
            );
        }

        return $this->respond(
            [
                'success' => true,
                'user' => $user,
            ],
            200
        );
    }

    // ============================================================
    // UPDATE ACCOUNT
    // ============================================================

    public function update()
    {
        $userId = $this->request->userId ?? null;

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Authenticated user not found.',
                ],
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Invalid request data.',
                ],
                400
            );
        }

        $username = trim(
            (string) ($data['username'] ?? '')
        );

        $phone = trim(
            (string) ($data['phone'] ?? '')
        );

        // --------------------------------------------------------
        // VALIDATE USERNAME
        // --------------------------------------------------------

        if ($username === '') {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Username is required.',
                ],
                422
            );
        }

        if (strlen($username) < 3) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Username must be at least 3 characters.',
                ],
                422
            );
        }

        if (strlen($username) > 100) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Username is too long.',
                ],
                422
            );
        }

        // --------------------------------------------------------
        // GET CURRENT USER
        // --------------------------------------------------------

        $userModel = new UserModel();

        $user = $userModel
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'User account not found.',
                ],
                404
            );
        }

        // --------------------------------------------------------
        // CHECK USERNAME DUPLICATE
        // --------------------------------------------------------

        $existingUser = $userModel
            ->where('username', $username)
            ->where('id !=', $userId)
            ->first();

        if ($existingUser) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'This username is already being used.',
                ],
                409
            );
        }

        // --------------------------------------------------------
        // UPDATE
        // --------------------------------------------------------

        $updateData = [
            'username' => $username,
            'phone' => $phone !== '' ? $phone : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!$userModel->update($userId, $updateData)) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Failed to update account information.',
                    'errors' => $userModel->errors(),
                ],
                500
            );
        }

        // --------------------------------------------------------
        // GET UPDATED USER
        // --------------------------------------------------------

        $updatedUser = $userModel
            ->select(
                'id, full_name, username, role, phone, status, created_at'
            )
            ->where('id', $userId)
            ->first();

        return $this->respond(
            [
                'success' => true,
                'message' =>
                    'Account information updated successfully.',
                'user' => $updatedUser,
            ],
            200
        );
    }

    // ============================================================
    // CHANGE PASSWORD
    // ============================================================

    public function changePassword()
    {
        $userId = $this->request->userId ?? null;

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Authenticated user not found.',
                ],
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Invalid request data.',
                ],
                400
            );
        }

        $currentPassword = (string) (
            $data['current_password'] ?? ''
        );

        $newPassword = (string) (
            $data['new_password'] ?? ''
        );

        // --------------------------------------------------------
        // VALIDATE
        // --------------------------------------------------------

        if (
            trim($currentPassword) === '' ||
            trim($newPassword) === ''
        ) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Current password and new password are required.',
                ],
                422
            );
        }

        if (strlen($newPassword) < 4) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'New password must be at least 4 characters.',
                ],
                422
            );
        }

        // --------------------------------------------------------
        // GET USER
        // --------------------------------------------------------

        $userModel = new UserModel();

        $user = $userModel
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'User account not found.',
                ],
                404
            );
        }

        // --------------------------------------------------------
        // VERIFY CURRENT PASSWORD
        // --------------------------------------------------------

        $storedHash = $user['password_hash'] ?? '';

        if (
            $storedHash === '' ||
            !password_verify(
                $currentPassword,
                $storedHash
            )
        ) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Current password is incorrect.',
                ],
                401
            );
        }

        // --------------------------------------------------------
        // PREVENT SAME PASSWORD
        // --------------------------------------------------------

        if (
            password_verify(
                $newPassword,
                $storedHash
            )
        ) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'New password must be different from the current password.',
                ],
                422
            );
        }

        // --------------------------------------------------------
        // HASH NEW PASSWORD
        // --------------------------------------------------------

        $newHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if ($newHash === false) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Failed to secure the new password.',
                ],
                500
            );
        }

        // --------------------------------------------------------
        // UPDATE PASSWORD
        // --------------------------------------------------------

        if (!$userModel->update(
            $userId,
            [
                'password_hash' => $newHash,
                'updated_at' => date(
                    'Y-m-d H:i:s'
                ),
            ]
        )) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'Failed to change password.',
                    'errors' => $userModel->errors(),
                ],
                500
            );
        }

        return $this->respond(
            [
                'success' => true,
                'message' =>
                    'Password changed successfully.',
            ],
            200
        );
    }
}