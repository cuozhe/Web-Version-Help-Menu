<?php
session_start();
require_once __DIR__ . '/db.php';
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}
// 数据库结构兼容：如无card_opacity字段则自动添加
try { $pdo->query("SELECT card_opacity FROM settings LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE settings ADD COLUMN card_opacity FLOAT DEFAULT 1"); }
try { $pdo->query("SELECT hidden FROM sections LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE sections ADD COLUMN hidden TINYINT(1) DEFAULT 0"); }
try { $pdo->query("SELECT hidden FROM items LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE items ADD COLUMN hidden TINYINT(1) DEFAULT 0"); }
// 确保 settings 至少有一条记录
try { $pdo->exec("INSERT INTO settings (id, site_title, logo, background, card_opacity) SELECT 1, '蓑衣帮助', '', '', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM settings WHERE id=1)"); } catch (Exception $e) {}

// 上传图片处理函数（仅允许常见图片类型）
function handle_image_upload($field, $prefix, $oldWebPath = '') {
    if (empty($_FILES[$field]['name'])) {
        return $oldWebPath;
    }
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0775, true);
    }
    $maxSize = 5 * 1024 * 1024; // 5MB
    if (!empty($_FILES[$field]['size']) && $_FILES[$field]['size'] > $maxSize) {
        return $oldWebPath;
    }
    $tmp = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        return $oldWebPath;
    }
    $finfo = function_exists('finfo_open') ? @finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $finfo ? @finfo_file($finfo, $tmp) : '';
    if ($finfo) @finfo_close($finfo);
    $allowed = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico'
    ];
    if (!isset($allowed[$mime])) {
        return $oldWebPath;
    }
    $ext = $allowed[$mime];
    $name = $prefix . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $destLocal = $uploadsDir . '/' . $name;
    if (@move_uploaded_file($tmp, $destLocal)) {
        return '/help/uploads/' . $name;
    }
    return $oldWebPath;
}

