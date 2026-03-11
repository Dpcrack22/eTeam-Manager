<?php require __DIR__ . '/app-head.php'; ?>
<?php $appClass = empty($hideSidebar) ? 'app' : 'app app--no-sidebar'; ?>
<div class="<?php echo $appClass; ?>">
    <?php if (empty($hideSidebar)): ?>
        <?php require __DIR__ . '/app-sidebar.php'; ?>
    <?php endif; ?>

    <?php
    // if the sidebar is hidden we must remove any left margin/space so main uses full width
    $mainStyle = empty($hideSidebar) ? '' : 'style="margin-left:0; width:100%;"';
    $containerStyle = empty($hideSidebar) ? 'padding: 0;' : 'padding: 0; max-width: 100%; width: 100%;';
    ?>

    <main class="main" <?php echo $mainStyle; ?> >
        <?php require __DIR__ . '/app-topbar.php'; ?>
        <section class="page">
            <div class="container" style="<?php echo $containerStyle; ?>">