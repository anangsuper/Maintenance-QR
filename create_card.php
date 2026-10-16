<?php
if (is_file(__DIR__ . '/api/create_card.php')) {
    require __DIR__ . '/api/create_card.php';
} else {
    header('Location: /api/dashboard.php?tab=kartu&add=1#addCardModal');
    exit;
}
