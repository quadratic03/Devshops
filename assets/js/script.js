// DevMarket Philippines Custom JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Product detail modal
    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const productId = button.getAttribute('data-product-id');
            const productTitle = button.getAttribute('data-product-title');
            const productPrice = button.getAttribute('data-product-price');
            const productDescription = button.getAttribute('data-product-description');
            const sellerPhone = button.getAttribute('data-seller-phone');
            
            // Update modal content
            const modalTitle = productModal.querySelector('.modal-title');
            const modalPrice = productModal.querySelector('.modal-price');
            const modalDescription = productModal.querySelector('.modal-description');
            const modalPhone = productModal.querySelector('.modal-phone');
            const messengerLink = productModal.querySelector('.messenger-link');
            
            if (modalTitle) modalTitle.textContent = productTitle;
            if (modalPrice) modalPrice.textContent = productPrice;
            if (modalDescription) modalDescription.textContent = productDescription;
            if (modalPhone) modalPhone.textContent = sellerPhone;
            
            // Update messenger link (example implementation)
            if (messengerLink) {
                const sellerUsername = button.getAttribute('data-seller-username');
                messengerLink.href = `https://m.me/${sellerUsername}`;
            }
        });
    }
    
    // File upload preview
    const fileInput = document.getElementById('product-image');
    const previewContainer = document.getElementById('image-preview-container');
    const previewImage = document.getElementById('image-preview');
    
    if (fileInput && previewContainer && previewImage) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                previewContainer.style.display = 'block';
                
                reader.addEventListener('load', function() {
                    previewImage.setAttribute('src', this.result);
                });
                
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                previewImage.setAttribute('src', '');
            }
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Price range filter
    const priceRange = document.getElementById('price-range');
    const priceValue = document.getElementById('price-value');
    
    if (priceRange && priceValue) {
        priceRange.addEventListener('input', function() {
            priceValue.textContent = this.value;
        });
    }
}); 