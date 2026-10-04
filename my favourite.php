<?php
session_start();
include 'config/db_connection.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tenant'){
    header("Location: login.php");
    exit();
}

$tenant_id = $_SESSION['user_id'];

// ---- Handle remove from favorites (on this page) ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_property_id'])){
    $remove_id = $_POST['remove_property_id'];
    mysqli_query($conn, "DELETE FROM favorites WHERE tenant_id='$tenant_id' AND property_id='$remove_id'");
    header("Location: my_favorites.php");
    exit();
}

// ---- Fetch all favorited properties ----
$query = "SELECT p.* FROM favorites f
          JOIN properties p ON f.property_id = p.id
          WHERE f.tenant_id = '$tenant_id'
          ORDER BY f.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Favorites - Hira Rentals</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f8f9fa; }
        .navbar{
            background: #fff;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .navbar h2{ color: #E8622A; margin: 0; }
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
        .container { max-width: 1000px; margin: 35px auto; padding: 0 25px; }
        .section-title{
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 8px;
            border-bottom: 3px solid #E8622A;
            display: inline-block;
        }
        .grid{
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .card{
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eee;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            position: relative;
        }
        .card-body{ padding: 16px; }
        .card h3{ margin: 0 0 8px 0; color: #333; font-size: 16px; }
        .card p{ margin: 4px 0; font-size: 13px; color: #666; }
        .price{ color: #E8622A; font-weight: bold; font-size: 16px; }
        .card-actions{
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .btn{
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            border: none;
            cursor: pointer;
        }
        .btn-view{ background: #E8622A; color: #fff; }
        .btn-remove{ background: #fff; color: #dc3545; border: 1px solid #dc3545; }
        .empty-state{
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state a{ color: #E8622A; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>

    <div class="navbar">
        <h2>❤️ My Favorites</h2>
        <div>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <p class="section-title">Saved Properties</p>

        <?php if(mysqli_num_rows($result) > 0): ?>
            <div class="grid">
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <div class="card">
                        <div class="card-body">
                            <h3><?php echo $row['title']; ?></h3>
                            <p>📍 <?php echo $row['location']; ?></p>
                            <p class="price">PKR <?php echo number_format($row['price']); ?>/mo</p>
                            <div class="card-actions">
                                <a href="property_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-view">View Details</a>
                                <form method="POST" style="flex:1;">
                                    <input type="hidden" name="remove_property_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-remove" style="width:100%;">Remove</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>You have not yet chosen any property.</p>
                <p><a href="properties.php">Browse properties →</a></p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>