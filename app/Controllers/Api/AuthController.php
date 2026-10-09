<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AuthTokenModel;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        $data = $this->request->getJSON(true);

        if (!is_array($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid request.'
                ]);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $rememberMe = (bool) ($data['remember_me'] ?? false);

        if ($username === '' || $password === '') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Username and password are required.'
                ]);
        }

        try {
            $userModel = new UserModel();

            $user = $userModel
                ->where('username', $username)
                ->where('status', 'active')
                ->first();

            if (!$user) {
                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Invalid username or password.'
                    ]);
            }

            if (!password_verify(
                $password,
                $user['password_hash']
            )) {
                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Invalid username or password.'
                    ]);
            }

            /*
             * Generate a secure random authentication token.
             */
            $token = bin2hex(random_bytes(32));

            /*
             * Store only the SHA-256 hash in the database.
             */
            $tokenHash = hash('sha256', $token);

            /*
             * Remember Me:
             * 30 days if selected.
             * Otherwise 1 day.
             */
            $expirationSeconds = $rememberMe
                ? 60 * 60 * 24 * 30
                : 60 * 60 * 24;

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + $expirationSeconds
            );

            $authTokenModel = new AuthTokenModel();

            $authTokenModel->insert([
                'user_id' => $user['id'],
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Login successful.',

                /*
                 * This is the ONLY time the actual token
                 * is sent to the Flutter application.
                 */
                'auth_token' => $token,

                'expires_at' => $expiresAt,

                'user' => [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                ],
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Login failed.',
                    'error' => $e->getMessage()
                ]);
        }
    }
}