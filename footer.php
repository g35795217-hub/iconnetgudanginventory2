  </main>
</div>
<dialog id="fd" class="mb"><h3>Detail</h3><dl id="fdl" class="dl"></dl><div class="mf"><button type="button" class="btn y" onclick="this.closest('dialog').close()">Tutup</button></div></dialog>
<?php if (!empty($charts)): ?><script src="assets/vendor/chart.umd.min.js" defer></script><?php endif; ?>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>" defer></script>
</body>
</html>
