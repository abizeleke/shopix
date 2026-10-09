<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppActivationModel;

class ActivationController extends BaseController
{
    public function test()
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Activation controller is working!'
        ]);
    }

    public function activate()
    {
        // Get JSON request body
        $data = $this->request->getJSON(true);

        // Make sure JSON was received
        if (!is_array($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid request.'
                ]);
        }

        // Get activation code
        $activationCode = trim($data['activation_code'] ?? '');

        // Make sure activation code was provided
        if ($activationCode === '') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'message' => 'Activation code is required.'
                ]);
        }

        try {
            $model = new AppActivationModel();

            // Find active activation code
            $activation = $model
                ->where('activation_code', $activationCode)
                ->where('status', 'active')
                ->first();

            // Code does not exist or is inactive
            if (!$activation) {
                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'success' => false,
                        'message' => 'Invalid activation code.'
                    ]);
            }

            // Activation successful
            return $this->response->setJSON([
                'success' => true,
                'message' => 'App activated successfully!'
            ]);

        } catch (\Throwable $e) {

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'success' => false,
                    'message' => 'Activation failed.',
                    'error' => $e->getMessage()
                ]);
        }
    }
}