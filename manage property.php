<?php
session_start();
include 'config/db_connection.php';

if(!isset($_SESSION['user_id']) || (strtolower($_SESSION['role']) != 'manager' && strtolower($_SESSION['role']) != 'admin')){
    echo "<h2 style='color:red; text-align:center;'>Access Denied!</h2>";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);

if(isset($_POST['add_property'])){
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);

    // Latitude/Longitude are optional — if left blank, store NULL instead of an empty string
    $latitude = ($_POST['latitude'] !== '') ? mysqli_real_escape_string($conn, $_POST['latitude']) : null;
    $longitude = ($_POST['longitude'] !== '') ? mysqli_real_escape_string($conn, $_POST['longitude']) : null;
    $lat_sql = $latitude !== null ? "'$latitude'" : "NULL";
    $long_sql = $longitude !== null ? "'$longitude'" : "NULL";

    $insert = "INSERT INTO properties 
               (title, price, location, latitude, longitude, manager_id, status) 
               VALUES ('$title','$price','$location',$lat_sql,$long_sql,'$user_id','available')";
    
    if(mysqli_query($conn, $insert)){
        echo "<script>alert('Property Added Successfully!'); window.location='manage_properties.php';</script>";
    } else {
        echo "<script>alert('Error: ".mysqli_error($conn)."');</script>";
    }
}

if(isset($_GET['action']) && $_GET['action'] == 'toggle_status'){
    $property_id = $_GET['id'];
    $current_status = $_GET['status'];
    $new_status = ($current_status == 'available') ? 'occupied' : 'available';
    
    if($user_role == 'admin') {
        $update = "UPDATE properties SET status='$new_status' WHERE id='$property_id'";
    } else {
        $update = "UPDATE properties SET status='$new_status' WHERE id='$property_id' AND manager_id='$user_id'";
    }
    
    mysqli_query($conn, $update);
    header("Location: manage_properties.php");
    exit();
}

if(isset($_GET['action']) && $_GET['action'] == 'delete'){
    $property_id = $_GET['id'];
    
    if($user_role == 'admin') {
        $delete = "DELETE FROM properties WHERE id='$property_id'";
    } else {
        $delete = "DELETE FROM properties WHERE id='$property_id' AND manager_id='$user_id'";
    }
    
    if(mysqli_query($conn, $delete)){
        echo "<script>alert('Property Deleted!'); window.location='manage_properties.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Properties - Hira Rentals</title>
    <style>
        body{ font-family: Arial; background: #f5f5f5; margin: 30px; }
        .box{ 
            background: #fff; padding: 25px; border-radius: 10px; 
            border: 1px solid #ddd; max-width: 900px; margin: 0 auto; 
        }
        h2{ color: #E8622A; margin-top: 0; }
        input, button{ 
            width: 100%; padding: 10px; margin: 10px 0; 
            border: 1px solid #ccc; border-radius: 6px; 
            box-sizing: border-box; 
        }
        button{ 
            background: #E8622A; color: white; 
            border: none; font-size: 16px; cursor: pointer; 
        }
        table{ 
            width: 100%; border-collapse: collapse; 
            margin-top: 20px; background: white; 
        }
        th, td{ border: 1px solid #ddd; padding: 12px; text-align: left; }
        th{ background-color: #E8622A; color: white; }
        .badge{ 
            padding: 5px 10px; border-radius: 4px; 
            font-weight: bold; font-size: 12px; 
        }
        .available{ background: #28a745; color: white; }
        .occupied{ background: #dc3545; color: white; }
        .btn-action{ 
            padding: 5px 10px; text-decoration: none; 
            color: white; border-radius: 4px; 
            font-size: 12px; margin-right: 5px; 
        }
        .btn-status{ background: #007bff; }
        .btn-del{ background: #6c757d; }
        .coord-row{ display: flex; gap: 10px; }
        .coord-row input{ margin: 10px 0; }
        .hint{
            font-size: 12px; color: #888; margin: -6px 0 10px;
        }
        .hint a{ color: #E8622A; }
    </style>
</head>
<body>

    <div class="box">
        <h2>🛠️ Manage Properties</h2>
        <a href="dashboard.php" 
           style="color: #E8622A; text-decoration: none; font-size: 14px;">
           ⬅️ Back to Dashboard
        </a>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0;">

        <h3>➕ Add New Property</h3>
        <form method="POST" style="max-width: 500px; margin-bottom: 30px;">
            <input type="text" name="title" 
                   placeholder="Property Title" required>
            <input type="number" name="price" 
                   placeholder="Rent Price" required>
            <input type="text" name="location" 
                   placeholder="Location (e.g. Civil Lines Gujrat)" required>

            <div class="coord-row">
                <input type="text" name="latitude" 
                       placeholder="Latitude (e.g. 32.5742)" pattern="^-?\d{1,3}(\.\d+)?$">
                <input type="text" name="longitude" 
                       placeholder="Longitude (e.g. 74.0856)" pattern="^-?\d{1,3}(\.\d+)?$">
            </div>
            <p class="hint">
                Optional — leave blank if unknown. Get exact coordinates from
                <a href="https://maps.google.com" target="_blank" rel="noopener">Google Maps</a>:
                right-click the property location → "What's here?" → copy the two numbers shown.
            </p>

            <button type="submit" name="add_property">
                Add Property
            </button>
        </form>

        <h3>📋 Properties List</h3>
        <table>
            <tr>
                <th>Title</th>
                <th>Price</th>
                <th>Location</th>
                <th>Coordinates</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php
            if($user_role == 'admin') {
                $q = "SELECT * FROM properties";
            } else {
                $q = "SELECT * FROM properties WHERE manager_id = '$user_id'";
            }
            
            $res = mysqli_query($conn, $q);
            
            while($row = mysqli_fetch_assoc($res)){
                echo "<tr>";
                echo "<td>".$row['title']."</td>";
                echo "<td>Rs. ".$row['price']."</td>";
                echo "<td>".$row['location']."</td>";
                echo "<td>";
                if(!empty($row['latitude']) && !empty($row['longitude'])){
                    echo "<span style='color:#28a745; font-size:12px;'>✓ ".$row['latitude'].", ".$row['longitude']."</span>";
                } else {
                    echo "<span style='color:#bbb; font-size:12px;'>Not set</span>";
                }
                echo "</td>";
                echo "<td><span class='badge ".$row['status']."'>".ucfirst($row['status'])."</span></td>";
                echo "<td>";
                echo "<a href='manage_properties.php?action=toggle_status&id=".$row['id']."&status=".$row['status']."' 
                           class='btn-action btn-status'>
                           Change Status
                       </a>";
                echo "<a href='manage_properties.php?action=delete&id=".$row['id']."' 
                           class='btn-action btn-del' 
                           onclick='return confirm(\"Delete this property?\")'>
                           Delete
                       </a>";
                echo "</td>";
                echo "</tr>";
            }
            if(mysqli_num_rows($res) == 0){ 
                echo "<tr><td colspan='6' style='text-align:center;'>No properties found.</td></tr>"; 
            }
            ?>
        </table>
    </div>

</body>
</html>