<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use Config\Database;

class AccountController extends BaseController
{
    use ResponseTrait;

    // ============================================================
    // GET CURRENT ACCOUNT
    // ============================================================

    public function index()
    {
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Could not determine logged-in user.',
                ],
                401
            );
        }

        $db = Database::connect();

        $user = $db
            ->table('users')
            ->select(
                'id, full_name, username, role, phone, status, created_at, updated_at'
            )
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        if (!$user) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'User account not found.',
                ],
                404
            );
        }

        return $this->respond([
            'success' => true,
            'user' => $user,
        ]);
    }

    // ============================================================
    // UPDATE ACCOUNT
    // ============================================================

    public function update()
    {
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Could not determine logged-in user.',
                ],
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            $data = [];
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

        // --------------------------------------------------------
        // VALIDATION
        // --------------------------------------------------------

        if ($fullName === '') {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Full name is required.',
                ],
                400
            );
        }

        if ($username === '') {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Username is required.',
                ],
                400
            );
        }

        $db = Database::connect();

        // --------------------------------------------------------
        // CHECK DUPLICATE USERNAME
        // --------------------------------------------------------

        $existingUser = $db
            ->table('users')
            ->select('id')
            ->where('username', $username)
            ->where('id !=', $userId)
            ->get()
            ->getRowArray();

        if ($existingUser) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Username is already in use.',
                ],
                409
            );
        }

        // --------------------------------------------------------
        // UPDATE USER
        // --------------------------------------------------------

        $updateData = [
            'full_name' => $fullName,
            'username' => $username,
            'phone' => $phone !== '' ? $phone : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $updated = $db
            ->table('users')
            ->where('id', $userId)
            ->update($updateData);

        if (!$updated) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Failed to update account.',
                ],
                500
            );
        }

        // --------------------------------------------------------
        // GET UPDATED USER
        // --------------------------------------------------------

        $user = $db
            ->table('users')
            ->select(
                'id, full_name, username, role, phone, status, created_at, updated_at'
            )
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        return $this->respond([
            'success' => true,
            'message' => 'Account information updated.',
            'user' => $user,
        ]);
    }

    // ============================================================
    // CHANGE PASSWORD
    // ============================================================

    public function changePassword()
    {
        $userId = $this->getAuthenticatedUserId();

        if (!$userId) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Could not determine logged-in user.',
                ],
                401
            );
        }

        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            $data = [];
        }

        $currentPassword =
            (string) ($data['current_password'] ?? '');

        $newPassword =
            (string) ($data['new_password'] ?? '');

        // --------------------------------------------------------
        // VALIDATION
        // --------------------------------------------------------

        if ($currentPassword === '') {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Current password is required.',
                ],
                400
            );
        }

        if ($newPassword === '') {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'New password is required.',
                ],
                400
            );
        }

        if (strlen($newPassword) < 4) {
            return $this->respond(
                [
                    'success' => false,
                    'message' =>
                        'New password must be at least 4 characters.',
                ],
                400
            );
        }

        // --------------------------------------------------------
        // GET USER
        // --------------------------------------------------------

        $db = Database::connect();

        $user = $db
            ->table('users')
            ->select('id, password_hash')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

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

        $passwordHash =
            (string) ($user['password_hash'] ?? '');

        if (
            $passwordHash === '' ||
            !password_verify(
                $currentPassword,
                $passwordHash
            )
        ) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Current password is incorrect.',
                ],
                400
            );
        }

        // --------------------------------------------------------
        // HASH NEW PASSWORD
        // --------------------------------------------------------

        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        if ($newPasswordHash === false) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Failed to create password hash.',
                ],
                500
            );
        }

        // --------------------------------------------------------
        // SAVE NEW PASSWORD
        // --------------------------------------------------------

        $updated = $db
            ->table('users')
            ->where('id', $userId)
            ->update([
                'password_hash' => $newPasswordHash,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (!$updated) {
            return $this->respond(
                [
                    'success' => false,
                    'message' => 'Failed to change password.',
                ],
                500
            );
        }

        return $this->respond([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    // ============================================================
    // AUTHENTICATED USER ID
    // ============================================================

    private function getAuthenticatedUserId(): ?int
    {
        /*
         * AuthFilter.php sets:
         *
         * $request->userId = $authToken['user_id'];
         *
         * Therefore we read exactly that property here.
         */

        $userId = $this->request->userId ?? null;

        if ($userId === null) {
            return null;
        }

        if (!is_numeric($userId)) {
            return null;
        }

        return (int) $userId;
    }
}