<?php

session_start();

echo "<p>Request Type</p>";
echo $_SERVER['REQUEST_METHOD'];

$headres = apache_request_headers();
echo "<p>Headers: </p>";
echo "<pre>"; print_r($headres); echo "</pre>";

echo "<p>Body: </p>";
echo "<pre>"; print_r($_REQUEST); echo "</pre>";

echo "<p>Cookie: </p>";
echo "<pre>"; print_r($_COOKIE); echo "</pre>";

echo "<p>Session: </p>";
echo "<pre>"; print_r($_SESSION); echo "</pre>";

echo "<p>FILES: </p>";
echo "<pre>"; print_r($_FILES); echo "</pre>";