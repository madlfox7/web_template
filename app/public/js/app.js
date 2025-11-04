// Enhanced UX JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
  // Add loading states to forms
  setupFormLoadingStates();
  
  // Add cart animations
  setupCartAnimations();
  
  // Add confirmation dialogs
  setupConfirmationDialogs();
  
  // Add auto-hide for flash messages
  setupAutoHideFlashMessages();
  
  // Add responsive navigation
  setupResponsiveNavigation();
  
  // Add form validation enhancements
  setupFormValidation();
});

// Form loading states
function setupFormLoadingStates() {
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Loading...';
        
        // Re-enable after 5 seconds as fallback
        setTimeout(() => {
          submitBtn.disabled = false;
          submitBtn.classList.remove('loading');
          submitBtn.textContent = originalText;
        }, 5000);
      }
    });
  });
}

// Cart animations
function setupCartAnimations() {
  // Animate cart additions
  document.addEventListener('click', function(e) {
    if (e.target.closest('button[type="submit"]') && 
        e.target.closest('form input[name="action"][value="add"]')) {
      
      const button = e.target.closest('button');
      if (button) {
        button.style.transform = 'scale(0.95)';
        setTimeout(() => {
          button.style.transform = '';
        }, 150);
      }
    }
  });

  // Update cart badge with animation
  function updateCartBadge() {
    const cartLink = document.querySelector('a[href*="cart"]');
    if (cartLink) {
      cartLink.style.transform = 'scale(1.1)';
      setTimeout(() => {
        cartLink.style.transform = '';
      }, 200);
    }
  }
}

// Confirmation dialogs with better UX
function setupConfirmationDialogs() {
  document.addEventListener('click', function(e) {
    const element = e.target.closest('[onsubmit*="confirm"]');
    if (element) {
      const form = e.target.closest('form');
      if (form && form.onsubmit) {
        e.preventDefault();
        
        const message = form.onsubmit.toString().match(/confirm\(['"`]([^'"`]+)['"`]\)/);
        const confirmText = message ? message[1] : 'Are you sure?';
        
        showCustomConfirm(confirmText, () => {
          form.onsubmit = null; // Remove confirmation
          form.submit();
        });
      }
    }
  });
}

// Custom confirmation dialog
function showCustomConfirm(message, callback) {
  const overlay = document.createElement('div');
  overlay.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  `;

  const dialog = document.createElement('div');
  dialog.style.cssText = `
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    max-width: 400px;
    text-align: center;
  `;

  const title = document.createElement('h3');
  title.textContent = 'Confirm Action';
  title.style.cssText = 'margin: 0 0 1rem 0;';

  const messageP = document.createElement('p');
  messageP.textContent = message;
  messageP.style.cssText = 'margin: 0 0 1.5rem 0;';

  const buttonContainer = document.createElement('div');
  buttonContainer.style.cssText = 'display: flex; gap: 1rem; justify-content: center;';

  const cancelBtn = document.createElement('button');
  cancelBtn.className = 'btn secondary';
  cancelBtn.textContent = 'Cancel';
  cancelBtn.onclick = () => overlay.remove();

  const confirmBtn = document.createElement('button');
  confirmBtn.className = 'btn danger';
  confirmBtn.textContent = 'Confirm';
  confirmBtn.onclick = () => {
    overlay.remove();
    callback();
  };

  buttonContainer.appendChild(cancelBtn);
  buttonContainer.appendChild(confirmBtn);
  dialog.appendChild(title);
  dialog.appendChild(messageP);
  dialog.appendChild(buttonContainer);

  overlay.className = 'overlay';
  overlay.appendChild(dialog);
  document.body.appendChild(overlay);

  // Close on outside click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      overlay.remove();
    }
  });
}

// Auto-hide flash messages
function setupAutoHideFlashMessages() {
  document.querySelectorAll('.flash').forEach(flash => {
    if (!flash.classList.contains('error')) {
      setTimeout(() => {
        flash.style.opacity = '0';
        flash.style.transform = 'translateY(-10px)';
        setTimeout(() => flash.remove(), 300);
      }, 5000);
    }
  });
}

// Responsive navigation
function setupResponsiveNavigation() {
  // Add mobile menu toggle if needed
  const nav = document.querySelector('.topbar nav');
  if (nav && window.innerWidth <= 768) {
    nav.style.flexWrap = 'wrap';
  }
}

// Enhanced form validation
function setupFormValidation() {
  // Real-time validation feedback
  document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
      const value = parseInt(this.value);
      const min = parseInt(this.min);
      const max = parseInt(this.max);
      
      this.style.borderColor = '';
      
      if (value < min || value > max) {
        this.style.borderColor = 'var(--danger)';
      } else {
        this.style.borderColor = 'var(--success)';
      }
    });
  });

  // Price validation
  document.querySelectorAll('input[name="price"]').forEach(input => {
    input.addEventListener('input', function() {
      const value = parseFloat(this.value);
      
      if (value <= 0) {
        this.style.borderColor = 'var(--danger)';
      } else if (value > 99999.99) {
        this.style.borderColor = 'var(--warning)';
      } else {
        this.style.borderColor = 'var(--success)';
      }
    });
  });
}

// Utility functions
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `flash ${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    min-width: 300px;
    animation: slideIn 0.3s ease-out;
  `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100%)';
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

// Smooth scrolling for anchor links
document.addEventListener('click', function(e) {
  const link = e.target.closest('a[href^="#"]');
  if (link) {
    e.preventDefault();
    const target = document.querySelector(link.getAttribute('href'));
    if (target) {
      target.scrollIntoView({ behavior: 'smooth' });
    }
  }
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  
  .loading {
    position: relative;
    overflow: hidden;
  }
  
  .loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: loading 1.5s infinite;
  }
  
  @keyframes loading {
    0% { left: -100%; }
    100% { left: 100%; }
  }
`;
document.head.appendChild(style);
