<?php
session_start();
include 'config/db_connection.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = intval($_SESSION['user_id']);

// Release session lock early so other pages/tabs aren't blocked waiting
session_write_close();

// Only managers and owners may add, edit or delete properties
$can_manage = ($role == 'manager' || $role == 'owner');

if (!file_exists('uploads')) {
    mkdir('uploads', 0755, true);
}

// Saves an uploaded image safely (only real jpg/png/webp images, random file name).
// Returns the new file name, or '' if nothing valid was uploaded.
function save_property_image($file){
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, $allowed)) return '';
    if(@getimagesize($file['tmp_name']) === false) return '';
    $name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if(move_uploaded_file($file['tmp_name'], 'uploads/' . $name)) return $name;
    return '';
}

if($can_manage && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])){
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = intval($_POST['price']);
    $status = ($_POST['status'] == 'occupied') ? 'occupied' : 'available';

    $image_name = "";
    if(isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0){
        $image_name = save_property_image($_FILES['property_image']);
    }

    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO properties 
                  (title, location, price, status, manager_id, image) 
                  VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssisis", $title, $location, $price, $status, $user_id, $image_name);
        mysqli_stmt_execute($stmt);
        echo "<script>alert('Property Added Successfully!'); window.location.href='properties.php';</script>";
    } catch (mysqli_sql_exception $e) {
        echo "<script>alert('Could not add property. Please check the details.');</script>";
    }
}

