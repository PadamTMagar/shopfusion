<?php
// shop/search.php - Redirect dedicated search route to the storefront search experience.

$queryString = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

header('Location: ../index.php' . $queryString, true, 302);
exit();
