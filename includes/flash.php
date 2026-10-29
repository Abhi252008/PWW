<?php
/**
 * Renders (and clears) any pending flash message. Include this once,
 * right after includes/header.php, on every page.
 */
$flash = get_flash();
if ($flash):
    $iconMap = [
        'success' => 'bi-check-circle',
        'error'   => 'bi-exclamation-triangle',
        'info'    => 'bi-info-circle',
    ];
    $icon = $iconMap[$flash['type']] ?? 'bi-info-circle';
?>
<div class="flash-toast flash-<?= e($flash['type']) ?> glass" role="alert">
  <i class="bi <?= $icon ?>"></i>
  <span><?= e($flash['message']) ?></span>
  <button type="button" class="flash-close" aria-label="Close notification" title="Close">&times;</button>
</div>
<script>
(function() {
  function initFlashToasts() {
    var toasts = document.querySelectorAll('.flash-toast:not([data-dismiss-init])');
    toasts.forEach(function(toast) {
      toast.setAttribute('data-dismiss-init', 'true');
      var DURATION = 4500; // Auto-dismiss after 4.5 seconds
      var timer = null;

      function dismiss() {
        if (timer) {
          clearTimeout(timer);
          timer = null;
        }
        toast.classList.add('flash-hide');
        setTimeout(function() {
          if (toast && toast.parentNode) {
            toast.remove();
          }
        }, 380);
      }

      function startTimer() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(dismiss, DURATION);
      }

      var closeBtn = toast.querySelector('.flash-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          dismiss();
        });
      }

      // Pause timer when hovered so user can finish reading
      toast.addEventListener('mouseenter', function() {
        if (timer) {
          clearTimeout(timer);
          timer = null;
        }
      });
      toast.addEventListener('mouseleave', function() {
        startTimer();
      });

      startTimer();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFlashToasts);
  } else {
    initFlashToasts();
  }
})();
</script>
<?php endif; ?>

