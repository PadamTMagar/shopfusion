(function () {
    function parseConfig(element) {
        try {
            return JSON.parse(element.getAttribute('data-config') || '{}');
        } catch (error) {
            return {};
        }
    }

    function createMessage(role, text) {
        const row = document.createElement('div');
        row.className = 'ai-chat-message ai-chat-message-' + role;

        const bubble = document.createElement('div');
        bubble.className = 'ai-chat-bubble';
        bubble.textContent = text;

        row.appendChild(bubble);
        return row;
    }

    function createTypingIndicator() {
        const row = document.createElement('div');
        row.className = 'ai-chat-message ai-chat-message-bot ai-chat-message-typing';
        row.innerHTML = '<div class="ai-chat-bubble"><span></span><span></span><span></span></div>';
        return row;
    }

    function setLoading(panel, isLoading) {
        const button = panel.querySelector('[data-ai-chat-send]');
        const status = panel.querySelector('[data-ai-chat-status]');

        if (!button || !status) {
            return;
        }

        button.disabled = isLoading;
        button.textContent = isLoading ? 'Sending...' : 'Send';
        status.textContent = isLoading ? 'Assistant is thinking...' : 'Ask about shipping, payments, returns, or this product.';
    }

    function appendMessage(panel, role, text) {
        const body = panel.querySelector('[data-ai-chat-messages]');
        if (!body) {
            return;
        }

        body.appendChild(createMessage(role, text));
        body.scrollTop = body.scrollHeight;
    }

    function setTyping(panel, isTyping) {
        const body = panel.querySelector('[data-ai-chat-messages]');
        if (!body) {
            return;
        }

        const existing = body.querySelector('.ai-chat-message-typing');
        if (isTyping) {
            if (!existing) {
                body.appendChild(createTypingIndicator());
                body.scrollTop = body.scrollHeight;
            }
            return;
        }

        if (existing) {
            existing.remove();
        }
    }

    function appendSuggestions(panel, suggestions, input) {
        const wrap = panel.querySelector('[data-ai-chat-suggestions]');
        if (!wrap || !Array.isArray(suggestions)) {
            return;
        }

        wrap.innerHTML = '';
        suggestions.slice(0, 4).forEach(function (label) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ai-chat-chip';
            button.textContent = label;
            button.addEventListener('click', function () {
                input.value = label;
                input.focus();
            });
            wrap.appendChild(button);
        });
    }

    function buildWidget(root, config) {
        root.innerHTML = [
            '<button type="button" class="ai-chat-toggle" data-ai-chat-toggle aria-expanded="false">',
            '<span class="ai-chat-toggle-label">AI Support</span>',
            '</button>',
            '<section class="ai-chat-panel" data-ai-chat-panel hidden>',
            '<div class="ai-chat-panel-header">',
            '<div>',
            '<span class="ai-kicker">ShopFusion AI</span>',
            '<h3>Customer Support</h3>',
            '</div>',
            '<button type="button" class="ai-chat-close" data-ai-chat-close aria-label="Close chat">&times;</button>',
            '</div>',
            '<p class="ai-chat-status" data-ai-chat-status>Ask about shipping, payments, returns, or this product.</p>',
            '<div class="ai-chat-messages" data-ai-chat-messages></div>',
            '<div class="ai-chat-suggestions" data-ai-chat-suggestions></div>',
            '<form class="ai-chat-form" data-ai-chat-form>',
            '<textarea data-ai-chat-input rows="2" maxlength="800" placeholder="Type your question..."></textarea>',
            '<div class="ai-chat-form-footer">',
            '<span class="ai-chat-note">Secure request via PHP backend</span>',
            '<button type="submit" data-ai-chat-send>Send</button>',
            '</div>',
            '</form>',
            '</section>'
        ].join('');

        const toggle = root.querySelector('[data-ai-chat-toggle]');
        const panel = root.querySelector('[data-ai-chat-panel]');
        const close = root.querySelector('[data-ai-chat-close]');
        const form = root.querySelector('[data-ai-chat-form]');
        const input = root.querySelector('[data-ai-chat-input]');

        appendMessage(panel, 'bot', config.productId
            ? 'Welcome to ShopFusion support. I can help with this product, delivery details, payment options, returns, and basic order guidance.'
            : 'Welcome to ShopFusion support. I can help with product questions, delivery info, payment options, returns, and basic order guidance.');

        appendSuggestions(panel, [
            'Track my order',
            'Return policy',
            'Payment options',
            'Delivery info',
            'Best-selling products',
            config.productId ? 'Tell me about this product' : 'Product help'
        ], input);

        function openPanel() {
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            root.classList.add('is-open');
            input.focus();
        }

        function closePanel() {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            root.classList.remove('is-open');
        }

        toggle.addEventListener('click', function () {
            if (panel.hidden) {
                openPanel();
            } else {
                closePanel();
            }
        });

        close.addEventListener('click', closePanel);

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const message = input.value.trim();
            if (!message) {
                return;
            }

            appendMessage(panel, 'user', message);
            input.value = '';
            setLoading(panel, true);
            setTyping(panel, true);

            fetch(config.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    page_type: config.pageType || 'storefront',
                    page_title: config.pageTitle || document.title,
                    product_id: config.productId || 0
                })
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    setTyping(panel, false);
                    appendMessage(panel, 'bot', data.message || 'I could not generate a reply right now.');
                    appendSuggestions(panel, data.suggestions || [], input);
                })
                .catch(function () {
                    setTyping(panel, false);
                    appendMessage(panel, 'bot', 'I ran into a temporary support error. Please try again in a moment, or use My Orders, Checkout, or the product page details while the assistant reconnects.');
                })
                .finally(function () {
                    setLoading(panel, false);
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ai-chat-root]').forEach(function (root) {
            buildWidget(root, parseConfig(root));
        });
    });
})();
