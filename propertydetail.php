<?php
session_start();
include 'config/db_connection.php';

if(!isset($_GET['id'])){
    header("Location: properties.php");
    exit();
}

$id = $_GET['id'];
$query = "SELECT * FROM properties WHERE id='$id'";
$result = mysqli_query($conn, $query);
$property = mysqli_fetch_assoc($result);

if(!$property){
    header("Location: properties.php");
    exit();
}

$is_logged_in = isset($_SESSION['user_id']);
$is_tenant = $is_logged_in && $_SESSION['role'] == 'tenant';

$message = "";

// ---- Handle Add/Remove Favorite ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_favorite'])){
    if($is_tenant){
        $tenant_id = $_SESSION['user_id'];
        $property_id = $id;

        $check_fav = mysqli_query($conn, "SELECT id FROM favorites WHERE tenant_id='$tenant_id' AND property_id='$property_id'");

        if(mysqli_num_rows($check_fav) > 0){
            mysqli_query($conn, "DELETE FROM favorites WHERE tenant_id='$tenant_id' AND property_id='$property_id'");
        } else {
            mysqli_query($conn, "INSERT INTO favorites (tenant_id, property_id) VALUES ('$tenant_id', '$property_id')");
        }

        header("Location: property_detail.php?id=$property_id");
        exit();
    }
}

// ---- Check if this property is already favorited by the logged-in tenant ----
$is_favorited = false;
if($is_tenant){
    $fav_check = mysqli_query($conn, "SELECT id FROM favorites WHERE tenant_id='" . $_SESSION['user_id'] . "' AND property_id='$id'");
    $is_favorited = mysqli_num_rows($fav_check) > 0;
}

// ---- Check if this tenant has scheduled a visit for this property ----
// (used to enforce "schedule a visit before booking")
$has_scheduled_visit = false;
if($is_tenant){
    $visit_check = mysqli_query($conn,
        "SELECT id FROM site_visits WHERE tenant_id='" . $_SESSION['user_id'] . "' AND property_id='$id'");
    $has_scheduled_visit = $visit_check && mysqli_num_rows($visit_check) > 0;
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_now'])){
    if($is_tenant){
        $tenant_id = $_SESSION['user_id'];
        $property_id = $id;

        // ---- Enforce: tenant must have scheduled a visit for this property first ----
        $visit_recheck = mysqli_query($conn,
            "SELECT id FROM site_visits WHERE tenant_id='$tenant_id' AND property_id='$property_id'");

        if(mysqli_num_rows($visit_recheck) == 0){
            $message = "<script>alert('Please schedule a visit for this property before booking it.');</script>";
        } else {
            $check = mysqli_query($conn, 
                "SELECT * FROM rental_requests 
                 WHERE property_id='$property_id' 
                 AND tenant_id='$tenant_id' 
                 AND status='pending'");

            if(mysqli_num_rows($check) > 0){
                $message = "<script>alert('You already have a pending request for this property!');</script>";
            } else {
                $insert = "INSERT INTO rental_requests 
                           (property_id, tenant_id, status) 
                           VALUES 
                           ('$property_id','$tenant_id','pending')";
                
                if(mysqli_query($conn, $insert)){
                    $message = "<script>alert('Booking Request Sent Successfully!'); window.location='booking.php';</script>";
                }
            }
        }
    }
}

// ---- Handle new review submission ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])){
    if($is_tenant){
        $tenant_id = $_SESSION['user_id'];
        $property_id = $id;
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);

        if($rating >= 1 && $rating <= 5){
            $stmt = mysqli_prepare($conn, 
                "INSERT INTO reviews (property_id, tenant_id, rating, comment) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiis", $property_id, $tenant_id, $rating, $comment);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $message = "<script>alert('Review submitted, thank you!'); window.location='property_detail.php?id=$property_id';</script>";
        } else {
            $message = "<script>alert('Please select a star rating!');</script>";
        }
    }
}