if($can_manage && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_property'])){
    $id = intval($_POST['property_id']);
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = intval($_POST['price']);
    $status = ($_POST['status'] == 'occupied') ? 'occupied' : 'available';

    $image_name = "";
    if(isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0){
        $image_name = save_property_image($_FILES['property_image']);
    }

    try {
        if($image_name != ''){
            $stmt = mysqli_prepare($conn, "UPDATE properties 
                      SET title=?, location=?, price=?, status=?, image=? 
                      WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssissi", $title, $location, $price, $status, $image_name, $id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE properties 
                      SET title=?, location=?, price=?, status=? 
                      WHERE id=?");
            mysqli_stmt_bind_param($stmt, "ssisi", $title, $location, $price, $status, $id);
        }
        mysqli_stmt_execute($stmt);
        echo "<script>alert('Property Updated Successfully!'); window.location.href='properties.php';</script>";
    } catch (mysqli_sql_exception $e) {
        echo "<script>alert('Could not update property. Please check the details.');</script>";
    }
}

if($can_manage && isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    try {
        $stmt = mysqli_prepare($conn, "DELETE FROM properties WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        echo "<script>alert('Property Deleted!'); window.location.href='properties.php';</script>";
    } catch (mysqli_sql_exception $e) {
        echo "<script>alert('This property cannot be deleted because it has bookings, payments or other records linked to it.'); window.location.href='properties.php';</script>";
    }
}

// ---- Search / filters ----
$search_raw = isset($_GET['search']) ? trim($_GET['search']) : '';
$search = htmlspecialchars($search_raw, ENT_QUOTES);   // safe copy for showing in the form

$filter_status = (isset($_GET['status']) && in_array($_GET['status'], ['available', 'occupied'])) ? $_GET['status'] : '';
$filter_price = (isset($_GET['price']) && is_numeric($_GET['price'])) ? (string)intval($_GET['price']) : '';

$sql = "SELECT * FROM properties WHERE 1=1";
$types = '';
$params = [];

if($search_raw != ''){
    $sql .= " AND (title LIKE ? OR location LIKE ?)";
    $like = '%' . $search_raw . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
if($filter_status != ''){
    $sql .= " AND status = ?";
    $types .= 's';
    $params[] = $filter_status;
}
if($filter_price != ''){
    $sql .= " AND price <= ?";
    $types .= 'i';
    $params[] = intval($filter_price);
}

$stmt = mysqli_prepare($conn, $sql);
if($types != ''){
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Properties - Hira Rentals</title>
    <!-- <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"> -->
    <style>
        body{
            font-family: 'Poppins', Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
        }
        .navbar{
            background: #fff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eef2f5;
        }
        .navbar h2{ color: #E8622A; margin: 0; }
        .container{
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .add-form{
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            margin-bottom: 25px;
            border: 1px solid #eef2f5;
        }
        .add-form input,
        .add-form select{
            padding: 10px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 8px;
            width: 180px;
            font-family: inherit;
        }
        .add-form button{
            padding: 10px 25px;
            background: #E8622A;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .search-bar{
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .search-bar input,
        .search-bar select{
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        .search-bar button{
            padding: 12px 25px;
            background: #E8622A;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .grid{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .card{
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eef2f5;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }
        .card:hover{
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
        /* IMAGE — full picture visible, click opens lightbox (not a link anymore) */
        .img-link{
            display: block;
            width: 100%;
            overflow: hidden;
            cursor: zoom-in;
            background: #eaeaea;
        }
        .property-img{
            width: 100%;
            height: 210px;
            object-fit: contain;   /* shows the WHOLE image, no cropping */
            object-position: center;
            background-color: #eaeaea;
            display: block;
            transition: transform 0.3s ease;
        }
        .img-link:hover .property-img{
            transform: scale(1.03);
        }
        .card-content{ padding: 20px; flex-grow: 1; }
        .card h3{ margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #2d3748; }
        .card p{ margin: 5px 0; color: #718096; font-size: 13px; }
        .price{ color: #E8622A; font-weight: 700; font-size: 19px; margin: 10px 0 !important; }
        .status-badge{ display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .status-available{ background: #d4edda; color: #155724; }
        .status-occupied{ background: #f8d7da; color: #721c24; }
        .btn-details{ 
            display: inline-block; padding: 9px 20px; 
            background: #E8622A; color: #fff; 
            text-decoration: none; border-radius: 8px; 
            font-size: 13px; text-align: center;
            transition: background 0.2s;
        }
        .btn-details:hover{ background: #c94d1a; }
        .action-buttons{ margin-top: 15px; border-top: 1px solid #f7fafc; padding-top: 12px; }
        .btn-delete{ background: #dc3545; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; }
        .btn-edit{ background: #ffc107; color: #fff; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; margin-right: 5px; }
        .btn-back{ background: #34495e; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: bold; display: inline-block; margin-right: 10px; }
        .logout{ background: #E8622A; color: #fff; padding: 8px 15px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .no-data{ text-align: center; padding: 50px; color: #999; grid-column: span 3; }
        .edit-form{ margin-top: 15px; display: none; background: #f7fafc; padding: 15px; border-radius: 8px; }
        .edit-form input, 
        .edit-form select{ 
            width: 100%; padding: 8px; margin: 5px 0; 
            border: 1px solid #ddd; border-radius: 6px; 
            box-sizing: border-box; 
        }
        .edit-form button{ 
            width: 100%; padding: 10px; background: #E8622A; 
            color: #fff; border: none; border-radius: 6px; 
            cursor: pointer; margin-top: 8px; 
        }

        /* LIGHTBOX — full-size image popup */
        .lightbox-overlay{
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }
        .lightbox-overlay.active{ display: flex; }
        .lightbox-overlay img{
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .lightbox-close{
            position: absolute;
            top: 22px; right: 30px;
            color: #fff;
            font-size: 32px;
            font-weight: 300;
            cursor: pointer;
            line-height: 1;
            width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            transition: background 0.2s;
        }
        .lightbox-close:hover{ background: rgba(255,255,255,0.2); }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Hira Rentals</h2>
        <div>
            <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">

        <?php if($role == 'manager' || $role == 'owner'): ?>
        <div class="add-form">
            <h3>Add New Property</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="title" 
                       placeholder="Property Title" required>
                <input type="text" name="location" 
                       placeholder="Location" required>
                <input type="number" name="price" 
                       placeholder="Rent (PKR)" required>
                <select name="status">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                </select>
                <input type="file" name="property_image" 
                       accept="image/*" style="width:230px;">
                <button type="submit" name="add_property">
                    + Add Property
                </button>
            </form>
        </div>
        <?php endif; ?>

        <form method="GET">
            <div class="search-bar">
                <input type="text" name="search" 
                       placeholder="Search by name or location..."
                       value="<?php echo $search; ?>">
                <select name="status">
                    <option value="">Any Status</option>
                    <option value="available" 
                        <?php if($filter_status=='available') echo 'selected'; ?>>
                        Available
                    </option>
                    <option value="occupied" 
                        <?php if($filter_status=='occupied') echo 'selected'; ?>>
                        Occupied
                    </option>
                </select>
                <input type="number" name="price" 
                       placeholder="Max Rent"
                       value="<?php echo $filter_price; ?>">
                <button type="submit">Search</button>
            </div>
        </form>

        <div class="grid">
            <?php if($result && mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <div class="card">
                    <?php 
                    $image_filename = (!empty($row['image'])) ? $row['image'] : '';
                    if(!empty($image_filename) && file_exists("uploads/" . $image_filename)){
                        $image_path = "uploads/" . $image_filename;
                    } else {
                        $image_path = "https://placehold.co/600x400/eaeaea/718096?text=Hira+Rentals";
                    }
                    ?>

                    <!-- IMAGE — click opens the lightbox, no longer a navigation link -->
                    <div class="img-link" onclick="openLightbox('<?php echo htmlspecialchars($image_path, ENT_QUOTES); ?>')">
                        <img src="<?php echo $image_path; ?>" 
                             class="property-img" alt="Property Image">
                    </div>

                    <div class="card-content">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <p class="price">
                            PKR <?php echo number_format($row['price']); ?>/mo
                        </p>
                        <div class="status-badge status-<?php echo $row['status']; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </div>
                        <br><br>
                        <a href="property_detail.php?id=<?php echo $row['id']; ?>" 
                           class="btn-details">
                            Details
                        </a>

                        <?php if($role == 'manager' || $role == 'owner'): ?>
                        <div class="action-buttons">
                            <button class="btn-edit" 
                                onclick="toggleEdit('edit-<?php echo $row['id']; ?>')">
                                Edit
                            </button>
                            <a href="?delete=<?php echo $row['id']; ?>" 
                               onclick="return confirm('Delete this property?')" 
                               class="btn-delete" 
                               style="text-decoration:none">
                                Delete
                            </a>
                        </div>

                        <div class="edit-form" 
                             id="edit-<?php echo $row['id']; ?>">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="property_id" 
                                       value="<?php echo $row['id']; ?>">
                                <input type="text" name="title" 
                                       value="<?php echo htmlspecialchars($row['title']); ?>" required>
                                <input type="text" name="location" 
                                       value="<?php echo htmlspecialchars($row['location']); ?>" required>
                                <input type="number" name="price" 
                                       value="<?php echo $row['price']; ?>" required>
                                <select name="status">
                                    <option value="available" 
                                        <?php if($row['status']=='available') echo 'selected'; ?>>
                                        Available
                                    </option>
                                    <option value="occupied"
                                        <?php if($row['status']=='occupied') echo 'selected'; ?>>
                                        Occupied
                                    </option>
                                </select>
                                <label style="font-size:12px; color:#666;">
                                    Update Image (Optional):
                                </label>
                                <input type="file" name="property_image" 
                                       accept="image/*">
                                <button type="submit" name="update_property">
                                    Update Property
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    No properties found!
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LIGHTBOX (full-size image popup) -->
    <div class="lightbox-overlay" id="lightboxOverlay" onclick="closeLightbox(event)">
        <div class="lightbox-close" onclick="closeLightbox(event)">&times;</div>
        <img id="lightboxImg" src="" alt="Full size property image">
    </div>

    <script>
    function toggleEdit(id){
        var form = document.getElementById(id);
        if(form.style.display == 'none' || 
           form.style.display == ''){
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }

    function openLightbox(src){
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightboxOverlay').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox(e){
        if(e.target.id === 'lightboxOverlay' || e.target.classList.contains('lightbox-close')){
            document.getElementById('lightboxOverlay').classList.remove('active');
            document.getElementById('lightboxImg').src = '';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            document.getElementById('lightboxOverlay').classList.remove('active');
            document.getElementById('lightboxImg').src = '';
            document.body.style.overflow = '';
        }
    });
    </script>

</body>
</html>
