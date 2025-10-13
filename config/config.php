<?php
define('DB_HOST', 'mysql.hostinger.com');  
define('DB_NAME', 'u635425032_pwdportal'); 
define('DB_USER', 'u635425032_pwdportal');        
define('DB_PASS', '@PwdPortal1234');            
define('BASE_URL', 'https://job4pwd.site'); 

session_start();

/* =====================
   SMTP / Mail Settings
   (Modify values for your real SMTP provider; keep credentials private.)
   ===================== */
if (!defined('SMTP_HOST')) {
define('SMTP_ENABLE', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', 'mingchancutie@gmail.com');
define('SMTP_PASS', 'zzyvwpscxnxvtaja');
define('SMTP_FROM_EMAIL', 'mingchancutie@gmail.com');
define('SMTP_FROM_NAME', 'PWD Portal'); // set to true to enable sending
}

/* =====================
   Matching Policy Flags (Optional Overrides)
   You can override the defaults in Matching.php by defining these constants.
   Values:
   - MATCH_HARD_LOCK: true to block applications when not eligible.
   - MATCH_SKILL_MIN_PCT: 0.0 to 1.0 minimum fraction of required skills.
   - MATCH_ENFORCE_EDU: true to enforce required education.
   ===================== */
if (!defined('MATCH_HARD_LOCK')) {
   define('MATCH_HARD_LOCK', true);
}
if (!defined('MATCH_SKILL_MIN_PCT')) {
   define('MATCH_SKILL_MIN_PCT', 0.6);
}
if (!defined('MATCH_ENFORCE_EDU')) {
   define('MATCH_ENFORCE_EDU', true);
}

