/**
 * Chatbot Widget JavaScript
 * Comunicación con DeepSeek 7B via Ollama
 */

let chatHistory = [];
let isProcessing = false;

function getSlug() {
    if (typeof CHATBOT_SLUG !== 'undefined') return CHATBOT_SLUG;
    const parts = window.location.pathname.split('/').filter(p => p);
    return parts[0] || '';
}

function enviarMensaje() {
    const input = document.getElementById('chatbotInput');
    const message = input.value.trim();
    
    if (!message || isProcessing) return;
    
    input.value = '';
    isProcessing = true;
    
    // Agregar mensaje del usuario al chat
    agregarMensaje(message, 'user');
    
    // Agregar a historial
    chatHistory.push({ role: 'user', content: message });
    
    // Mostrar indicador de escritura
    mostrarTyping();
    
    // Llamar a la API
    fetch('/chatbot-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: message,
            history: chatHistory.slice(-10), // Últimos 10 mensajes
            slug: getSlug()
        })
    })
    .then(response => response.json())
    .then(data => {
        ocultarTyping();
        
        if (data.success && data.response) {
            // Agregar respuesta del bot
            agregarMensaje(data.response, 'bot');
            chatHistory.push({ role: 'assistant', content: data.response });
            
            // Si es respuesta fallback, agregar enlace WhatsApp
            if (data.fallback) {
                // El enlace ya viene en el HTML de la respuesta
            }
        } else {
            agregarMensaje('Lo siento, hubo un error. Por favor intenta de nuevo.', 'bot');
        }
    })
    .catch(error => {
        ocultarTyping();
        agregarMensaje('Error de conexión. Por favor intenta de nuevo.', 'bot');
        console.error('Chatbot error:', error);
    })
    .finally(() => {
        isProcessing = false;
    });
}

function agregarMensaje(texto, tipo) {
    const container = document.getElementById('chatbotMessages');
    const msgDiv = document.createElement('div');
    msgDiv.className = tipo === 'user' ? 'chat-msg-user' : 'chat-msg-bot';
    msgDiv.innerHTML = texto.replace(/\n/g, '<br>');
    container.appendChild(msgDiv);
    container.scrollTop = container.scrollHeight;
}

function mostrarTyping() {
    const container = document.getElementById('chatbotMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-typing';
    typingDiv.id = 'chatTypingIndicator';
    typingDiv.innerHTML = '<span></span><span></span><span></span>';
    container.appendChild(typingDiv);
    container.scrollTop = container.scrollHeight;
}

function ocultarTyping() {
    const typing = document.getElementById('chatTypingIndicator');
    if (typing) typing.remove();
}

// Abrir chat si hay un hash #chat en la URL
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#chat') {
        setTimeout(() => toggleChatbot(), 500);
    }

    // Auto-abrir después de 30 segundos si no se ha interactuado
    let chatbotOpened = false;
    setTimeout(() => {
        if (!chatbotOpened && !document.getElementById('chatbotWidget').classList.contains('open')) {
            // Solo mostrar el toggle con animación
            const toggle = document.getElementById('chatbotToggle');
            if (toggle) {
                toggle.style.animation = 'pulse 2s infinite';
            }
        }
    }, 30000);

    document.addEventListener('click', function() {
        chatbotOpened = true;
    }, { once: true });
});
