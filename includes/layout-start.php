<?php require __DIR__ . '/head.php'; ?>
<?php $appClass = empty($hideSidebar) ? 'app' : 'app app--no-sidebar'; ?>
<div class="app-shell">
    <div class="<?php echo $appClass; ?>" data-app-shell>
        <?php if (empty($hideSidebar)): ?>
            <?php require __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>

        <?php
            // if the sidebar is hidden we must remove any left margin/space so main uses full width
            $mainStyle = empty($hideSidebar) ? '' : 'style="margin-left:0; width:100%;"';
            $containerStyle = empty($hideSidebar) ? 'padding: 0;' : 'padding: 0 16px 48px; max-width: 760px; width: 100%; margin: 0 auto;';
        ?>

        <main class="main" <?php echo $mainStyle; ?>>
            <?php require __DIR__ . '/header.php'; ?>
            <section class="page">
                <div class="container" style="<?php echo $containerStyle; ?>">