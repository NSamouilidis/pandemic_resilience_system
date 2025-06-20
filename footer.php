<footer>
        <p>&copy; 2025 Pandemic Resilience System | Data-driven Systems (5CM506)</p>
    </footer>
    
    <script src="<?php echo $root_path; ?>assets/js/main.js"></script>
    
    <?php if (isset($additional_js)): ?>
        <?php foreach ($additional_js as $js_file): ?>
            <script src="<?php echo $root_path . $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
</body>
</html>