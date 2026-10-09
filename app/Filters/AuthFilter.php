<?php

namespace App\Filters;

use App\Models\AuthTokenModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication token is required.',
                ]);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid authorization header.',
                ]);
        }

        $token = trim($matches[1]);

        if ($token === '') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Authentication token is required.',
                ]);
        }

        /*
         * The login controller stores only the SHA-256
         * hash of the authentication token.
         */
        $tokenHash = hash('sha256', $token);

        $authTokenModel = new AuthTokenModel();

        $authToken = $authTokenModel
            ->where('token_hash', $tokenHash)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();

        if (!$authToken) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired authentication token.',
                ]);
        }

        /*
         * Make the authenticated user ID available
         * to controllers during this request.
         */
        $request->userId = $authToken['user_id'];
    }

    public function after(
        RequestInterface $request,
        ResponseInterface $response,
        $arguments = null
    ) {
        // Nothing required after the request.
    }
}