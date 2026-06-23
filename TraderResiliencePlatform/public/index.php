<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API requests
if (str_starts_with($uri, '/api/')) {
    $_SERVER['REQUEST_URI'] = substr($uri, 4); // strip /api prefix
    require __DIR__ . '/../api/index.php';
    exit;
}

// Serve SPA for all other routes
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX Trader — Institutional Performance Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="/css/app.css">
</head>
<body>

<!-- SIDEBAR -->
<nav id="sidebar">
  <div class="sidebar-logo">
    <span class="logo-icon">▲</span>
    <span class="logo-text">APEX</span>
  </div>
  <div class="sidebar-nav">
    <a href="#" class="nav-item active" data-view="dashboard">
      <span class="nav-icon">⬡</span><span>Command Center</span>
    </a>
    <a href="#" class="nav-item" data-view="journal">
      <span class="nav-icon">◈</span><span>Trade Journal</span>
    </a>
    <a href="#" class="nav-item" data-view="resilience">
      <span class="nav-icon">◎</span><span>Resilience Lab</span>
    </a>
    <a href="#" class="nav-item" data-view="allocator">
      <span class="nav-icon">◐</span><span>Capital Allocator</span>
    </a>
    <a href="#" class="nav-item" data-view="risk">
      <span class="nav-icon">◆</span><span>Risk Control</span>
    </a>
    <a href="#" class="nav-item" data-view="analytics">
      <span class="nav-icon">◉</span><span>Analytics</span>
    </a>
  </div>
  <div class="sidebar-footer">
    <a href="#" class="nav-item" data-view="settings">
      <span class="nav-icon">⚙</span><span>Settings</span>
    </a>
  </div>
</nav>

<!-- MAIN CONTENT -->
<main id="main-content">
  <!-- TOPBAR -->
  <div id="topbar">
    <div class="topbar-left">
      <span id="view-title">Command Center</span>
      <span id="market-session" class="session-badge"></span>
    </div>
    <div class="topbar-right">
      <div id="clock" class="mono"></div>
      <div id="halt-badge" class="halt-badge hidden">⛔ HALT</div>
      <button class="icon-btn" id="alert-btn" onclick="loadAlerts()">
        🔔 <span id="alert-count" class="badge hidden">0</span>
      </button>
      <button class="icon-btn" onclick="showView('settings')">⚙</button>
    </div>
  </div>

  <!-- VIEWS -->
  <div id="view-dashboard" class="view active"></div>
  <div id="view-journal" class="view"></div>
  <div id="view-resilience" class="view"></div>
  <div id="view-allocator" class="view"></div>
  <div id="view-risk" class="view"></div>
  <div id="view-analytics" class="view"></div>
  <div id="view-settings" class="view"></div>
</main>

<!-- MODAL -->
<div id="modal-overlay" class="hidden" onclick="closeModal()">
  <div id="modal" onclick="event.stopPropagation()">
    <div id="modal-header">
      <h3 id="modal-title"></h3>
      <button onclick="closeModal()" class="close-btn">✕</button>
    </div>
    <div id="modal-body"></div>
  </div>
</div>

<!-- TOAST -->
<div id="toast-container"></div>

<link rel="stylesheet" href="/css/app.css">
<script src="/js/app.js"></script>
</body>
</html>
