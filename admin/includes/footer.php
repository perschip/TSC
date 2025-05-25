                <!-- End of main content container -->
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- Common Admin Scripts -->
    <script>
    // Mobile sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(event.target) && event.target !== sidebarToggle) {
                sidebar.classList.remove('show');
            }
        });
        
        // Loading overlay functions
        window.showLoading = function() {
            document.getElementById('loading-overlay').classList.add('show');
        };
        
        window.hideLoading = function() {
            document.getElementById('loading-overlay').classList.remove('show');
        };
    });
    </script>
    
    <?php if (isset($use_tinymce) && $use_tinymce): ?>
    <!-- TinyMCE Initialization -->
    <script>
    tinymce.init({
        selector: '.editor',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist mediaembed casechange export formatpainter pageembed linkchecker a11ychecker tinymcespellchecker permanentpen powerpaste advtable advcode editimage advtemplate mentions tableofcontents footnotes mergetags autocorrect typography inlinecss',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
        height: 500
    });
    </script>
    <?php endif; ?>
    
    <?php if (isset($extra_scripts)): ?>
    <!-- Page-specific scripts -->
    <?php echo $extra_scripts; ?>
    <?php endif; ?>
</body>
</html>