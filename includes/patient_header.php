<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($pageTitle ?? 'CyberClinic') ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="topnav">
  <a href="<?= SITE_URL ?>/patient/dashboard.php" class="topnav-brand">
    <div class="brand-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
    CyberClinic
  </a>
  <div class="topnav-links">
    <a href="<?= SITE_URL ?>/patient/dashboard.php"     class="<?= ($activePage??'')==='dashboard'     ?'active':'' ?>">Dashboard</a>
    <a href="<?= SITE_URL ?>/patient/book.php"          class="<?= ($activePage??'')==='book'          ?'active':'' ?>">Book</a>
    <a href="<?= SITE_URL ?>/patient/appointments.php"  class="<?= ($activePage??'')==='appointments'  ?'active':'' ?>">Appointments</a>
    <a href="<?= SITE_URL ?>/patient/records.php"       class="<?= ($activePage??'')==='records'       ?'active':'' ?>">My Records</a>
    <a href="<?= SITE_URL ?>/patient/prescriptions.php" class="<?= ($activePage??'')==='prescriptions' ?'active':'' ?>">Prescriptions</a>
    <?php $unreadCount = isLoggedIn() ? getUnreadCount((int)$_SESSION['user_id']) : 0; ?>
    <a href="<?= SITE_URL ?>/patient/notifications.php" class="<?= ($activePage??'')==='notifications' ?'active':'' ?>">
      Notifications<?php if($unreadCount>0): ?> <span style="background:#ef4444;color:white;font-size:10px;font-weight:700;padding:1px 5px;border-radius:10px;margin-left:3px"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/patient/profile.php"       class="<?= ($activePage??'')==='profile'       ?'active':'' ?>">Profile</a>
  </div>
  <div class="topnav-right">
    <span class="nav-user"><?= sanitize($_SESSION['user_name']??'') ?></span>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-outline btn-sm">Sign out</a>
  </div>
</nav>
