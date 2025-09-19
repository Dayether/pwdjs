<?php
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Helpers.php';
require_once '../classes/SupportTicket.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') {
    Helpers::redirect('index.php');
    exit;
}

/**
 * Helper: map status to bootstrap badge class (compatible with PHP 7)
 */
function ticket_status_badge($status) {
    switch ($status) {
        case 'Open':     return 'primary';
        case 'Pending':  return 'warning';
        case 'Resolved': return 'success';
        case 'Closed':   return 'secondary';
        default:         return 'secondary';
    }
}

// Handle actions (status changes or delete)
if (isset($_GET['action'], $_GET['ticket_id'])) {
    $action    = $_GET['action'];
    $ticket_id = $_GET['ticket_id'];

    if ($action === 'delete') {
        if (SupportTicket::delete($ticket_id)) {
            Helpers::flash('msg', 'Ticket deleted.');
        } else {
            Helpers::flash('msg', 'Delete failed.');
        }
    } else {
        // Map action to status
        $newStatus = null;
        switch ($action) {
            case 'open':     $newStatus = 'Open'; break;
            case 'pending':  $newStatus = 'Pending'; break;
            case 'closed':   $newStatus = 'Closed'; break;
            case 'resolved': $newStatus = 'Resolved'; break;
        }
        if ($newStatus) {
            if (SupportTicket::updateStatus($ticket_id, $newStatus)) {
                Helpers::flash('msg', 'Ticket status updated to ' . $newStatus . '.');
            } else {
                Helpers::flash('msg', 'Failed to update status.');
            }
        } else {
            Helpers::flash('msg', 'Unknown action.');
        }
    }
    Helpers::redirect('admin_support_tickets.php');
    exit;
}

