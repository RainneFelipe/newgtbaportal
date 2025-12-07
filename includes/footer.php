            </div>
        </main>
    </div>
    <!-- End Main Wrapper -->
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Golden Treasure Baptist Academy. All rights reserved.</p>
            <p>Student Portal System</p>
        </div>
    </footer>
    
    <script>
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarTrigger = document.getElementById('sidebarTrigger');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const mainWrapper = document.getElementById('mainWrapper');

            function openSidebar() {
                sidebar?.classList.add('active');
                sidebarOverlay?.classList.add('active');
                document.body.classList.add('sidebar-open');
            }

            function closeSidebar() {
                sidebar?.classList.remove('active');
                sidebarOverlay?.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }

            // Event listeners
            sidebarTrigger?.addEventListener('click', openSidebar);
            sidebarToggle?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar?.classList.contains('active')) {
                    closeSidebar();
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar?.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });

        // Common JavaScript functions
        function showAlert(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            const container = document.querySelector('.container');
            container.insertBefore(alert, container.firstChild);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
    
    <?php
    // Show privacy policy modal if not accepted
    if (isset($_SESSION['privacy_policy_accepted']) && $_SESSION['privacy_policy_accepted'] == 0) {
        include __DIR__ . '/privacy_policy_modal.php';
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('privacyPolicyModal').style.display = 'flex';
            });
        </script>";
    }
    ?>
</body>
</html>
