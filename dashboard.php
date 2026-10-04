<?php
session_start();
include('config/db_connection.php');

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = isset($_SESSION['name']) ? $_SESSION['name'] : 'User';

$total_properties = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM properties"));
$available_properties = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM properties WHERE status='available'"));
$occupied_properties = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM properties WHERE status='occupied'"));
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM rental_requests"));

$menus = [];
if($role == 'tenant'){
    $menus = [
        ['properties.php', '🏠', 'Properties', 'Browse listings'],
        ['schedule_visit.php', '📅', 'Schedule Visit', 'Book a viewing'],
        ['booking.php', '📋', 'My Bookings & Payment', 'Track & pay rent'],
        ['my_contract.php', '📄', 'My Contract', 'View lease'],
        ['payments_history.php', '💰', 'Payment History', 'Transactions'],
        ['property_status.php', '📊', 'Property Status', 'Occupancy'],
        ['maintenance.php', '🔧', 'Maintenance', 'Report issue'],
        ['my_favourite.php', '❤️', 'My Favorites', 'Saved properties'],
    ];
} elseif($role == 'owner'){
    $menus = [
        ['properties.php', '🏠', 'Properties', 'Browse listings'],
        ['schedule_visit.php', '📅', 'Schedule Visit', 'Book viewing'],
        ['booking.php', '📋', 'Bookings', 'Track requests'],
        ['property_status.php', '📊', 'Property Status', 'Occupancy'],
        ['payments_history.php', '💰', 'Payment History', 'Transactions'],
        ['maintenance.php', '🔧', 'Maintenance', 'Report issue'],
        ['view_tenant_details.php', '👥', 'Tenant Details', 'View tenants'],
    ];
} elseif($role == 'manager'){
    $menus = [
        ['properties.php', '🏠', 'Properties', 'Browse listings'],
        ['schedule_visit.php', '📅', 'Schedule Visit', 'Book viewing'],
        ['booking.php', '📋', 'Bookings', 'Track requests'],
        ['my_contract.php', '📄', 'Contracts', 'Lease details'],
        ['maintenance.php', '🔧', 'Maintenance', 'Report issue'],
        ['generate_report.php', '📈', 'Generate Report', 'Analytics'],
    ];
} elseif($role == 'admin'){
    $menus = [
        ['properties.php', '🏠', 'Properties', 'Browse listings'],
        ['manage_users.php', '👥', 'Manage Users', 'User accounts'],
        ['manage_property_manager.php', '👨‍💼', 'Manage Managers', 'Assign managers'],
        ['manage_property.php', '🏢', 'Manage Property', 'Edit listings'],
        ['payments_history.php', '💰', 'Payment History', 'Transactions'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Hira Property</title>
    <!-- <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"> -->
    <style>
        :root {
            --bg: #FAF8F4;
            --white: #FFFFFF;
            --sidebar: #1B2340;
            --sidebar-deep: #12162A;
            --accent: #E8622A;
            --accent2: #c94d1a;
            --navy: #1B2340;
            --text: #221F1C;
            --muted: #726C63;
            --line: #EDE8DE;
            --green: #2D7A4F;
            --purple: #6B42A8;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 240px;
            background: var(--sidebar-deep);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0; top: 0;
            z-index: 100;
        }
        .sidebar-logo {
            padding: 28px 22px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .logo-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }
        .logo-mark {
            width: 28px; height: 28px;
            background: var(--accent);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-mark svg { display: block; }
        .logo-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 19px; font-weight: 700;
            color: #fff; letter-spacing: 0.3px;
        }
        .logo-name span { color: var(--accent); }
        .logo-sub {
            font-size: 10px; color: rgba(255,255,255,0.3);
            letter-spacing: 1.5px; text-transform: uppercase;
            padding-left: 38px;
        }
        .sidebar-user {
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 11px;
        }
        .avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0;
        }
        .u-name { color: #fff; font-size: 13px; font-weight: 500; }
        .u-role {
            display: inline-block;
            background: rgba(232,98,42,0.25); color: #F2956A;
            font-size: 9px; padding: 2px 8px; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.8px; margin-top: 3px;
        }
        .sidebar-nav { flex: 1; padding: 14px 10px; overflow-y: auto; }
        .nav-section-label {
            font-size: 9.5px; color: rgba(255,255,255,0.25);
            text-transform: uppercase; letter-spacing: 1.2px;
            padding: 12px 12px 5px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 12px; border-radius: 7px;
            text-decoration: none; color: rgba(255,255,255,0.55);
            font-size: 13px; margin-bottom: 1px;
            transition: all 0.18s ease;
        }
        .nav-item:hover { background: rgba(232,98,42,0.16); color: #fff; }
        .nav-icon { font-size: 15px; flex-shrink: 0; }
        .nav-label { font-weight: 400; }
        .nav-desc { font-size: 10px; color: rgba(255,255,255,0.3); margin-top: 1px; }
        .nav-item:hover .nav-desc { color: rgba(255,255,255,0.5); }
        .sidebar-foot {
            padding: 14px 22px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .logout-link {
            display: flex; align-items: center; gap: 9px;
            color: rgba(255,100,100,0.75); text-decoration: none;
            font-size: 13px; padding: 8px 0; transition: color 0.15s;
        }
        .logout-link:hover { color: #ff8080; }
        .main { margin-left: 240px; flex: 1; padding: 36px 40px 48px; }
        .page-header {
            display: flex; justify-content: space-between;
            align-items: flex-start; margin-bottom: 34px;
        }
        .greeting-small {
            font-size: 11px; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;
        }
        .greeting-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px; font-weight: 700;
            color: var(--navy); line-height: 1.1;
        }
        .greeting-name em { color: var(--accent); font-style: italic; }
        .greeting-date { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .stats-row {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 36px;
        }
        .stat {
            background: var(--white); padding: 22px 24px;
            text-decoration: none; color: inherit;
            transition: background 0.15s;
        }
        .stat:hover { background: #FDF6F0; }
        .stat-tag {
            font-size: 9.5px; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px;
        }
        .stat-val {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 700; line-height: 1;
        }
        .stat-val.big { font-size: 48px; color: var(--accent); }
        .stat-val.med { font-size: 36px; color: var(--navy); }
        .stat-foot { font-size: 11px; color: var(--muted); margin-top: 8px; }
        .stat.s-available .stat-val { color: var(--green); }
        .stat.s-occupied .stat-val { color: var(--navy); }
        .stat.s-bookings .stat-val { color: var(--purple); }
        .section-head {
            display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
        }
        .section-line { height: 1px; background: var(--line); flex: 1; }
        .section-label {
            font-size: 10px; color: var(--muted);
            text-transform: uppercase; letter-spacing: 1.3px;
        }
        .qa-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .qa-card {
            background: var(--white);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px 18px;
            text-decoration: none; color: var(--text);
            display: flex; align-items: center; gap: 14px;
            transition: all 0.18s ease;
        }
        .qa-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 16px rgba(232,98,42,0.12);
            transform: translateY(-2px);
        }
        .qa-icon {
            width: 42px; height: 42px; background: #FDF0E8;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0; transition: background 0.18s;
        }
        .qa-card:hover .qa-icon { background: var(--accent); }
        .qa-body { flex: 1; min-width: 0; }
        .qa-title {
            font-size: 13px; font-weight: 600; color: var(--navy);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .qa-desc { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .qa-arrow { color: #ccc; font-size: 14px; flex-shrink: 0; }
        .qa-card:hover .qa-arrow { color: var(--accent); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-top">
            <div class="logo-mark" style="overflow:hidden;">
                <img src="assets/logo.png" alt="Hira Property"
                     style="width:100%; height:100%; object-fit:cover; border-radius:6px;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" style="display:none;">
                    <path d="M3 21V9L12 3l9 6v12" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 21v-8h6v8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <span class="logo-name">Hira <span>Property</span></span>
        </div>
        <div class="logo-sub">Rental Management</div>
    </div>

    <div class="sidebar-user">
        <div class="avatar"><?php echo strtoupper(substr($username,0,1)); ?></div>
        <div>
            <div class="u-name"><?php echo htmlspecialchars($username); ?></div>
            <span class="u-role"><?php echo ucfirst($role); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu</div>
        <?php foreach($menus as $m): ?>
        <a href="<?php echo $m[0]; ?>" class="nav-item">
            <span class="nav-icon"><?php echo $m[1]; ?></span>
            <div>
                <div class="nav-label"><?php echo $m[2]; ?></div>
                <div class="nav-desc"><?php echo $m[3]; ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <a href="logout.php" class="logout-link">🚪 Logout</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <div>
            <div class="greeting-small"><?php echo date('l, j F Y'); ?></div>
            <div class="greeting-name">
                Welcome back, <em><?php echo htmlspecialchars($username); ?></em>
            </div>
            <div class="greeting-date">
                Here's your property overview for today.
            </div>
        </div>
    </div>

    <div class="stats-row">
        <a href="properties.php" class="stat">
            <div class="stat-tag">Total Properties</div>
            <div class="stat-val big"><?php echo $total_properties['total']; ?></div>
            <div class="stat-foot">All listings</div>
        </a>
        <a href="properties.php?status=available" class="stat s-available">
            <div class="stat-tag">Available</div>
            <div class="stat-val med"><?php echo $available_properties['total']; ?></div>
            <div class="stat-foot">Ready to rent</div>
        </a>
        <a href="properties.php?status=occupied" class="stat s-occupied">
            <div class="stat-tag">Occupied</div>
            <div class="stat-val med"><?php echo $occupied_properties['total']; ?></div>
            <div class="stat-foot">Currently rented</div>
        </a>
        <a href="booking.php" class="stat s-bookings">
            <div class="stat-tag">Total Bookings</div>
            <div class="stat-val med"><?php echo $total_bookings['total']; ?></div>
            <div class="stat-foot">All requests</div>
        </a>
    </div>

    <div class="section-head">
        <div class="section-label">Quick Access</div>
        <div class="section-line"></div>
    </div>
    <div class="qa-grid">
        <?php foreach($menus as $m): ?>
        <a href="<?php echo $m[0]; ?>" class="qa-card">
            <div class="qa-icon"><?php echo $m[1]; ?></div>
            <div class="qa-body">
                <div class="qa-title"><?php echo $m[2]; ?></div>
                <div class="qa-desc"><?php echo $m[3]; ?></div>
            </div>
            <div class="qa-arrow">›</div>
        </a>
        <?php endforeach; ?>
    </div>
</main>

<?php include 'chatbot_widget.php'; ?>
</body>
</html>