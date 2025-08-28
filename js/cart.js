// js/cart.js - Cart functionality

// Helper function to get correct API path
function getApiPath() {
    // Check if we're in a subdirectory (like shop/, customer/, etc.)
    const currentPath = window.location.pathname;
    if (currentPath.includes('/shop/') || currentPath.includes('/customer/') || 
        currentPath.includes('/trader/') || currentPath.includes('/admin/') ||
        currentPath.includes('/payment/') || currentPath.includes('/auth/')) {
        return '../api/';
    }
    return 'api/';
}

// Cart operations
const Cart = {
    // Add item to cart
    addItem: function(productId, quantity = 1, button = null) {
        // Try to get the button from event or parameter
        if (!button && window.event && window.event.target) {
            button = window.event.target;
        }
        
        const stopLoading = button ? showLoading(button, 'Adding...') : () => {};
        
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        fetch(getApiPath() + 'cart_actions.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get as text first
        })
        .then(text => {
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid response');
            }
            
            stopLoading();
            
            if (data.success) {
                this.updateCartCount(data.cart_count);
                showNotification('Product added to cart!', 'success');
                
                // Update button text temporarily (if button exists)
                if (button) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i> Added!';
                    button.classList.add('btn-success');
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.classList.remove('btn-success');
                    }, 2000);
                }
            } else {
                showNotification(data.message || 'Failed to add item to cart', 'error');
            }
        })
        .catch(error => {
            stopLoading();
            console.error('Error:', error);
            showNotification('Something went wrong. Please try again.', 'error');
        });
    },
    
    // Update item quantity
    updateQuantity: function(productId, quantity) {
        if (quantity < 1) {
            this.removeItem(productId);
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        
        fetch(getApiPath() + 'cart_actions.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateCartDisplay(data);
                showNotification('Cart updated!', 'success');
            } else {
                showNotification(data.message || 'Failed to update cart', 'error');
                // Revert quantity input
                const quantityInput = document.querySelector(`input[data-product-id="${productId}"]`);
                if (quantityInput) {
                    quantityInput.value = quantityInput.getAttribute('data-original-value');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Something went wrong. Please try again.', 'error');
        });
    },
    
    // Remove item from cart
    removeItem: function(productId) {
        confirmAction('Are you sure you want to remove this item from your cart?', () => {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('product_id', productId);
            
            fetch(getApiPath() + 'cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the cart item element
                    const cartItem = document.querySelector(`[data-cart-item="${productId}"]`);
                    if (cartItem) {
                        cartItem.style.transition = 'opacity 0.3s, transform 0.3s';
                        cartItem.style.opacity = '0';
                        cartItem.style.transform = 'translateX(-100%)';
                        setTimeout(() => cartItem.remove(), 300);
                    }
                    
                    this.updateCartDisplay(data);
                    showNotification('Item removed from cart', 'info');
                } else {
                    showNotification(data.message || 'Failed to remove item', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong. Please try again.', 'error');
            });
        });
    },
    
    // Clear entire cart
    clearCart: function() {
        confirmAction('Are you sure you want to clear your entire cart?', () => {
            const formData = new FormData();
            formData.append('action', 'clear');
            
            fetch(getApiPath() + 'cart_actions.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove all cart items
                    const cartItems = document.querySelectorAll('[data-cart-item]');
                    cartItems.forEach(item => {
                        item.style.transition = 'opacity 0.3s';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    });
                    
                    this.updateCartDisplay(data);
                    showNotification('Cart cleared!', 'info');
                    
                    // Show empty cart message
                    setTimeout(() => {
                        this.showEmptyCart();
                    }, 400);
                } else {
                    showNotification(data.message || 'Failed to clear cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Something went wrong. Please try again.', 'error');
            });
        });
    },
    
    // Update cart count in header
    updateCartCount: function(count) {
        const cartCountElements = document.querySelectorAll('.cart-count, #cartCount, #cartCountLoggedIn, #cartCountGuest');
        cartCountElements.forEach(element => {
            element.textContent = count || 0;
            
            // Add animation
            element.style.transform = 'scale(1.3)';
            element.style.transition = 'transform 0.2s';
            setTimeout(() => {
                element.style.transform = 'scale(1)';
            }, 200);
        });
    },
    
    // Update cart display (totals, counts, etc.)
    updateCartDisplay: function(data) {
        this.updateCartCount(data.cart_count);
        
        // Update subtotals
        if (data.items) {
            data.items.forEach(item => {
                const subtotalElement = document.querySelector(`[data-subtotal="${item.product_id}"]`);
                if (subtotalElement) {
                    subtotalElement.textContent = formatPrice(item.subtotal);
                }
            });
        }
        
        // Update cart total
        const totalElements = document.querySelectorAll('.cart-total, #cartTotal');
        totalElements.forEach(element => {
            element.textContent = formatPrice(data.cart_total || 0);
        });
        
        // Update item counts
        if (data.items) {
            data.items.forEach(item => {
                const quantityInput = document.querySelector(`input[data-product-id="${item.product_id}"]`);
                if (quantityInput) {
                    quantityInput.value = item.quantity;
                    quantityInput.setAttribute('data-original-value', item.quantity);
                }
            });
        }
    },
    
    // Show empty cart message
    showEmptyCart: function() {
        const cartContainer = document.querySelector('.cart-items, .cart-container');
        if (cartContainer) {
            cartContainer.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p>Add some products to get started!</p>
                                            <a href="../index.php" class="btn-primary">Continue Shopping</a>
                </div>
            `;
        }
    },
    
    // Apply promo code
    applyPromoCode: function() {
        const promoInput = document.querySelector('#promoCode');
        const promoButton = document.querySelector('#applyPromoBtn');
        
        if (!promoInput || !promoInput.value.trim()) {
            showNotification('Please enter a promo code', 'warning');
            return;
        }
        
        const stopLoading = showLoading(promoButton, 'Applying...');
        
        const formData = new FormData();
        formData.append('action', 'apply_promo');
        formData.append('promo_code', promoInput.value.trim());
        
        fetch(getApiPath() + 'cart_actions.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            stopLoading();
            
            if (data.success) {
                showNotification('Promo code applied successfully!', 'success');
                this.updateCartDisplay(data);
                
                // Show discount info
                const discountElement = document.querySelector('#discountAmount');
                if (discountElement && data.discount_amount) {
                    discountElement.textContent = '-' + formatPrice(data.discount_amount);
                    discountElement.parentElement.style.display = 'block';
                }
                
                // Disable promo input
                promoInput.disabled = true;
                promoButton.disabled = true;
                promoButton.innerHTML = '<i class="fas fa-check"></i> Applied';
            } else {
                showNotification(data.message || 'Invalid promo code', 'error');
            }
        })
        .catch(error => {
            stopLoading();
            console.error('Error:', error);
            showNotification('Something went wrong. Please try again.', 'error');
        });
    },
    
    // Remove promo code
    removePromoCode: function() {
        const formData = new FormData();
        formData.append('action', 'remove_promo');
        
        fetch(getApiPath() + 'cart_actions.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Promo code removed', 'info');
                this.updateCartDisplay(data);
                
                // Hide discount info
                const discountElement = document.querySelector('#discountAmount');
                if (discountElement) {
                    discountElement.parentElement.style.display = 'none';
                }
                
                // Re-enable promo input
                const promoInput = document.querySelector('#promoCode');
                const promoButton = document.querySelector('#applyPromoBtn');
                if (promoInput && promoButton) {
                    promoInput.disabled = false;
                    promoInput.value = '';
                    promoButton.disabled = false;
                    promoButton.innerHTML = 'Apply';
                }
            } else {
                showNotification(data.message || 'Failed to remove promo code', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Something went wrong. Please try again.', 'error');
        });
    }
};

// Initialize cart functionality when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCartEvents();
    loadCartCount();
});

// Initialize cart event listeners
function initializeCartEvents() {
    // Quantity input changes (with debouncing)
    const quantityInputs = document.querySelectorAll('input[data-product-id]');
    quantityInputs.forEach(input => {
        let timeoutId;
        
        input.addEventListener('input', function() {
            clearTimeout(timeoutId);
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(this.value);
            
            // Store original value for reverting on error
            if (!this.hasAttribute('data-original-value')) {
                this.setAttribute('data-original-value', this.value);
            }
            
            if (quantity >= 1) {
                timeoutId = setTimeout(() => {
                    Cart.updateQuantity(productId, quantity);
                }, 500); // 500ms delay
            }
        });
        
        // Prevent negative values
        input.addEventListener('keydown', function(e) {
            if (e.key === '-' || e.key === '+' || e.key === 'e') {
                e.preventDefault();
            }
        });
    });
    
    // Plus/minus buttons for quantity
    const plusButtons = document.querySelectorAll('.quantity-plus');
    const minusButtons = document.querySelectorAll('.quantity-minus');
    
    plusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const currentValue = parseInt(input.value) || 0;
            input.value = currentValue + 1;
            
            const productId = input.getAttribute('data-product-id');
            Cart.updateQuantity(productId, currentValue + 1);
        });
    });
    
    minusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            const currentValue = parseInt(input.value) || 0;
            
            if (currentValue > 1) {
                input.value = currentValue - 1;
                const productId = input.getAttribute('data-product-id');
                Cart.updateQuantity(productId, currentValue - 1);
            }
        });
    });
    
    // Remove item buttons
    const removeButtons = document.querySelectorAll('.remove-item');
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            Cart.removeItem(productId);
        });
    });
    
    // Clear cart button
    const clearCartButton = document.querySelector('#clearCartBtn');
    if (clearCartButton) {
        clearCartButton.addEventListener('click', function() {
            Cart.clearCart();
        });
    }
    
    // Promo code functionality
    const applyPromoButton = document.querySelector('#applyPromoBtn');
    if (applyPromoButton) {
        applyPromoButton.addEventListener('click', function() {
            Cart.applyPromoCode();
        });
    }
    
    const promoInput = document.querySelector('#promoCode');
    if (promoInput) {
        promoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                Cart.applyPromoCode();
            }
        });
    }
    
    const removePromoButton = document.querySelector('#removePromoBtn');
    if (removePromoButton) {
        removePromoButton.addEventListener('click', function() {
            Cart.removePromoCode();
        });
    }
}

// Load current cart count
function loadCartCount() {
    fetch(getApiPath() + 'cart_actions.php?action=get_count', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid response');
        }
        
        if (data.success) {
            Cart.updateCartCount(data.cart_count);
        }
    })
    .catch(error => {
        console.error('Error loading cart count:', error);
    });
}

// Global function for adding to cart (called from product cards)
function addToCart(productId, quantity = 1) {
    // Try to get the button that was clicked
    let button = null;
    if (window.event && window.event.target) {
        button = window.event.target;
        // If it's an icon inside the button, get the parent button
        if (button.tagName === 'I' && button.parentElement.tagName === 'BUTTON') {
            button = button.parentElement;
        }
    }
    
    Cart.addItem(productId, quantity, button);
}

// Global function for updating cart quantity
function updateCartQuantity(productId, quantity) {
    Cart.updateQuantity(productId, quantity);
}

// Global function for removing from cart
function removeFromCart(productId) {
    Cart.removeItem(productId);
}

// Quick add to cart with quantity selector
function quickAddToCart(productId) {
    const quantity = prompt('Enter quantity:', '1');
    if (quantity && parseInt(quantity) > 0) {
        Cart.addItem(productId, parseInt(quantity));
    }
}

// Add to wishlist (placeholder for future implementation)
function addToWishlist(productId) {
    showNotification('Wishlist feature coming soon!', 'info');
}

// Export cart functions for global access
window.Cart = Cart;
window.addToCart = addToCart;
window.updateCartQuantity = updateCartQuantity;
window.removeFromCart = removeFromCart;
window.quickAddToCart = quickAddToCart;
window.addToWishlist = addToWishlist;