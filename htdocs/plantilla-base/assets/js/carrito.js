/**
 * Sistema de Carrito de Compras
 * Usa localStorage para persistencia, sincronizado con el slug del emprendimiento
 */

function getSlug() {
    const path = window.location.pathname;
    const parts = path.split('/').filter(p => p);
    return parts[0] || '';
}

function getCarrito() {
    const slug = getSlug();
    return JSON.parse(localStorage.getItem('carrito_' + slug) || '[]');
}

function guardarCarrito(carrito) {
    const slug = getSlug();
    localStorage.setItem('carrito_' + slug, JSON.stringify(carrito));
    actualizarContadorCarrito();
}

function actualizarContadorCarrito() {
    const carrito = getCarrito();
    const total = carrito.reduce((sum, item) => sum + item.cantidad, 0);
    document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = total;
        el.style.display = total > 0 ? 'flex' : 'none';
    });
}

function agregarAlCarrito(producto) {
    const carrito = getCarrito();
    const existing = carrito.find(item => item.id === producto.id);
    
    if (existing) {
        existing.cantidad = Math.min(existing.cantidad + 1, existing.stock);
    } else {
        carrito.push({
            id: producto.id,
            nombre: producto.nombre,
            precio: parseFloat(producto.precio),
            imagen: producto.imagen || '',
            stock: producto.stock || 0,
            cantidad: 1
        });
    }
    
    guardarCarrito(carrito);
    mostrarNotificacion('✓ Producto agregado al carrito');
}

function eliminarDelCarrito(productoId) {
    let carrito = getCarrito();
    carrito = carrito.filter(item => item.id !== productoId);
    guardarCarrito(carrito);
    renderizarCarrito();
}

function actualizarCantidad(productoId, nuevaCantidad) {
    const carrito = getCarrito();
    const item = carrito.find(item => item.id === productoId);
    if (item) {
        item.cantidad = Math.max(1, Math.min(nuevaCantidad, item.stock));
        guardarCarrito(carrito);
        renderizarCarrito();
    }
}

function calcularTotal() {
    const carrito = getCarrito();
    return carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
}

function vaciarCarrito() {
    if (confirm('¿Vaciar el carrito?')) {
        guardarCarrito([]);
        renderizarCarrito();
    }
}

function renderizarCarrito() {
    const container = document.getElementById('carritoContainer');
    const totalEl = document.getElementById('totalCarrito');
    if (!container) return;

    const carrito = getCarrito();

    if (carrito.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h5>Tu carrito está vacío</h5>
                <p class="text-muted">Explora nuestros productos y agrega lo que te guste</p>
                <a href="productos.php" class="btn btn-primary"><i class="fas fa-arrow-left me-2"></i> Ver Productos</a>
            </div>`;
        if (totalEl) totalEl.textContent = '$0.00';
        return;
    }

    let html = `<table class="table table-cart">
        <thead><tr>
            <th>Producto</th>
            <th>Precio</th>
            <th>Cantidad</th>
            <th>Subtotal</th>
            <th></th>
        </tr></thead><tbody>`;

    carrito.forEach(item => {
        const subtotal = item.precio * item.cantidad;
        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        ${item.imagen ? `<img src="uploads/${item.imagen}" class="rounded" style="width:50px;height:50px;object-fit:cover">` : 
                        `<div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:50px;height:50px"><i class="fas fa-box text-muted"></i></div>`}
                        <div>
                            <strong>${item.nombre}</strong>
                            <br><small class="text-muted">Stock: ${item.stock}</small>
                        </div>
                    </div>
                </td>
                <td class="fw-semibold">$${item.precio.toFixed(2)}</td>
                <td>
                    <div class="input-group input-group-sm" style="width:110px">
                        <button class="btn btn-outline-secondary" onclick="actualizarCantidad(${item.id}, ${item.cantidad - 1})">-</button>
                        <input type="number" class="form-control text-center" value="${item.cantidad}" min="1" max="${item.stock}" 
                               onchange="actualizarCantidad(${item.id}, parseInt(this.value))">
                        <button class="btn btn-outline-secondary" onclick="actualizarCantidad(${item.id}, ${item.cantidad + 1})">+</button>
                    </div>
                </td>
                <td class="fw-bold">$${subtotal.toFixed(2)}</td>
                <td>
                    <button class="btn btn-outline-danger btn-sm" onclick="eliminarDelCarrito(${item.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    });

    html += '</tbody></table>';
    container.innerHTML = html;

    if (totalEl) {
        totalEl.textContent = '$' + calcularTotal().toFixed(2);
    }
}

function mostrarNotificacion(msg) {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
}

// Inicializar contadores y bindeos
document.addEventListener('DOMContentLoaded', function() {
    actualizarContadorCarrito();
    renderizarCarrito();

    // Bindeo de botones "Agregar al Carrito"
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            agregarAlCarrito({
                id: parseInt(this.dataset.id),
                nombre: this.dataset.nombre,
                precio: parseFloat(this.dataset.precio),
                imagen: this.dataset.imagen || '',
                stock: parseInt(this.dataset.stock)
            });
        });
    });
});
