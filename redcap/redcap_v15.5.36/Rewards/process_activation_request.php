<?php

use Vanderbilt\REDCap\Classes\Rewards\Utility\ActivationApprovalManager;

 include __DIR__.'/partials/header.php'; ?>
<?php
if (!(SUPER_USER || (!$rewards_enabled_by_super_users_only && $user_rights['design']))) {
  redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
  // exit("ERROR: You must be a super user to perform this action!");
}

$project_id = $_GET['pid'] ?? null;
$enable = $_GET['enable'] ?? null;
$request_id = $_GET['request_id'] ?? null;

$manager = new ActivationApprovalManager($project_id);

$approved = $manager->processRequest($request_id, $userid, $enable);
?>

<div style="width: auto; max-width: 800px;">
    <h1>Request Approved</h1>
</div>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
