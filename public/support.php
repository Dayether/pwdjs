<?php
// Legacy simple support page kept for backward compatibility.
// Redirect permanently to the new, feature-rich support contact form.
require_once '../config/config.php';
header('Location: support_contact', true, 301);
exit;
?>