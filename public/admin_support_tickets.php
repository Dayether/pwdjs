<?php
require_once '../config/config.php';
require_once '../classes/Helpers.php';
require_once '../classes/SupportTicket.php';
require_once '../classes/Mail.php';

Helpers::requireLogin();
if (($_SESSION['role'] ?? '') !== 'admin') { Helpers::redirect('index.php'); exit; }

function ticket_status_badge($status){
  switch($status){
    case 'Open': return 'primary';
    case 'Pending': return 'warning';
    case 'Resolved': return 'success';
    case 'Closed': return 'secondary';
    default: return 'secondary';
  }
}

// Reply submission
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reply_ticket_id'], $_POST['reply_message'])){
  $tid=trim($_POST['reply_ticket_id']);
  $msg=trim($_POST['reply_message']);
  $rec=trim($_POST['reply_recipient']??'');
  $new=trim($_POST['reply_new_status']??'');
  $ticket=SupportTicket::find($tid);
  if(!$ticket){ Helpers::flash('msg','Ticket not found.'); Helpers::redirect('admin_support_tickets.php'); exit; }
  $emailTo = $rec!=='' ? $rec : $ticket['email'];
  $smtpDisabled=!Mail::isEnabled();
  $err=null;
  if($msg!==''){
    $subject='Support Ticket Reply: #'.$ticket['ticket_id'];
    $body=nl2br(htmlspecialchars($msg));
    if(!$smtpDisabled){ $res=Mail::send($emailTo,$ticket['name'],$subject,$body); if(!$res['success']) $err=$res['error']??'Send failed'; }
  }
  if($new && $new!==$ticket['status']) SupportTicket::updateStatus($tid,$new);
  if($err===null && !$smtpDisabled) Helpers::flash('msg','Reply sent successfully.');
  elseif($smtpDisabled) Helpers::flash('msg','Reply saved (email sending disabled).');
  else Helpers::flash('msg','Reply logged but email failed: '.($err?:'Unknown error'));
  Helpers::redirect('admin_support_tickets.php?view='.urlencode($tid));
  exit;
}

// Status / delete actions
if(isset($_GET['action'],$_GET['ticket_id'])){
  $a=strtolower($_GET['action']); $tid=$_GET['ticket_id'];
  if($a==='delete'){
    Helpers::flash('msg', SupportTicket::delete($tid)?'Ticket deleted.':'Delete failed.');
  } else {
    $map=['open'=>'Open','pending'=>'Pending','resolved'=>'Resolved','closed'=>'Closed'];
    if(isset($map[$a])){ SupportTicket::updateStatus($tid,$map[$a]); Helpers::flash('msg','Status updated to '.$map[$a].'.'); }
  }
  Helpers::redirect('admin_support_tickets.php');
  exit;
}

$statusFilter=$_GET['status']??''; $search=$_GET['q']??''; $viewId=$_GET['view']??'';
$tickets=SupportTicket::list(['status'=>$statusFilter?:null,'search'=>$search]);
$counts=['total'=>0,'Open'=>0,'Pending'=>0,'Resolved'=>0,'Closed'=>0];
foreach($tickets as $t){ $counts['total']++; $s=$t['status']; if(isset($counts[$s])) $counts[$s]++; }
$resolvedPct=$counts['total']?round(($counts['Resolved']/$counts['total'])*100,1):0;
$viewTicket=$viewId?SupportTicket::find($viewId):null;