// 处理站点设置保存
if (isset($_POST['save_settings'])) {
    $site_title = trim($_POST['site_title'] ?? '');
    $logo = handle_image_upload('logo', 'logo', $_POST['old_logo'] ?? '');
    $background = handle_image_upload('background', 'bg', $_POST['old_background'] ?? '');
    $card_opacity = floatval($_POST['card_opacity'] ?? 1);
    if ($card_opacity < 0.1) $card_opacity = 0.1;
    if ($card_opacity > 1) $card_opacity = 1;
    $pdo->prepare('UPDATE settings SET site_title=?, logo=?, background=?, card_opacity=? WHERE id=1')
        ->execute([$site_title, $logo, $background, $card_opacity]);
    header('Location: index.php?msg=' . urlencode('站点设置已保存') . '#top');
    exit;
}
// 处理板块添加
if (isset($_POST['add_section'])) {
    $title = $_POST['section_title'] ?? '';
    // 检查sections表是否有section_id字段
    $cols = $pdo->query("SHOW COLUMNS FROM sections")->fetchAll();
    $has_section_id = false;
    foreach($cols as $col){ if($col['Field']==='section_id'){$has_section_id=true;break;}}
    // 计算排序
    $order = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM sections')->fetchColumn();
    if($has_section_id){
        $pdo->prepare('INSERT INTO sections (section_id, title, sort_order) VALUES (?, ?, ?)')->execute([null, $title, $order]);
    }else{
        $pdo->prepare('INSERT INTO sections (title, sort_order) VALUES (?, ?)')->execute([$title, $order]);
    }
    $new_id = $pdo->lastInsertId();
    header('Location: index.php?msg=' . urlencode('板块已添加') . '#section-' . $new_id);
    exit;
}
// 处理板块修改
if (isset($_POST['edit_section'])) {
    $id = intval($_POST['section_id'] ?? 0);
    $title = $_POST['section_title'] ?? '';
    if ($id > 0) {
        $pdo->prepare('UPDATE sections SET title=? WHERE id=?')->execute([$title, $id]);
        header('Location: index.php?msg=' . urlencode('板块已修改') . '#section-' . $id);
        exit;
    }
}
// 处理板块删除
if (isset($_GET['del_section'])) {
    $del_id = $_GET['del_section'];
    $pdo->prepare('DELETE FROM sections WHERE id=?')->execute([$del_id]);
    header('Location: index.php?msg=' . urlencode('板块已删除') . '#top');
    exit;
}
// 处理板块隐藏/显示
if (isset($_GET['toggle_section'])) {
    $id = intval($_GET['toggle_section']);
    $hidden = intval($_GET['hidden']);
    $pdo->prepare('UPDATE sections SET hidden=? WHERE id=?')->execute([$hidden, $id]);
    header('Location: index.php?msg=' . urlencode('板块已更新') . '#section-' . $id);
    exit;
}
// 处理板块排序 上移/下移
if (isset($_GET['move_section'])) {
    $id = intval($_GET['move_section']);
    $dir = ($_GET['dir'] ?? 'up') === 'down' ? 'down' : 'up';
    try {
        $pdo->beginTransaction();
        $rows = $pdo->query('SELECT id, sort_order FROM sections ORDER BY sort_order, id')->fetchAll();
        $stmt = $pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?');
        $i = 1;
        foreach ($rows as $r) { $stmt->execute([$i++, $r['id']]); }
        $rows = $pdo->query('SELECT id, sort_order FROM sections ORDER BY sort_order, id')->fetchAll();
        $index = -1;
        for ($j=0;$j<count($rows);$j++){ if ((int)$rows[$j]['id'] === $id) { $index = $j; break; } }
        if ($index !== -1) {
            if ($dir === 'up' && $index > 0) {
                $curr = (int)$rows[$index]['sort_order'];
                $nbrId = (int)$rows[$index-1]['id'];
                $nbrOrder = (int)$rows[$index-1]['sort_order'];
                $pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?')->execute([$nbrOrder, $id]);
                $pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?')->execute([$curr, $nbrId]);
            } elseif ($dir === 'down' && $index < count($rows) - 1) {
                $curr = (int)$rows[$index]['sort_order'];
                $nbrId = (int)$rows[$index+1]['id'];
                $nbrOrder = (int)$rows[$index+1]['sort_order'];
                $pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?')->execute([$nbrOrder, $id]);
                $pdo->prepare('UPDATE sections SET sort_order=? WHERE id=?')->execute([$curr, $nbrId]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        @ $pdo->rollBack();
    }
    header('Location: index.php?msg=' . urlencode('板块顺序已更新') . '#section-' . $id);
    exit;
}
// 处理菜单项添加
if (isset($_POST['add_item'])) {
    $section_id = intval($_POST['item_section_id']);
    $title = trim($_POST['item_title'] ?? '');
    $desc = trim($_POST['item_desc'] ?? '');
    $icon = handle_image_upload('item_icon', 'icon', '');
    $url = trim($_POST['item_url'] ?? '');
    if ($url && !preg_match('#^(https?://|/|mailto:|tel:)#i', $url)) { $url = 'http://' . $url; }
    // 检查items表是否有section_id_fk字段
    $has_fk = false;
    $cols = $pdo->query("SHOW COLUMNS FROM items")->fetchAll();
    foreach($cols as $col){ if($col['Field']==='section_id_fk'){$has_fk=true;break;}}
    // 计算排序
    $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM items WHERE section_id=?');
    $orderStmt->execute([$section_id]);
    $order = (int)$orderStmt->fetchColumn();

    if($has_fk){
        $pdo->prepare('INSERT INTO items (section_id, section_id_fk, title, description, icon, url, sort_order) VALUES (?,?,?,?,?,?,?)')
            ->execute([$section_id, $section_id, $title, $desc, $icon, $url, $order]);
    }else{
        $pdo->prepare('INSERT INTO items (section_id, title, description, icon, url, sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$section_id, $title, $desc, $icon, $url, $order]);
    }
    $new_item_id = $pdo->lastInsertId();
    header('Location: index.php?msg=' . urlencode('菜单项已添加') . '#item-' . $new_item_id);
    exit;
}
// 处理菜单项修改
if (isset($_POST['edit_item'])) {
    $id = intval($_POST['item_id'] ?? 0);
    $title = trim($_POST['item_title'] ?? '');
    $desc = trim($_POST['item_desc'] ?? '');
    $url = trim($_POST['item_url'] ?? '');
    if ($url && !preg_match('#^(https?://|/|mailto:|tel:)#i', $url)) { $url = 'http://' . $url; }
    $icon = $_POST['old_icon'] ?? '';
    $icon = handle_image_upload('item_icon', 'icon', $icon);
    if ($id > 0) {
        $pdo->prepare('UPDATE items SET title=?, description=?, url=?, icon=? WHERE id=?')
            ->execute([$title, $desc, $url, $icon, $id]);
        header('Location: index.php?msg=' . urlencode('菜单项已修改') . '#item-' . $id);
        exit;
    }
}
// 处理菜单项删除
if (isset($_GET['del_item'])) {
    $del_id = $_GET['del_item'];
    $pdo->prepare('DELETE FROM items WHERE id=?')->execute([$del_id]);
    $section_id = 0;
    if (isset($_GET['section'])) {
        $section_id = intval($_GET['section']);
    }
    $anchor = $section_id ? ('#section-' . $section_id) : '#top';
    header('Location: index.php?msg=' . urlencode('菜单项已删除') . $anchor);
    exit;
}
// 处理菜单项隐藏/显示
if (isset($_GET['toggle_item'])) {
    $id = intval($_GET['toggle_item']);
    $hidden = intval($_GET['hidden']);
    $section_id = intval($_GET['section'] ?? 0);
    $pdo->prepare('UPDATE items SET hidden=? WHERE id=?')->execute([$hidden, $id]);
    $anchor = $section_id ? ('#section-' . $section_id) : '#top';
    header('Location: index.php?msg=' . urlencode('菜单项已更新') . $anchor);
    exit;
}
// 处理菜单项排序 上移/下移
if (isset($_GET['reorder_item'])) {
    $id = intval($_GET['reorder_item']);
    $dir = ($_GET['dir'] ?? 'up') === 'down' ? 'down' : 'up';
    $stmt = $pdo->prepare('SELECT section_id FROM items WHERE id=?');
    $stmt->execute([$id]);
    $section_id = (int)($stmt->fetchColumn() ?: 0);
    if ($section_id) {
        try {
            $pdo->beginTransaction();
            $rowsStmt = $pdo->prepare('SELECT id, sort_order FROM items WHERE section_id=? ORDER BY sort_order, id');
            $rowsStmt->execute([$section_id]);
            $list = $rowsStmt->fetchAll();
            $seq = $pdo->prepare('UPDATE items SET sort_order=? WHERE id=?');
            $i = 1;
            foreach ($list as $r) { $seq->execute([$i++, $r['id']]); }
            $rowsStmt->execute([$section_id]);
            $list = $rowsStmt->fetchAll();
            $index = -1;
            for ($j=0;$j<count($list);$j++){ if ((int)$list[$j]['id'] === $id) { $index = $j; break; } }
            if ($index !== -1) {
                if ($dir === 'up' && $index > 0) {
                    $curr = (int)$list[$index]['sort_order'];
                    $nbrId = (int)$list[$index-1]['id'];
                    $nbrOrder = (int)$list[$index-1]['sort_order'];
                    $pdo->prepare('UPDATE items SET sort_order=? WHERE id=?')->execute([$nbrOrder, $id]);
                    $pdo->prepare('UPDATE items SET sort_order=? WHERE id=?')->execute([$curr, $nbrId]);
                } elseif ($dir === 'down' && $index < count($list) - 1) {
                    $curr = (int)$list[$index]['sort_order'];
                    $nbrId = (int)$list[$index+1]['id'];
                    $nbrOrder = (int)$list[$index+1]['sort_order'];
                    $pdo->prepare('UPDATE items SET sort_order=? WHERE id=?')->execute([$nbrOrder, $id]);
                    $pdo->prepare('UPDATE items SET sort_order=? WHERE id=?')->execute([$curr, $nbrId]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            @ $pdo->rollBack();
        }
    }
    $anchor = $section_id ? ('#section-' . $section_id) : '#top';
    header('Location: index.php?msg=' . urlencode('菜单项顺序已更新') . $anchor);
    exit;
}
// 处理菜单项移动
if (isset($_POST['move_item'])) {
    $id = $_POST['item_id'];
    $new_section = $_POST['move_to_section'];
    $pdo->prepare('UPDATE items SET section_id=? WHERE id=?')->execute([$new_section, $id]);
    header('Location: index.php?msg=' . urlencode('菜单项已移动') . '#section-' . $new_section);
    exit;
}
// 读取所有板块（包括隐藏的）
$all_sections = $pdo->query('SELECT * FROM sections ORDER BY sort_order, id')->fetchAll();
// 只显示未隐藏的板块用于前台和下拉移动列表
$sections = array_filter($all_sections, function($s){return !$s['hidden'];});
// 读取所有菜单项（包括隐藏的）用于后台管理
$all_items = $pdo->query('SELECT * FROM items ORDER BY section_id, sort_order, id')->fetchAll();
$items_by_section = [];
foreach ($all_items as $item) {
    $items_by_section[$item['section_id']][] = $item;
}
$msg = $_GET['msg'] ?? '';
// 页面顶部加锚点
echo '<div id="top"></div>';
$setting = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch();
if (!$setting) $setting = ['site_title'=>'', 'logo'=>'', 'background'=>'', 'card_opacity'=>1];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>后台管理</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', Arial, sans-serif; }
        .admin-box { max-width: 900px; margin: 40px auto; padding: 32px 36px; background: #fff; border-radius: 18px; box-shadow: 0 4px 24px #0001; }
        h1, h2 { color: #2d3a4b; }
        .msg { color: #2196f3; margin-bottom: 18px; }
        label { display: inline-block; min-width: 80px; color: #555; margin-bottom: 8px; }
        input[type=text], input[type=password], input[type=file], input[type=number] {
            border: 1px solid #d0d7de; border-radius: 6px; padding: 6px 10px; margin-right: 8px; margin-bottom: 8px; background: #f8fafc; transition: border 0.2s; }
        input[type=text]:focus, input[type=password]:focus { border-color: #2196f3; outline: none; }
        button { background: #2196f3; color: #fff; border: none; border-radius: 6px; padding: 6px 18px; margin-left: 4px; cursor: pointer; transition: background 0.2s; }
        button:hover { background: #1769aa; }
        .section { background: #f7fafd; border-radius: 12px; padding: 18px 20px; margin-bottom: 28px; box-shadow: 0 2px 8px #0001; }
        .item {
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0001;
            padding: 14px 18px;
        }
        .item:last-child { margin-bottom: 0; }
        .item form, .section form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
        .item .op-group { display: flex; gap: 8px; align-items: center; }
        .item img, .section img { vertical-align: middle; height: 24px; border-radius: 4px; margin-left: 4px; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .admin-header a { color: #2196f3; text-decoration: none; font-size: 15px; }
        .admin-header a:hover { text-decoration: underline; }
        hr { border: none; border-top: 1px solid #e0e6ed; margin: 32px 0; }
        .logo-preview, .bg-preview { max-height:40px; max-width:80px; border-radius:8px; vertical-align:middle; }
        .icon-preview { max-height:40px; max-width:40px; border-radius:6px; vertical-align:middle; }
        .btn-op { display:inline-block; padding:4px 12px; border-radius:5px; font-size:14px; text-decoration:none; background:#e3eaf2; color:#2196f3; border:none; cursor:pointer; transition:background 0.2s; }
        .btn-op:hover { background:#d0e2f7; }
        .btn-del { background:#ffeaea; color:#f44336; }
        .btn-del:hover { background:#ffd6d6; }
        .btn-hide { background:#e3eaf2; color:#2196f3; }
        .btn-hide:hover { background:#d0e2f7; }
        @media (max-width: 600px) { .admin-box { padding: 10px; } .section { padding: 8px; } }
    </style>
    <script>
    function showMoveSelect(id) {
        document.getElementById('move-select-' + id).style.display = 'inline-flex';
        document.getElementById('move-btn-' + id).style.display = 'none';
    }
    function cancelMoveSelect(id) {
        document.getElementById('move-select-' + id).style.display = 'none';
        document.getElementById('move-btn-' + id).style.display = 'inline-block';
    }
    function filterItems(input) {
        var q = (input.value || '').trim().toLowerCase();
        var sections = document.querySelectorAll('.section');
        sections.forEach(function(sec){
            var items = sec.querySelectorAll('.item');
            var any = false;
            items.forEach(function(it){
                var t = (it.querySelector('input[name="item_title"]').value || '').toLowerCase();
                var d = (it.querySelector('input[name="item_desc"]').value || '').toLowerCase();
                var u = (it.querySelector('input[name="item_url"]').value || '').toLowerCase();
                var match = !q || t.includes(q) || d.includes(q) || u.includes(q);
                it.style.display = match ? '' : 'none';
                if (match) any = true;
            });
            if (q) {
                sec.style.opacity = any ? '1' : '0.4';
            } else {
                sec.style.opacity = '';
            }
        });
    }
    </script>
</head>
<body>
<div class="admin-box">
    <div class="admin-header">
        <h1>后台管理</h1>
        <a href="logout.php">退出登录</a>
    </div>
    <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <h2>站点设置</h2>
    <form method="post" enctype="multipart/form-data" style="margin-bottom:24px;gap:12px;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;">
        <input type="hidden" name="save_settings" value="1">
        <label>网站标题：<input type="text" name="site_title" value="<?= htmlspecialchars($setting['site_title']) ?>" required></label>
        <label>LOGO：<input type="file" name="logo"> <?php if($setting['logo']): ?><img src="<?= htmlspecialchars($setting['logo']) ?>" alt="logo" class="logo-preview"><?php endif; ?><input type="hidden" name="old_logo" value="<?= htmlspecialchars($setting['logo']) ?>"></label>
        <label>背景图片：<input type="file" name="background"> <?php if($setting['background']): ?><img src="<?= htmlspecialchars($setting['background']) ?>" alt="bg" class="bg-preview"><?php endif; ?><input type="hidden" name="old_background" value="<?= htmlspecialchars($setting['background']) ?>"></label>
        <label>卡片透明度：<input type="number" name="card_opacity" min="0.1" max="1" step="0.05" value="<?= isset($setting['card_opacity']) ? $setting['card_opacity'] : 1 ?>" style="width:60px;"> (0.1~1，1为不透明)</label>
        <div style="flex-basis:100%;height:0;"></div>
        <div style="width:100%;display:flex;justify-content:center;">
            <button type="submit">保存设置</button>
        </div>
    </form>
    <hr>
    <h2>板块管理</h2>
    <div style="margin:10px 0 6px 0; display:flex; justify-content:flex-end;">
        <input type="text" placeholder="搜索菜单项..." oninput="filterItems(this)" style="padding:6px 10px;border:1px solid #d0d7de;border-radius:6px;">
    </div>
    <form method="post" style="margin-bottom:18px;gap:8px;display:flex;flex-wrap:wrap;align-items:center;">
        <input type="text" name="section_title" placeholder="新板块标题" required>
        <button type="submit" name="add_section" value="1">添加板块</button>
    </form>
    <?php foreach($all_sections as $section): ?>
        <div class="section" id="section-<?= $section['id'] ?>" style="<?= $section['hidden'] ? 'opacity:0.5;' : '' ?>">
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                <input type="text" name="section_title" value="<?= htmlspecialchars($section['title']) ?>" required>
                <div style="display:inline-flex;gap:8px;vertical-align:middle;">
                    <button type="submit" name="edit_section" value="1">修改</button>
                    <a href="?move_section=<?= $section['id'] ?>&dir=up#section-<?= $section['id'] ?>" class="btn-op">上移</a>
                    <a href="?move_section=<?= $section['id'] ?>&dir=down#section-<?= $section['id'] ?>" class="btn-op">下移</a>
                    <a href="?del_section=<?= $section['id'] ?>" onclick="return confirm('确定删除该板块及其所有菜单项？')" class="btn-op btn-del">删除</a>
                    <a href="?toggle_section=<?= $section['id'] ?>&hidden=<?= $section['hidden'] ? 0 : 1 ?>#section-<?= $section['id'] ?>" class="btn-op btn-hide">
                        <?= $section['hidden'] ? '显示' : '隐藏' ?>
                    </a>
                </div>
            </form>
            <div style="margin-left:20px;">
                <h4 style="margin:10px 0 6px 0;">菜单项</h4>
                <?php if(!empty($items_by_section[$section['id']])): ?>
                    <?php foreach($items_by_section[$section['id']] as $item): ?>
                        <div class="item" id="item-<?= $item['id'] ?>" style="<?= $item['hidden'] ? 'opacity:0.5;' : '' ?>">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <input type="text" name="item_title" value="<?= htmlspecialchars($item['title']) ?>" required>
                                <input type="text" name="item_desc" value="<?= htmlspecialchars($item['description']) ?>" placeholder="描述">
                                <input type="text" name="item_url" value="<?= htmlspecialchars($item['url']) ?>" placeholder="链接">
                                <input type="file" name="item_icon">
                                <?php if($item['icon']): ?><img src="<?= htmlspecialchars($item['icon']) ?>" alt="icon" class="icon-preview"><?php endif; ?>
                                <input type="hidden" name="old_icon" value="<?= htmlspecialchars($item['icon']) ?>">
                                <div class="op-group">
                                    <button type="submit" name="edit_item" value="1">修改</button>
                                    <a href="?reorder_item=<?= $item['id'] ?>&dir=up#item-<?= $item['id'] ?>" class="btn-op">上移</a>
                                    <a href="?reorder_item=<?= $item['id'] ?>&dir=down#item-<?= $item['id'] ?>" class="btn-op">下移</a>
                                    <button type="button" id="move-btn-<?= $item['id'] ?>" onclick="showMoveSelect(<?= $item['id'] ?>)" class="btn-op">移动</button>
                                    <span id="move-select-<?= $item['id'] ?>" style="display:none;align-items:center;gap:4px;">
                                        <select name="move_to_section">
                                            <?php foreach($sections as $sec): ?>
                                                <option value="<?= $sec['id'] ?>" <?= $sec['id'] == $section['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sec['title']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="move_item" value="1">确定</button>
                                        <button type="button" onclick="cancelMoveSelect(<?= $item['id'] ?>)">取消</button>
                                    </span>
                                    <a href="?del_item=<?= $item['id'] ?>&section=<?= $section['id'] ?>" onclick="return confirm('确定删除该菜单项？')" class="btn-op btn-del">删除</a>
                                    <a href="?toggle_item=<?= $item['id'] ?>&hidden=<?= $item['hidden'] ? 0 : 1 ?>&section=<?= $section['id'] ?>#item-<?= $item['id'] ?>" class="btn-op btn-hide">
                                        <?= $item['hidden'] ? '显示' : '隐藏' ?>
                                    </a>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" style="margin-top:10px;gap:8px;display:flex;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="item_section_id" value="<?= $section['id'] ?>">
                    <input type="text" name="item_title" placeholder="菜单项标题" required>
                    <input type="text" name="item_desc" placeholder="描述">
                    <input type="text" name="item_url" placeholder="链接">
                    <input type="file" name="item_icon">
                    <button type="submit" name="add_item" value="1">添加菜单项</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html> 