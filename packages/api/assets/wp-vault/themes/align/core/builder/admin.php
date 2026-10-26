<?php
/**
 * Align Builder - Admin Relative URL Fallback Redirect
 *
 * If a relative admin.php link is resolved against builder's <base href>,
 * redirect cleanly to the real WordPress admin endpoint.
 *
 * @package Align
 */

$query     = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
$admin_url = '/wp-admin/admin.php';
header( 'Location: ' . $admin_url . $query, true, 302 );
exit;
