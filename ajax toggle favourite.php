<?php
session_start();
include 'config/db_connection.php';

header('Content-Type: application/json');

// Must be logged in tenant
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant'){
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

$tenant_id = $_SESSION['user_id'];
$property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : 0;

if($property_id <= 0){
    echo json_encode(['success' => false, 'message' => 'Invalid property']);
    exit();
}

$check_fav = mysqli_query($conn, "SELECT id FROM favorites WHERE tenant_id='$tenant_id' AND property_id='$property_id'");

if(mysqli_num_rows($check_fav) > 0){
    mysqli_query($conn, "DELETE FROM favorites WHERE tenant_id='$tenant_id' AND property_id='$property_id'");
    echo json_encode(['success' => true, 'favorited' => false]);
} else {
    mysqli_query($conn, "INSERT INTO favorites (tenant_id, property_id) VALUES ('$tenant_id', '$property_id')");
    echo json_encode(['success' => true, 'favorited' => true]);
}