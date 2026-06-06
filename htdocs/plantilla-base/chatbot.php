<?php
/**
 * Widget de chatbot offline con DeepSeek 7B via Ollama
 * Se incluye en todas las páginas del emprendimiento
 */
$chatbotSlug = $slug ?? basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<!-- Chatbot Widget -->
<div class="chatbot-widget" id="chatbotWidget">
    <div class="chatbot-header">
        <div>
            <i class="fas fa-robot me-2"></i>
            <h6 class="d-inline">Asistente IA</h6>
        </div>
        <button class="close-chat" onclick="toggleChatbot()">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbotMessages">
        <div class="chat-msg-bot">
            ¡Hola! Soy el asistente virtual. Pregúntame sobre productos, stock, precios, formas de pago y más.
        </div>
    </div>
    <div class="chatbot-input">
        <input type="text" id="chatbotInput" placeholder="Escribe tu mensaje..." onkeypress="if(event.key==='Enter') enviarMensaje()">
        <button onclick="enviarMensaje()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<button class="chatbot-toggle show" id="chatbotToggle" onclick="toggleChatbot()" title="Abrir chat IA">
    <i class="fas fa-comment-dots"></i>
</button>

<script>
const CHATBOT_SLUG = '<?= $chatbotSlug ?>';

function toggleChatbot() {
    const widget = document.getElementById('chatbotWidget');
    const toggle = document.getElementById('chatbotToggle');
    widget.classList.toggle('open');
    toggle.classList.toggle('show');
    
    if (widget.classList.contains('open')) {
        setTimeout(() => {
            document.getElementById('chatbotInput')?.focus();
        }, 300);
    }
}
</script>
