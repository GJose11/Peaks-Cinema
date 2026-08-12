<?php
date_default_timezone_set('Asia/Manila');
include("../peakscinemas_database.php");

// ── CRUD Logic ────────────────────────────────────────────────
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item']) || isset($_POST['edit_item'])) {
        $itemName    = mysqli_real_escape_string($conn, $_POST['item_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $price       = (float)$_POST['price'];
        $category    = mysqli_real_escape_string($conn, $_POST['category']);
        $isAvailable = isset($_POST['is_available']) ? 1 : 0;
        $sortOrder   = (int)$_POST['sort_order'];
        
        $imageURL = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $targetDir = "../uploads/food/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $fileExt = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExt;
            $targetFile = $targetDir . $fileName;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $imageURL = "uploads/food/" . $fileName;
            }
        }

        if (isset($_POST['add_item'])) {
            $sql = "INSERT INTO food_items (ItemName, Description, Price, Category, ImageURL, IsAvailable, SortOrder) 
                    VALUES ('$itemName', '$description', $price, '$category', " . ($imageURL ? "'$imageURL'" : "NULL") . ", $isAvailable, $sortOrder)";
            if ($conn->query($sql)) {
                $msg = "Item added successfully!";
                $msgType = "ok";
            } else {
                $msg = "Error adding item: " . $conn->error;
                $msgType = "err";
            }
        } else {
            $itemId = (int)$_POST['item_id'];
            $imgUpdate = $imageURL ? ", ImageURL='$imageURL'" : "";
            $sql = "UPDATE food_items SET 
                    ItemName='$itemName', 
                    Description='$description', 
                    Price=$price, 
                    Category='$category', 
                    IsAvailable=$isAvailable, 
                    SortOrder=$sortOrder 
                    $imgUpdate 
                    WHERE Item_ID=$itemId";
            if ($conn->query($sql)) {
                $msg = "Item updated successfully!";
                $msgType = "ok";
            } else {
                $msg = "Error updating item: " . $conn->error;
                $msgType = "err";
            }
        }
    } elseif (isset($_POST['delete_item'])) {
        $itemId = (int)$_POST['item_id'];
        if ($conn->query("DELETE FROM food_items WHERE Item_ID=$itemId")) {
            $msg = "Item deleted successfully!";
            $msgType = "ok";
        } else {
            $msg = "Error deleting item: " . $conn->error;
            $msgType = "err";
        }
    }
}

// ── Fetch Items ───────────────────────────────────────────────
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';

$where = " WHERE 1=1 ";
if ($search) $where .= " AND (ItemName LIKE '%$search%' OR Description LIKE '%$search%') ";
if ($categoryFilter) $where .= " AND Category = '$categoryFilter' ";

$itemsRes = $conn->query("SELECT * FROM food_items $where ORDER BY SortOrder ASC, ItemName ASC");
$items = $itemsRes ? $itemsRes->fetch_all(MYSQLI_ASSOC) : [];

$categories = ['Food', 'Drinks', 'Combo'];

