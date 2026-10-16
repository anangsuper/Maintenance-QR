<?php
$query = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: /api/inventaris_mutation.php' . $query);
exit;
