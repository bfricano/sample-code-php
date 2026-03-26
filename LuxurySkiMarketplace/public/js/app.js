/**
 * Kypre - Luxury Ski Marketplace
 * Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Quantity input validation
    document.querySelectorAll('.qty-input, #quantity').forEach(function (input) {
        input.addEventListener('change', function () {
            var min = parseInt(this.min) || 1;
            var max = parseInt(this.max) || 10;
            var val = parseInt(this.value) || min;
            if (val < min) this.value = min;
            if (val > max) this.value = max;
        });
    });

    // Card number formatting (spaces every 4 digits for display)
    var cardInput = document.getElementById('card_number');
    if (cardInput) {
        cardInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 16);
        });
    }

    // CVV input validation
    var cvvInput = document.getElementById('card_cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 4);
        });
    }

    // Remove confirmation
    document.querySelectorAll('.remove-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm('Remove this item from your cart?')) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });
});