include '../includes/header.php';
?>
<div class="admin-layout">
  <?php include '../includes/admin_sidebar.php'; ?>
  <div class="admin-main">
    <style>
      .tickets-topbar{display:flex;flex-direction:column;gap:.9rem;margin-bottom:1.1rem}
      .t-chips{display:flex;flex-wrap:wrap;gap:.55rem}
      .t-chip{--bd:rgba(255,255,255,.08);display:inline-flex;align-items:center;gap:.45rem;font-size:.62rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;padding:.5rem .7rem;border:1px solid var(--bd);background:#162335;color:#c3d2e6;border-radius:8px;cursor:pointer;transition:.25s}
      .t-chip:hover{background:#1f3856;color:#fff}
      .t-chip.active{background:linear-gradient(135deg,#1f4d89,#163657);color:#fff;border-color:#3e74c4;box-shadow:0 4px 12px -6px rgba(0,0,0,.6)}
      .t-chip .count{background:rgba(255,255,255,.1);padding:.2rem .5rem;border-radius:5px;font-size:.55rem;font-weight:600}
      .t-filters{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
      .t-filters .search-box{flex:1 1 300px;position:relative}
      .t-filters .search-box input{background:#101b2b;border:1px solid #233246;color:#dbe6f5;padding:.6rem .75rem .6rem 2rem;font-size:.78rem;border-radius:9px;width:100%}
      .t-filters .search-box i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);color:#6c7c91;font-size:.9rem}
      .t-filters select{background:#101b2b;border:1px solid #233246;color:#dbe6f5;font-size:.72rem;padding:.55rem .65rem;border-radius:8px}
      .t-filters button.reset-btn{background:#182739;border:1px solid #23384f;color:#9fb4cc;font-size:.7rem;padding:.55rem .75rem;border-radius:8px;font-weight:600;letter-spacing:.5px}
      .t-filters button.reset-btn:hover{background:#213249;color:#fff}
      .tickets-wrapper{position:relative;border:1px solid rgba(255,255,255,.06);background:#0f1827;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -12px rgba(0,0,0,.65);}
      table.tickets-table{margin:0;border-collapse:separate;border-spacing:0;width:100%}
      table.tickets-table thead th{background:#142134;color:#ced8e6;font-size:.63rem;font-weight:600;letter-spacing:.09em;text-transform:uppercase;padding:.75rem .85rem;border-bottom:1px solid #1f2e45;position:sticky;top:0;z-index:2;cursor:pointer}
      table.tickets-table tbody td{padding:.85rem .9rem;vertical-align:top;font-size:.72rem;color:#d2dbe7;border-bottom:1px solid #132031}
      table.tickets-table tbody tr:last-child td{border-bottom:none}
      table.tickets-table tbody tr{transition:.2s}
      table.tickets-table tbody tr:hover{background:rgba(255,255,255,.04)}
      .status-pill{display:inline-flex;align-items:center;gap:.35rem;font-size:.55rem;font-weight:600;letter-spacing:.05em;padding:.38rem .55rem;border-radius:20px;text-transform:uppercase}
      .st-Open{background:linear-gradient(135deg,#1d4ed8,#1e3a8a);color:#dbe9ff;border:1px solid #1e40af}
      .st-Pending{background:linear-gradient(135deg,#8a660c,#3f2f07);color:#fff1c7;border:1px solid #5f460b}
      .st-Resolved{background:linear-gradient(135deg,#1f7a46,#0f3d24);color:#d8ffe9;border:1px solid #1c5b36}
      .st-Closed{background:linear-gradient(135deg,#515b67,#2a3037);color:#e3e9ef;border:1px solid #3b444d}
      .ticket-actions a.action-btn{background:#132840;border:1px solid #1f3a57;color:#c4d8ef;font-size:.58rem;font-weight:600;padding:.4rem .55rem;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem;transition:.25s}
      .ticket-actions a.action-btn:hover{background:#1c3a57;color:#fff;border-color:#2f5a87}
      .empty-state{padding:2.3rem 1rem;text-align:center;color:#7f8fa1;font-size:.8rem}
      .fade-in{animation:fadeIn .5s ease both}
      @keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
      @media (max-width:880px){
        .tickets-wrapper{border:none;background:transparent;box-shadow:none}
        table.tickets-table,table.tickets-table thead,table.tickets-table tbody,table.tickets-table th,table.tickets-table td,table.tickets-table tr{display:block}
        table.tickets-table thead{display:none}
        table.tickets-table tbody tr{background:#132133;border:1px solid #1f3147;border-radius:12px;margin-bottom:.85rem;padding:.75rem .9rem}
        table.tickets-table tbody td{border:none;padding:.35rem 0;font-size:.7rem}
        table.tickets-table tbody td.actions{margin-top:.55rem}
      }
      .tickets-wrapper{margin-left:-.5rem;margin-right:-.5rem}
      @media (min-width:1200px){.tickets-wrapper{margin-left:-1rem;margin-right:-1rem}}
      .resolution-bar{height:6px;border-radius:4px;background:#1e293b;overflow:hidden;margin-top:.35rem;}
      .resolution-bar span{display:block;height:100%;background:linear-gradient(90deg,#16a34a,#4ade80);}    
    </style>

    <div class="tickets-topbar fade-in">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h6 fw-semibold mb-0 d-flex align-items-center" style="letter-spacing:.5px"><i class="bi bi-life-preserver me-2"></i>Support Tickets</h2>
        <a href="support_contact.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Create Test Ticket</a>
      </div>
      <div class="t-chips" id="ticketChips">
        <div class="t-chip active" data-filter="">All <span class="count"><?= $counts['total']; ?></span></div>
        <div class="t-chip" data-filter="Open">Open <span class="count"><?= $counts['Open']; ?></span></div>
        <div class="t-chip" data-filter="Pending">Pending <span class="count"><?= $counts['Pending']; ?></span></div>
        <div class="t-chip" data-filter="Resolved">Resolved <span class="count"><?= $counts['Resolved']; ?></span></div>
        <div class="t-chip" data-filter="Closed">Closed <span class="count"><?= $counts['Closed']; ?></span></div>
      </div>
      <div class="t-filters">
        <div class="search-box"><i class="bi bi-search"></i><input type="text" id="ticketSearch" placeholder="Search ticket id, subject, name, email..."></div>
        <select id="ticketStatusSelect">
          <option value="">All Status</option>
          <option value="Open">Open</option>
          <option value="Pending">Pending</option>
          <option value="Resolved">Resolved</option>
          <option value="Closed">Closed</option>
        </select>
        <button type="button" class="reset-btn" id="ticketReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
        <div class="ms-auto small text-secondary d-flex flex-column" style="min-width:140px">
          <span class="fw-semibold" style="font-size:.62rem;letter-spacing:.1em;text-transform:uppercase">Resolution Rate</span>
          <div class="resolution-bar"><span style="width:<?= $resolvedPct; ?>%"></span></div>
          <span style="font-size:.65rem;color:#9fb3c9"><?= $resolvedPct; ?>% Resolved</span>
        </div>
      </div>
    </div>

    <?php if (!empty($_SESSION['flash']['msg'])): ?>
      <div class="alert alert-info py-2 px-3 auto-dismiss mb-3 fade-in" style="border-left:4px solid #0ea5e9">
        <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']['msg']); ?>
      </div>
    <?php endif; ?>

    <div class="tickets-wrapper fade-in" id="ticketsWrapper">
      <table class="tickets-table" id="ticketsTable">
        <thead>
          <tr>
            <th data-sort="ticket">Ticket</th>
            <th data-sort="subject">Subject</th>
            <th data-sort="name">Name / Email</th>
            <th data-sort="status">Status</th>
            <th data-sort="created">Created</th>
            <th style="text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t): $st=$t['status']; ?>
            <tr data-status="<?= htmlspecialchars($st); ?>">
              <td style="font-size:.68rem;font-weight:600;color:#fff">
                <?= htmlspecialchars($t['ticket_id']); ?><br>
                <a class="text-decoration-none" style="font-size:.58rem" href="admin_support_tickets.php?view=<?= urlencode($t['ticket_id']); ?>">View</a>
              </td>
              <td style="font-size:.7rem;color:#d7e2ef"><?= htmlspecialchars(mb_strimwidth($t['subject'],0,70,'…')); ?></td>
              <td style="font-size:.65rem;color:#9fb3c9">
                <?= htmlspecialchars($t['name']); ?><br>
                <span style="color:#6e8196"><?= htmlspecialchars($t['email']); ?></span>
              </td>
              <td><span class="status-pill st-<?= htmlspecialchars($st); ?>"><i class="bi bi-circle-fill" style="font-size:.45rem"></i><?= htmlspecialchars($st); ?></span></td>
              <td style="font-size:.62rem;color:#c2cfdd;white-space:nowrap"><?= htmlspecialchars(date('M d, Y H:i', strtotime($t['created_at']))); ?></td>
              <td class="actions" style="text-align:right">
                <div class="ticket-actions" style="display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end">
                  <?php if($st!=='Open'): ?><a class="action-btn" href="?action=open&ticket_id=<?= urlencode($t['ticket_id']); ?>">Open</a><?php endif; ?>
                  <?php if($st!=='Pending'): ?><a class="action-btn" href="?action=pending&ticket_id=<?= urlencode($t['ticket_id']); ?>">Pending</a><?php endif; ?>
                  <?php if($st!=='Resolved'): ?><a class="action-btn" href="?action=resolved&ticket_id=<?= urlencode($t['ticket_id']); ?>">Resolve</a><?php endif; ?>
                  <?php if($st!=='Closed'): ?><a class="action-btn" href="?action=closed&ticket_id=<?= urlencode($t['ticket_id']); ?>">Close</a><?php endif; ?>
                  <a class="action-btn" data-confirm-title="Delete Ticket" data-confirm="Delete this support ticket?" data-confirm-yes="Delete" data-confirm-no="Cancel" href="?action=delete&ticket_id=<?= urlencode($t['ticket_id']); ?>"><i class="bi bi-trash"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$tickets): ?><tr><td colspan="6" class="empty-state">No tickets found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($viewTicket): ?>
      <div class="card border-0 shadow-sm my-4 fade-in">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 fw-semibold mb-0"><i class="bi bi-envelope-open me-2"></i>Ticket Details <span class="text-muted">#<?= htmlspecialchars($viewTicket['ticket_id']); ?></span></h3>
            <a href="admin_support_tickets.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> Close</a>
          </div>
          <dl class="row mb-0 small">
            <dt class="col-sm-3">Subject</dt><dd class="col-sm-9"><?= nl2br(htmlspecialchars($viewTicket['subject'])); ?></dd>
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= htmlspecialchars($viewTicket['name']); ?></dd>
            <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= htmlspecialchars($viewTicket['email']); ?></dd>
            <dt class="col-sm-3">User Role</dt><dd class="col-sm-9"><?= htmlspecialchars($viewTicket['user_role'] ?? '—'); ?></dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge text-bg-<?= ticket_status_badge($viewTicket['status']); ?>"><?= htmlspecialchars($viewTicket['status']); ?></span></dd>
            <dt class="col-sm-3">Message</dt><dd class="col-sm-9"><div class="border rounded p-2 bg-light"><?= nl2br(htmlspecialchars($viewTicket['message'])); ?></div></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= htmlspecialchars(date('M d, Y H:i', strtotime($viewTicket['created_at']))); ?></dd>
            <?php if (!empty($viewTicket['updated_at'])): ?><dt class="col-sm-3">Updated</dt><dd class="col-sm-9"><?= htmlspecialchars(date('M d, Y H:i', strtotime($viewTicket['updated_at']))); ?></dd><?php endif; ?>
          </dl>
          <hr>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-sm btn-outline-primary" href="?action=open&ticket_id=<?= urlencode($viewTicket['ticket_id']); ?>">Open</a>
            <a class="btn btn-sm btn-outline-warning" href="?action=pending&ticket_id=<?= urlencode($viewTicket['ticket_id']); ?>">Pending</a>
            <a class="btn btn-sm btn-outline-success" href="?action=resolved&ticket_id=<?= urlencode($viewTicket['ticket_id']); ?>">Resolve</a>
            <a class="btn btn-sm btn-outline-secondary" href="?action=closed&ticket_id=<?= urlencode($viewTicket['ticket_id']); ?>">Close</a>
            <a class="btn btn-sm btn-outline-danger" data-confirm-title="Delete Ticket" data-confirm="Delete this support ticket?" data-confirm-yes="Delete" data-confirm-no="Cancel" href="?action=delete&ticket_id=<?= urlencode($viewTicket['ticket_id']); ?>"><i class="bi bi-trash me-1"></i>Delete</a>
          </div>
          <h4 class="h6 fw-semibold mb-3"><i class="bi bi-reply me-2"></i>Send Reply</h4>
          <?php if (!Mail::isEnabled()): ?><div class="alert alert-warning py-2 px-3 small"><i class="bi bi-exclamation-triangle me-1"></i>Email sending is OFF (configure SMTP in config.php).</div><?php endif; ?>
          <form method="post" class="row g-3">
            <input type="hidden" name="reply_ticket_id" value="<?= htmlspecialchars($viewTicket['ticket_id']); ?>">
            <div class="col-md-6"><label class="form-label">Recipient Email (override)</label><input name="reply_recipient" type="email" class="form-control" placeholder="Leave blank to use ticket email (<?= htmlspecialchars($viewTicket['email']); ?>)" value="<?= htmlspecialchars($_POST['reply_recipient'] ?? ''); ?>"><div class="form-text">If left empty, original email is used.</div></div>
            <div class="col-md-6 d-flex align-items-end"><div class="small text-muted">Original: <?= htmlspecialchars($viewTicket['email']); ?></div></div>
            <div class="col-12"><label class="form-label">Reply Message</label><textarea name="reply_message" class="form-control" rows="6" required placeholder="Type your response..."><?= htmlspecialchars($_POST['reply_message'] ?? ''); ?></textarea><div class="form-text">User receives this via email (HTML formatted).</div></div>
            <div class="col-md-4"><label class="form-label">Update Status</label><select name="reply_new_status" class="form-select"><option value="">(Leave unchanged)</option><?php foreach(['Open','Pending','Resolved','Closed'] as $st): ?><option value="<?= $st; ?>" <?php if(($viewTicket['status']??'')===$st) echo 'disabled'; ?>><?= $st; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-8 d-flex align-items-end justify-content-end"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Reply</button></div>
          </form>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
// Client-side filtering & sorting
const tSearch=document.getElementById('ticketSearch');
const tSelect=document.getElementById('ticketStatusSelect');
const tChips=document.getElementById('ticketChips');
const tReset=document.getElementById('ticketReset');
const tRows=[...document.querySelectorAll('#ticketsTable tbody tr')];
function applyTicketFilters(){
  const q=(tSearch?.value||'').toLowerCase().trim();
  const st=tSelect?.value||'';
  tRows.forEach(r=>{
    const rs=r.getAttribute('data-status')||'';
    const text=r.innerText.toLowerCase();
    const okSt=!st||rs===st;const okQ=!q||text.includes(q);
    r.style.display=(okSt&&okQ)?'':'none';
  });
}
tSearch?.addEventListener('input',applyTicketFilters);
tSelect?.addEventListener('change',()=>{applyTicketFilters();syncChip();});
tChips?.addEventListener('click',e=>{const c=e.target.closest('.t-chip');if(!c)return;[...tChips.querySelectorAll('.t-chip')].forEach(x=>x.classList.remove('active'));c.classList.add('active');tSelect.value=c.getAttribute('data-filter')||'';applyTicketFilters();});
tReset?.addEventListener('click',()=>{tSearch.value='';tSelect.value='';[...tChips.querySelectorAll('.t-chip')].forEach(x=>x.classList.remove('active'));tChips.querySelector('[data-filter=""]')?.classList.add('active');applyTicketFilters();});
function syncChip(){const v=tSelect.value||'';[...tChips.querySelectorAll('.t-chip')].forEach(ch=>{ch.classList.toggle('active',(ch.getAttribute('data-filter')||'')===v);});}
// Sorting columns
document.querySelectorAll('#ticketsTable thead th[data-sort]').forEach(th=>{th.addEventListener('click',()=>{const key=th.getAttribute('data-sort');const tbody=th.closest('table').querySelector('tbody');const dir=th.getAttribute('data-dir')==='asc'?'desc':'asc';th.setAttribute('data-dir',dir);const f=dir==='asc'?1:-1;const arr=[...tbody.querySelectorAll('tr')];arr.sort((a,b)=>{const get=row=>{switch(key){case 'ticket':return row.children[0].innerText.toLowerCase();case 'subject':return row.children[1].innerText.toLowerCase();case 'name':return row.children[2].innerText.toLowerCase();case 'status':return row.children[3].innerText.toLowerCase();case 'created':return row.children[4].innerText.toLowerCase();default:return row.innerText.toLowerCase();}};return get(a).localeCompare(get(b))*f;});arr.forEach(tr=>tbody.appendChild(tr));});});
applyTicketFilters();
</script>