// ---- Fetch all reviews for this property ----
$reviews_query = mysqli_query($conn, "
    SELECT r.rating, r.comment, r.created_at, u.first_name, u.last_name
    FROM reviews r
    JOIN users u ON r.tenant_id = u.id
    WHERE r.property_id = '$id'
    ORDER BY r.created_at DESC
");

$review_count = mysqli_num_rows($reviews_query);
$avg_rating = 0;
if($review_count > 0){
    $sum = 0;
    $temp_reviews = [];
    while($rrow = mysqli_fetch_assoc($reviews_query)){
        $sum += $rrow['rating'];
        $temp_reviews[] = $rrow;
    }
    $avg_rating = round($sum / $review_count, 1);
    $reviews = $temp_reviews;
} else {
    $reviews = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $property['title']; ?> - Hira Rentals</title>
    <style>
        body{
            font-family: Arial;
            background: #f5f5f5;
            margin: 0;
        }
        .navbar{
            background: #fff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
        }
        .navbar h2{ color: #E8622A; margin: 0; }
        .logout{
            background: #E8622A;
            color: #fff;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
        }
        .login-btn{
            background: #1B2340;
            color: #fff;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
        }
        .container{
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        .property-card{
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        .property-card h1{
            color: #333;
            margin-top: 0;
            display: inline-block;
        }
        .fav-btn{
            background: none;
            border: 1px solid #ddd;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            font-size: 18px;
            cursor: pointer;
            float: right;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .fav-btn:hover{ border-color: #E8622A; transform: scale(1.08); }
        .fav-btn.active{ background: #ffe9e2; border-color: #E8622A; }
        .avg-rating-badge{
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }
        .price{
            color: #E8622A;
            font-size: 28px;
            font-weight: bold;
        }
        .status-available{
            background: #d4edda;
            color: #155724;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .status-occupied{
            background: #f8d7da;
            color: #721c24;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .info{
            margin: 20px 0;
            line-height: 2;
        }
        .btn{
            display: inline-block;
            padding: 12px 25px;
            background: #E8622A;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 10px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:disabled{
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-outline{
            background: #fff;
            color: #E8622A;
            border: 1px solid #E8622A;
        }
        .btn-visit{ background: #34495e; }
        .back{
            color: #E8622A;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .steps-guide{
            background: #fff5f0;
            border: 1px solid #E8622A;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .steps-guide h4{
            margin: 0 0 8px 0;
            color: #E8622A;
            font-size: 14px;
        }
        .steps-guide ol{
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #555;
            line-height: 1.8;
        }
        .visit-required-note{
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 10px 16px;
            margin: 10px 0;
            font-size: 13px;
            color: #856404;
        }
        .guest-note{
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
            font-size: 13.5px;
            color: #856404;
        }
        .map-section{
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .map-section h3{
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 16px;
        }
        .map-section iframe{
            border: 0;
            border-radius: 10px;
            width: 100%;
            height: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .reviews-section{
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .reviews-section h3{
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .review-form{
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 20px;
        }
        .star-rating{
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 30px;
            margin-bottom: 12px;
        }
        .star-rating input{ display: none; }
        .star-rating label{
            color: #ddd;
            cursor: pointer;
            padding: 0 2px;
            transition: color 0.15s;
        }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label{
            color: #E8622A;
        }
        .review-form textarea{
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: Arial;
            font-size: 14px;
            margin-bottom: 12px;
            resize: vertical;
            min-height: 70px;
        }
        .review-card{
            border-bottom: 1px solid #eee;
            padding: 14px 0;
        }
        .review-card:last-child{ border-bottom: none; }
        .review-top{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .review-name{ font-weight: 600; color: #333; font-size: 14px; }
        .review-stars{ color: #E8622A; font-size: 14px; }
        .review-date{ color: #999; font-size: 12px; }
        .review-comment{ color: #555; font-size: 13.5px; line-height: 1.6; }
        .no-reviews{ color: #999; font-size: 14px; }
    </style>
</head>
<body>
    <?php echo $message; ?>
    <div class="navbar">
        <h2>Hira Rentals</h2>
        <div>
            <?php if($is_logged_in): ?>
                <a href="dashboard.php">Dashboard</a>
                &nbsp;&nbsp;
                <a href="logout.php" class="logout">Logout</a>
            <?php else: ?>
                <a href="index.php" style="margin-right:15px; color:#333; text-decoration:none;">Home</a>
                <a href="login.php" class="login-btn">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <a href="<?php echo $is_logged_in ? 'properties.php' : 'index.php'; ?>" class="back">
            ← Back to Properties
        </a>

        <div class="property-card">
            <?php if($is_tenant): ?>
                <button type="button"
                        id="favBtn"
                        data-property-id="<?php echo $id; ?>"
                        data-favorited="<?php echo $is_favorited ? '1' : '0'; ?>"
                        class="fav-btn <?php echo $is_favorited ? 'active' : ''; ?>"
                        style="float:right;"
                        title="<?php echo $is_favorited ? 'Remove from favorites' : 'Add to favorites'; ?>"
                        onclick="toggleFavorite(this)">
                    <?php echo $is_favorited ? '❤️' : '🤍'; ?>
                </button>
            <?php endif; ?>
            <h1><?php echo $property['title']; ?></h1>
            <?php if($review_count > 0): ?>
                <span class="avg-rating-badge">⭐ <?php echo $avg_rating; ?> (<?php echo $review_count; ?> review<?php echo $review_count > 1 ? 's' : ''; ?>)</span>
            <?php endif; ?>

            <br><br>
            <span class="status-<?php echo $property['status']; ?>">
                <?php echo ucfirst($property['status']); ?>
            </span>

            <div class="info">
                <p>📍 <b>Location:</b> <?php echo $property['location']; ?></p>
                <p>💰 <b>Rent:</b>
                    <span class="price">
                        PKR <?php echo number_format($property['price']); ?>/mo
                    </span>
                </p>
                <p>📅 <b>Listed On:</b>
                    <?php echo date('d M Y',
                        strtotime($property['created_at'])); ?>
                </p>
                <p>🏠 <b>Marla:</b> <?php echo $property['marla']; ?></p>
                <p>🛏️ <b>Bedrooms:</b> <?php echo $property['rooms']; ?></p>
                <p>🚿 <b>Bathrooms:</b> <?php echo $property['bathrooms']; ?></p>
                <p>📝 <b>Description:</b> <?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
            </div>

            <?php if($property['status'] == 'available'): ?>

                <?php if($is_tenant): ?>
                    <div class="steps-guide">
                        <h4>📋 How to Rent This Property:</h4>
                        <ol>
                            <li>Schedule a Visit to see the property in person</li>
                            <li>Once visited, send a Rental Request</li>
                            <li>Manager will create a Lease Contract for you</li>
                            <li>Sign the contract & make your payment</li>
                        </ol>
                    </div>

                    <a href="schedule_visit.php?property_id=<?php echo $id; ?>" class="btn btn-visit">
                        📅 Schedule Visit
                    </a>

                    <?php if($has_scheduled_visit): ?>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="book_now" class="btn">
                                📝 Book Now
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn" disabled title="Schedule a visit first">
                            📝 Book Now
                        </button>
                        <div class="visit-required-note">
                            ⚠️ Please schedule a visit for this property before booking it.
                        </div>
                    <?php endif; ?>

                <?php elseif(!$is_logged_in): ?>
                    <div class="guest-note">
                        👋 Aap guest ke tor par dekh rahe hain. Booking ya visit schedule karne ke liye pehle login/register karna hoga.
                    </div>

                    <a href="login.php" class="btn btn-visit">
                        📅 Schedule Visit (Login Required)
                    </a>

                    <a href="login.php" class="btn">
                        📝 Book Now (Login Required)
                    </a>
                <?php endif; ?>

            <?php endif; ?>

            <a href="<?php echo $is_logged_in ? 'properties.php' : 'index.php'; ?>" class="btn btn-outline">
                Back
            </a>

            <!-- Google Maps Location -->
            <?php if(!empty($property['location'])): ?>
            <div class="map-section">
                <h3>📍 View on Map</h3>
                <iframe
                    loading="lazy"
                    allowfullscreen
                    src="https://maps.google.com/maps?q=<?php echo urlencode($property['location'] . ', Pakistan'); ?>&output=embed">
                </iframe>
            </div>
            <?php endif; ?>

            <!-- ==================== REVIEWS SECTION ==================== -->
            <div class="reviews-section">
                <h3>⭐ Reviews & Ratings</h3>

                <?php if($is_tenant): ?>
                <div class="review-form">
                    <form method="POST">
                        <div class="star-rating">
                            <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                        </div>
                        <textarea name="comment" placeholder="Apna experience likhein (optional)..."></textarea>
                        <button type="submit" name="submit_review" class="btn">Submit Review</button>
                    </form>
                </div>
                <?php elseif(!$is_logged_in): ?>
                    <div class="guest-note">
                        Review dene ke liye pehle <a href="login.php">login</a> karein.
                    </div>
                <?php endif; ?>

                <?php if($review_count > 0): ?>
                    <?php foreach($reviews as $rev): ?>
                        <div class="review-card">
                            <div class="review-top">
                                <div>
                                    <span class="review-name"><?php echo htmlspecialchars($rev['first_name'] . ' ' . $rev['last_name']); ?></span>
                                    &nbsp;
                                    <span class="review-stars"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?></span>
                                </div>
                                <span class="review-date"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <?php if(!empty($rev['comment'])): ?>
                                <div class="review-comment"><?php echo htmlspecialchars($rev['comment']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-reviews">Abhi tak koi review nahi. Sabse pehle review dene wale banein!</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
    function toggleFavorite(btn) {
        const propertyId = btn.getAttribute('data-property-id');
        btn.disabled = true;

        fetch('ajax_toggle_favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'property_id=' + encodeURIComponent(propertyId)
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            if (data.success) {
                if (data.favorited) {
                    btn.innerHTML = '❤️';
                    btn.classList.add('active');
                    btn.title = 'Remove from favorites';
                } else {
                    btn.innerHTML = '🤍';
                    btn.classList.remove('active');
                    btn.title = 'Add to favorites';
                }
            } else {
                alert(data.message || 'Kuch masla hua, dobara try karein.');
            }
        })
        .catch(err => {
            btn.disabled = false;
            alert('Network error, dobara try karein.');
        });
    }
    </script>

    <?php include 'chatbot_widget.php'; ?>
</body>
</html>