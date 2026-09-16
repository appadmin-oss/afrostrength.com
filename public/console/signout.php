<?php
declare(strict_types=1);

/**
 * Signing out is a POST.
 *
 * A GET would mean any image tag on any page could sign an advisor out
 * mid-decision, and a prefetching browser could do it by accident.
 */

require_once __DIR__ . '/../../lib/staff.php';
require_once __DIR__ . '/../../lib/ui.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_ok($_POST['csrf'] ?? null)) {
    staff_signout();
}
header('Location: signin.php');
exit;
