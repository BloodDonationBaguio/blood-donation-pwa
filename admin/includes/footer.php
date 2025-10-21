            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 text-center">
                    <span class="text-muted">
                        &copy; <?= date('Y') ?> Blood Donation System. All rights reserved.
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom Scripts -->
    <script src="/assets/js/admin.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (file_exists(__DIR__ . "/../assets/js/pages/$page.js")): ?>
        <script src="/assets/js/pages/<?= $page ?>.js"></script>
    <?php endif; ?>
    
</body>
</html>
