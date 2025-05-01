</main>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
    <script src="../assets/js/sidebar.js"></script>

    <?php if (isset($page_specific_js)): ?>
        <script src="<?php echo $page_specific_js; ?>"></script>
    <?php endif; ?>
</body>
</html>