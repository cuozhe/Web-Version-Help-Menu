<?php
require_once 'admin/db.php';
// 只读取未隐藏的板块和菜单项
$setting = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch();
$sections = $pdo->query('SELECT * FROM sections WHERE hidden=0 ORDER BY sort_order, id')->fetchAll();
$items = $pdo->query('SELECT * FROM items WHERE hidden=0 ORDER BY section_id, sort_order, id')->fetchAll();
// 按板块分组菜单项
$items_by_section = [];
foreach ($items as $item) {
    $items_by_section[$item['section_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($setting['site_title']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', Arial, sans-serif;
<?php if($setting['background']): ?>
            background-image: url('<?= htmlspecialchars($setting['background']) ?>');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
<?php endif; ?>
        }
        .main-box {
            max-width: 900px;
            margin: 40px auto;
            padding: 32px 36px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px #0001;
            opacity: <?= isset($setting['card_opacity']) ? $setting['card_opacity'] : 1 ?>;
        }
        .header { text-align: center; margin-bottom: 32px; }
        .logo { width: 90px; height: 90px; border-radius: 50%; margin-bottom: 10px; }
        h1 { color: #2d3a4b; margin-bottom: 8px; }
        .section { background: #f7fafd; border-radius: 12px; padding: 18px 20px; margin-bottom: 28px; box-shadow: 0 2px 8px #0001; }
        .section h2 { color: #2196f3; margin-bottom: 16px; }
        .items { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 18px; justify-content: center; }
        .item { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px #0001; padding: 16px 18px; min-width: 180px; max-width: 260px; text-align: center; display: flex; flex-direction: column; align-items: center; }
        .item img { width: 36px; height: 36px; border-radius: 6px; margin-bottom: 8px; }
        .item strong { font-size: 17px; color: #333; margin-bottom: 4px; }
        .item span { color: #888; font-size: 14px; margin-bottom: 6px; }
        .item a { color: #2196f3; text-decoration: none; font-size: 15px; margin-top: 4px; }
        .item a:hover { text-decoration: underline; }
        .empty { color: #bbb; text-align: center; margin: 30px 0; }
        footer {
            text-align: center;
            margin: 30px 0;
            color: #888;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0001;
            opacity: <?= isset($setting['card_opacity']) ? $setting['card_opacity'] : 1 ?>;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            padding: 10px 0;
        }
        @media (max-width: 600px) { .main-box { padding: 10px; } .section { padding: 8px; } .items { gap: 8px; } }
    </style>
</head>
<body>
<div class="main-box">
    <div class="header">
        <?php if($setting['logo']): ?>
            <img src="<?= htmlspecialchars($setting['logo']) ?>" alt="LOGO" class="logo">
        <?php endif; ?>
        <h1><?= htmlspecialchars($setting['site_title']) ?></h1>
    </div>
    <?php if(empty($sections)): ?>
        <div class="empty">暂无板块内容，请登录后台添加。</div>
    <?php endif; ?>
    <?php foreach($sections as $section): ?>
        <div class="section">
            <h2><?= htmlspecialchars($section['title']) ?></h2>
            <div class="items">
            <?php if(!empty($items_by_section[$section['id']])): ?>
                <?php foreach($items_by_section[$section['id']] as $item): ?>
                    <div class="item">
                        <?php if($item['icon']): ?><img src="<?= htmlspecialchars($item['icon']) ?>" alt="icon"><?php endif; ?>
                        <strong><?= htmlspecialchars($item['title']) ?></strong>
                        <?php if($item['description']): ?><span><?= htmlspecialchars($item['description']) ?></span><?php endif; ?>
                        <?php if($item['url']): ?><a href="<?= htmlspecialchars($item['url']) ?>" target="_blank">访问</a><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">该板块暂无菜单项</div>
            <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <footer>Created By SUOYI-1.1.0 & SEC-Plugin</footer>
</div>
</body>
</html> 