// View single ticket (detail panel)
$viewTicket = null;
if (!empty($_GET['view'])) {
    $viewTicket = SupportTicket::find($_GET['view']);
    if (!$viewTicket) {
        Helpers::flash('msg', 'Ticket not found.');
        Helpers::redirect('admin_support_tickets.php');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? '';
$search       = $_GET['q'] ?? '';

$tickets = SupportTicket::list([
    'status' => $statusFilter ?: null,
    'search' => $search
]);

include '../includes/header.php';
include '../includes/nav.php';
?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h2 class="h5 fw-semibold mb-0"><i class="bi bi-life-preserver me-2"></i>Admin · Support Tickets</h2>
      <a href="support_contact.php" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-lg me-1"></i>Create Test Ticket
      </a>
    </div>

    <?php if (!empty($_SESSION['flash']['msg'])): ?>
      <div class="alert alert-info py-2 px-3 auto-dismiss mb-3">
        <i class="bi bi-info-circle me-2"></i><?php
          echo htmlspecialchars($_SESSION['flash']['msg']);
          unset($_SESSION['flash']['msg']);
        ?>
      </div>
    <?php endif; ?>

    <form class="row g-2 mb-3">
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <?php foreach (['Open','Pending','Resolved','Closed'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php if ($statusFilter === $st) echo 'selected'; ?>>
              <?php echo $st; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <input name="q" class="form-control" placeholder="Search ticket id, subject, name, email"
               value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
        <a class="btn btn-outline-secondary" href="admin_support_tickets.php">Reset</a>
      </div>
    </form>

    <div class="table-responsive mb-0">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th style="min-width:130px;">Ticket</th>
            <th>Subject</th>
            <th>Name / Email</th>
            <th>Status</th>
            <th>Created</th>
            <th class="text-end" style="min-width:170px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="small">
                <strong><?php echo htmlspecialchars($t['ticket_id']); ?></strong><br>
                <a class="text-decoration-none" href="admin_support_tickets.php?view=<?php echo urlencode($t['ticket_id']); ?>">View</a>
              </td>
              <td><?php echo htmlspecialchars(mb_strimwidth($t['subject'], 0, 50, '…')); ?></td>
              <td class="small">
                <?php echo htmlspecialchars($t['name']); ?><br>
                <span class="text-muted"><?php echo htmlspecialchars($t['email']); ?></span>
              </td>
              <td>
                <?php $badge = ticket_status_badge($t['status']); ?>
                <span class="badge text-bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($t['status']); ?></span>
              </td>
              <td class="small text-muted">
                <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($t['created_at']))); ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <?php if ($t['status'] !== 'Open'): ?>
                    <a class="btn btn-outline-primary" href="?action=open&ticket_id=<?php echo urlencode($t['ticket_id']); ?>">Open</a>
                  <?php endif; ?>
                  <?php if ($t['status'] !== 'Pending'): ?>
                    <a class="btn btn-outline-warning" href="?action=pending&ticket_id=<?php echo urlencode($t['ticket_id']); ?>">Pending</a>
                  <?php endif; ?>
                  <?php if ($t['status'] !== 'Resolved'): ?>
                    <a class="btn btn-outline-success" href="?action=resolved&ticket_id=<?php echo urlencode($t['ticket_id']); ?>">Resolve</a>
                  <?php endif; ?>
                  <?php if ($t['status'] !== 'Closed'): ?>
                    <a class="btn btn-outline-secondary" href="?action=closed&ticket_id=<?php echo urlencode($t['ticket_id']); ?>">Close</a>
                  <?php endif; ?>
                  <a class="btn btn-outline-danger"
                     href="?action=delete&ticket_id=<?php echo urlencode($t['ticket_id']); ?>"
                     onclick="return confirm('Delete this ticket?');">
                     <i class="bi bi-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$tickets): ?>
            <tr>
              <td colspan="6" class="text-center text-muted small">No tickets found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($viewTicket): ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="h6 fw-semibold mb-0">
        <i class="bi bi-envelope-open me-2"></i>Ticket Details
        <span class="text-muted">#<?php echo htmlspecialchars($viewTicket['ticket_id']); ?></span>
      </h3>
      <a href="admin_support_tickets.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-x-lg"></i> Close
      </a>
    </div>

    <dl class="row mb-0 small">
      <dt class="col-sm-3">Subject</dt>
      <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($viewTicket['subject'])); ?></dd>

      <dt class="col-sm-3">Name</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($viewTicket['name']); ?></dd>

      <dt class="col-sm-3">Email</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($viewTicket['email']); ?></dd>

      <dt class="col-sm-3">User Role</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($viewTicket['user_role'] ?? '—'); ?></dd>

      <dt class="col-sm-3">Status</dt>
      <dd class="col-sm-9">
        <span class="badge text-bg-<?php echo ticket_status_badge($viewTicket['status']); ?>">
          <?php echo htmlspecialchars($viewTicket['status']); ?>
        </span>
      </dd>

      <dt class="col-sm-3">Message</dt>
      <dd class="col-sm-9">
        <div class="border rounded p-2 bg-light">
          <?php echo nl2br(htmlspecialchars($viewTicket['message'])); ?>
        </div>
      </dd>

      <dt class="col-sm-3">Created</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($viewTicket['created_at']))); ?></dd>

      <?php if (!empty($viewTicket['updated_at'])): ?>
        <dt class="col-sm-3">Updated</dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($viewTicket['updated_at']))); ?></dd>
      <?php endif; ?>
    </dl>

    <hr>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-outline-primary" href="?action=open&ticket_id=<?php echo urlencode($viewTicket['ticket_id']); ?>">Open</a>
      <a class="btn btn-sm btn-outline-warning" href="?action=pending&ticket_id=<?php echo urlencode($viewTicket['ticket_id']); ?>">Pending</a>
      <a class="btn btn-sm btn-outline-success" href="?action=resolved&ticket_id=<?php echo urlencode($viewTicket['ticket_id']); ?>">Resolve</a>
      <a class="btn btn-sm btn-outline-secondary" href="?action=closed&ticket_id=<?php echo urlencode($viewTicket['ticket_id']); ?>">Close</a>
      <a class="btn btn-sm btn-outline-danger"
         href="?action=delete&ticket_id=<?php echo urlencode($viewTicket['ticket_id']); ?>"
         onclick="return confirm('Delete this ticket?');">
         <i class="bi bi-trash me-1"></i>Delete
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.alert.auto-dismiss').forEach(function(el){
  setTimeout(function(){
    try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e){}
  }, 4000);
});
</script>