function getFoodItemFallback(string $category): array
{
    $normalized = strtolower(trim($category));

    return match ($normalized) {
        'drinks' => ['class' => 'drinks', 'icon' => 'DR'],
        'combo' => ['class' => 'combo', 'icon' => 'CM'],
        default => ['class' => 'food', 'icon' => 'FD'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>Food Management – Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background: #0f0f0f; color: #F9F9F9; min-height: 100vh; padding-top: 80px; }
        
        header { background: #1C1C1C; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; position: fixed; top: 0; left: 0; width: 100%; height: 60px; border-bottom: 1px solid rgba(255,255,255,0.06); z-index: 1000; }
        .logo { text-decoration: none; display:flex; align-items:center; }
        .logo img { height: 42px; width: auto; filter: invert(1); display: block; }
        nav { display: flex; gap: 4px; }
        nav a { color: rgba(249,249,249,0.5); text-decoration: none; font-size: 0.8rem; font-weight: 500; padding: 6px 14px; border-radius: 6px; transition: all 0.2s; }
        nav a:hover { background: rgba(255,255,255,0.08); color: #F9F9F9; }
        nav a.active { background: rgba(255,77,77,0.12); color: #ff4d4d; }

        @media (max-width: 1024px) {
            header { padding: 0 15px; }
            nav { display: none; }
            .mobile-nav-toggle { display: flex !important; }
        }

        .mobile-nav-toggle {
            display: none;
            flex-direction: column;
            gap: 4px;
            cursor: pointer;
            padding: 10px;
        }
        .mobile-nav-toggle span {
            width: 24px;
            height: 2px;
            background: #fff;
            border-radius: 2px;
        }

        #mobileMenu {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            width: 100%;
            background: #1C1C1C;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            z-index: 999;
            padding: 10px 0;
        }
        #mobileMenu a {
            display: block;
            padding: 12px 20px;
            color: rgba(249,249,249,0.6);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        #mobileMenu a.active { color: #ff4d4d; background: rgba(255,77,77,0.05); }

        .container { width: 95%; max-width: 1200px; margin: 0 auto; padding-bottom: 60px; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        .page-title { font-size: 1.7rem; font-weight: 800; }
        .page-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #ff4d4d; }

        @media (max-width: 768px) {
            .container { width: 94%; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 14px; }
            .panel-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .filters,
            .filters form { flex-direction: column; align-items: stretch !important; width: 100%; }
            .search-input { width: 100% !important; }
        }

        .panel { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.07); border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; justify-content: space-between; align-items: center; }
        .panel-header h2 { font-size: 0.85rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: rgba(249,249,249,0.45); }
        .panel-body { padding: 20px; }

        .btn { padding: 10px 20px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; transition: all 0.2s; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #ff4d4d; color: white; }
        .btn-primary:hover { background: #e03c3c; transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.1); color: rgba(249,249,249,0.6); }
        .btn-outline:hover { background: rgba(255,255,255,0.05); color: #F9F9F9; }
        .btn-danger { background: rgba(255,77,77,0.1); color: #ff4d4d; border: 1px solid rgba(255,77,77,0.2); }
        .btn-danger:hover { background: #ff4d4d; color: white; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 16px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: rgba(249,249,249,0.3); border-bottom: 1px solid rgba(255,255,255,0.06); }
        .data-table td { padding: 14px 16px; font-size: 0.88rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .data-table tr:hover { background: rgba(255,255,255,0.02); }
        
        .item-media { width: 40px; height: 40px; position: relative; }
        .item-img,
        .item-fallback { width: 40px; height: 40px; border-radius: 8px; }
        .item-img { object-fit: cover; background: #222; display: block; border: 1px solid rgba(255,255,255,0.08); }
        .item-img.is-hidden { display: none; }
        .item-fallback {
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            border: 1px solid rgba(255,255,255,0.08);
            background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02));
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .item-fallback.is-visible { display: flex; }
        .item-fallback-food { color: #f59e0b; background: linear-gradient(135deg, rgba(245,158,11,0.18), rgba(255,77,77,0.1)); }
        .item-fallback-drinks { color: #34d399; background: linear-gradient(135deg, rgba(16,185,129,0.18), rgba(59,130,246,0.1)); }
        .item-fallback-combo { color: #f9f9f9; background: linear-gradient(135deg, rgba(245,158,11,0.18), rgba(59,130,246,0.14)); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
        .badge-food { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .badge-drinks { background: rgba(16,185,129,0.1); color: #10b981; }
        .badge-combo { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
        .status-active { background: #22c55e; }
        .status-inactive { background: #6b7280; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: rgba(249,249,249,0.4); margin-bottom: 6px; }
        .form-control { width: 100%; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 14px; color: #F9F9F9; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: #ff4d4d; }
        
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal.active { display: flex; }
        .modal-content { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        
        .filters { display: flex; gap: 12px; align-items: center; }
        .search-input { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 8px 12px; color: white; font-size: 0.85rem; width: 240px; }
        
        .flash { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 0.88rem; display: flex; align-items: center; gap: 10px; }
        .flash.ok { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); color: #22c55e; }
        .flash.err { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444; }
    </style>
</head>
<body>

<header>
    <a href="dashboard.php" class="logo"><img src="../peakscinematransparent.png" alt="Peak's Cinema Logo"></a>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="malls_selection_admin.php">Malls</a>
        <a href="malls_selection_admin.php">Add Screenings</a>
        <a href="movie_upload.php">Movie Upload</a>
        <a href="food_admin.php" class="active">Food & Drinks</a>
        <a href="theater_upload.php">Theater Upload</a>
        <a href="mall_upload.php">Mall Upload</a>
        <a href="queue_admin.php">Queue Manager</a>
    </nav>
    <div style="display:flex;align-items:center;gap:8px;">
        <div class="mobile-nav-toggle" onclick="toggleMobileMenu()">
            <span></span><span></span><span></span>
        </div>
        <a href="../home.php" class="desktop-only"
           style="color:rgba(249,249,249,0.45);text-decoration:none;font-size:0.78rem;font-weight:500;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);
                  transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,255,255,0.06)';this.style.color='#F9F9F9'"
           onmouseout="this.style.background='';this.style.color='rgba(249,249,249,0.45)'">
            &#8592; Customer Site
        </a>
        <a href="admin_logout.php" class="desktop-only"
           style="color:#ff4d4d;text-decoration:none;font-size:0.78rem;font-weight:600;
                  padding:6px 14px;border-radius:6px;border:1px solid rgba(255,77,77,0.3);
                  background:rgba(255,77,77,0.08);transition:all 0.2s;display:flex;align-items:center;gap:5px;"
           onmouseover="this.style.background='rgba(255,77,77,0.18)'"
           onmouseout="this.style.background='rgba(255,77,77,0.08)'"
           onclick="return confirm('Log out of admin panel?')">
            &#x2192; Log Out
        </a>
    </div>
</header>

<div id="mobileMenu">
    <a href="dashboard.php">Dashboard</a>
    <a href="malls_selection_admin.php">Malls</a>
    <a href="movie_upload.php">Movie Upload</a>
    <a href="food_admin.php" class="active">Food & Drinks</a>
    <a href="theater_upload.php">Theater Upload</a>
    <a href="mall_upload.php">Mall Upload</a>
    <a href="queue_admin.php">Queue Manager</a>
    <hr style="opacity:0.1; margin:10px 20px;">
    <a href="../home.php">&#8592; Customer Site</a>
    <a href="admin_logout.php" style="color:#ff4d4d;">&#x2192; Log Out</a>
</div>

<style>
@media (max-width: 1024px) {
    .desktop-only { display: none !important; }
}
</style>

<div class="container">
    <div class="page-header">
        <div>
            <p class="page-label">Management</p>
            <h1 class="page-title">Food & Drinks</h1>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">+ Add New Item</button>
    </div>

    <?php if ($msg): ?>
        <div class="flash <?= $msgType ?>">
            <?= $msgType === 'ok' ? '✅' : '❌' ?> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <h2>🍿 Menu Items (<?= count($items) ?>)</h2>
            <div class="filters">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Search menu..." class="search-input" value="<?= htmlspecialchars($search) ?>">
                    <select name="category" class="search-input" style="width: 120px;" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($search || $categoryFilter): ?>
                        <a href="food_admin.php" class="btn btn-outline" style="padding: 8px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="panel-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Image</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $fallback = getFoodItemFallback($item['Category'] ?? 'Food');
                            $imagePath = !empty($item['ImageURL']) ? '../' . ltrim($item['ImageURL'], '/') : '';
                        ?>
                        <tr>
                            <td>
                                <div class="item-media">
                                    <?php if ($imagePath): ?>
                                        <img
                                            src="<?= htmlspecialchars($imagePath) ?>"
                                            class="item-img"
                                            alt="<?= htmlspecialchars($item['ItemName']) ?>"
                                            onerror="this.classList.add('is-hidden'); this.nextElementSibling.classList.add('is-visible');"
                                        >
                                    <?php endif; ?>
                                    <div class="item-fallback item-fallback-<?= $fallback['class'] ?><?= $imagePath ? '' : ' is-visible' ?>" aria-hidden="true">
                                        <?= $fallback['icon'] ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= htmlspecialchars($item['ItemName']) ?></div>
                                <div style="font-size: 0.72rem; color: rgba(249,249,249,0.3);"><?= htmlspecialchars($item['Description']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower($item['Category']) ?>"><?= $item['Category'] ?></span>
                            </td>
                            <td style="font-weight: 700; color: #ff4d4d;">₱<?= number_format($item['Price'], 2) ?></td>
                            <td>
                                <span class="status-dot status-<?= $item['IsAvailable'] ? 'active' : 'inactive' ?>"></span>
                                <?= $item['IsAvailable'] ? 'Available' : 'Sold Out' ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <button class="btn btn-outline" style="padding: 6px 10px;" onclick='editItem(<?= json_encode($item) ?>)'>Edit</button>
                                    <form method="POST" onsubmit="return confirm('Delete this item?')" style="display: inline;">
                                        <input type="hidden" name="item_id" value="<?= $item['Item_ID'] ?>">
                                        <button type="submit" name="delete_item" class="btn btn-danger" style="padding: 6px 10px;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: rgba(249,249,249,0.2);">No menu items found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="panel-header">
            <h2>Add New Menu Item</h2>
            <button class="btn btn-outline" style="padding: 4px 8px;" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="panel-body">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2" required></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" name="price" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 10px; padding-top: 24px;">
                    <input type="checkbox" name="is_available" id="add_avail" checked>
                    <label for="add_avail" style="margin-bottom: 0;">Available for Sale</label>
                </div>
            </div>
            <div class="form-group">
                <label>Image Upload</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div style="margin-top: 10px; display: flex; gap: 12px;">
                <button type="submit" name="add_item" class="btn btn-primary" style="flex: 1;">Add Item</button>
                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeModal('addModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="panel-header">
            <h2>Edit Menu Item</h2>
            <button class="btn btn-outline" style="padding: 4px 8px;" onclick="closeModal('editModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="panel-body">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" name="item_name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_desc" class="form-control" rows="2" required></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" name="price" id="edit_price" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="edit_category" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="edit_sort" class="form-control">
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 10px; padding-top: 24px;">
                    <input type="checkbox" name="is_available" id="edit_avail">
                    <label for="edit_avail" style="margin-bottom: 0;">Available for Sale</label>
                </div>
            </div>
            <div class="form-group">
                <label>Change Image (optional)</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            <div style="margin-top: 10px; display: flex; gap: 12px;">
                <button type="submit" name="edit_item" class="btn btn-primary" style="flex: 1;">Update Item</button>
                <button type="button" class="btn btn-outline" style="flex: 1;" onclick="closeModal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
function editItem(item) {
    document.getElementById('edit_item_id').value = item.Item_ID;
    document.getElementById('edit_name').value = item.ItemName;
    document.getElementById('edit_desc').value = item.Description;
    document.getElementById('edit_price').value = item.Price;
    document.getElementById('edit_category').value = item.Category;
    document.getElementById('edit_sort').value = item.SortOrder;
    document.getElementById('edit_avail').checked = item.IsAvailable == 1;
    openModal('editModal');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>

</body>
</html>
