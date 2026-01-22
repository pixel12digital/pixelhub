<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller do dashboard
 */
class DashboardController extends Controller
{
    /**
     * Página inicial do dashboard
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Conta total de tenants
        $stmt = $db->query("SELECT COUNT(*) as total FROM tenants");
        $tenantsCount = $stmt->fetch()['total'] ?? 0;

        // Conta total de invoices
        $stmt = $db->query("SELECT COUNT(*) as total FROM invoices");
        $invoicesCount = $stmt->fetch()['total'] ?? 0;

        // Conta invoices pendentes
        $stmt = $db->query("SELECT COUNT(*) as total FROM invoices WHERE status = 'pending'");
        $pendingInvoices = $stmt->fetch()['total'] ?? 0;

        $this->view('dashboard.index', [
            'user' => Auth::user(),
            'tenantsCount' => $tenantsCount,
            'invoicesCount' => $invoicesCount,
            'pendingInvoices' => $pendingInvoices,
        ]);
    }
}

