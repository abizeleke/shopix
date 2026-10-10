<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger
    ) {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // ============================================================
        // FORCE MYSQL SESSION TIMEZONE
        // ------------------------------------------------------------
        // This makes every query run as if the DB were in Ethiopian
        // time (UTC+3), so "today" filters match what the app expects.
        // ============================================================
        try {
            $db = \Config\Database::connect();
            $db->query("SET time_zone = '+03:00'");
        } catch (\Throwable $e) {
            // Silently ignore — some routes don't need the DB.
            log_message('debug', 'Could not set MySQL timezone: ' . $e->getMessage());
        }
    }
}
