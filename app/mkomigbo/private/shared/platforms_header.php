<?php
declare(strict_types=1);

if (!isset($active_nav) || !is_string($active_nav) || $active_nav === '') {
  $active_nav = 'platforms';
}

if (!isset($page_title) || !is_string($page_title) || $page_title === '') {
  $page_title = 'Platforms — Mkomi Igbo';
}

require_once __DIR__ . '/public_header.php';