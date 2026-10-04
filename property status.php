<?php
session_start();
include 'config/db_connection.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if($role == 'tenant'){
    $query = "SELECT p.*, r.status as booking_status 
              FROM properties p
              LEFT JOIN rental_requests r 
              ON p.id = r.property_id 
              AND r.tenant_id = '$user_id'
              ORDER BY p.created_at DESC";
} else {
    $query = "SELECT p.*, r.status as booking_status,
              u.first_name, u.last_name
              FROM properties p
              LEFT JOIN rental_requests r 
              ON p.id = r.property_id
              LEFT JOIN users u 
              ON r.tenant_id = u.id
              ORDER BY p.created_at DESC";
}

$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Property Status - Hira Rentals</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body{
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }
        .navbar{
            background: #fff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .navbar h2{ color: #E8622A; margin: 0; }
        .container{
            max-width: 1200px;
            margin: 35px auto;
            padding: 0 25px;
        }
        .section-title{
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 3px solid #E8622A;
            display: inline-block;
        }
        table{
            width: 100%;
            background: #fff;
            border-radius: 12px;
            border-collapse: collapse;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        th{
            background: #E8622A;
            color: #fff;
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            text-transform: uppercase;
        }
        td{
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        tr:hover td{ background: #fff5f0; }
        .status-available{
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-occupied{
            background: #f8d7da;
            color: #721c24;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .booking-approved{
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .booking-pending{
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .booking-rejected{
            background: #f8d7da;
            color: #721c24;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }
        .btn-back{
            background: #2c3e50;
            color: white;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            margin-right: 10px;
        }
        .logout{
            background: #E8622A;
            color: #fff;
            padding: 8px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
        }
        .no-data{
            text-align: center;
            padding: 50px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><img src="assets/logo.png" alt="Hira Rentals" style="height:40px; vertical-align:middle; margin-right:8px;">Hira Rentals</h2>
        <div>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <p class="section-title">📊 Property Status</p>

        <table>
            <tr>
                <th>#</th>
                <th>Property</th>
                <th>Location</th>
                <th>Price</th>
                <th>Property Status</th>
                <th>Booking Status</th>
                <?php if($role == 'owner'): ?>
                <th>Tenant</th>
                <?php endif; ?>
            </tr>

            <?php if($result && mysqli_num_rows($result) > 0): ?>
                <?php $i=1; while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo $row['title']; ?></td>
                    <td><?php echo $row['location']; ?></td>
                    <td>PKR <?php echo number_format($row['price']); ?></td>
                    <td>
                        <span class="status-<?php echo $row['status']; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['booking_status']): ?>
                        <span class="booking-<?php echo $row['booking_status']; ?>">
                            <?php echo ucfirst($row['booking_status']); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#999">No Booking</span>
                        <?php endif; ?>
                    </td>
                    <?php if($role == 'owner'): ?>
                    <td>
                        <?php if(isset($row['first_name'])): ?>
                        <?php echo $row['first_name'].' '.$row['last_name']; ?>
                        <?php else: ?>
                        <span style="color:#999">N/A</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="no-data">
                        No properties found!
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>