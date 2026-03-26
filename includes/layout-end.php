                </div>
            </section>
        </main>
    </div>

    <?php require __DIR__ . '/footer.php'; ?>
</div>
<?php if (!empty($pageScripts)): ?>
    <?php foreach ($pageScripts as $scriptPath): ?>
        <script src="<?php echo htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>