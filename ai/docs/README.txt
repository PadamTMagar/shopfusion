ShopFusion AI Module
====================

This folder contains every AI-related feature for the ShopFusion website.

What was added:
- AI customer support chatbot
- AI review moderation
- AI review analysis with summary, pros, cons, and sentiment
- Demo review support for products with no live reviews yet
- Shared UI refresh support for cleaner chatbot and review presentation
- Protected backend API endpoints
- Frontend assets for chat and review insight UI
- TXT-only documentation

Main folder structure:
- ai/config
- ai/includes
- ai/api
- ai/js
- ai/css
- ai/demo
- ai/docs
- ai/logs

Important notes:
- The OpenAI API key is never exposed in HTML, CSS, or JavaScript.
- The API key must be supplied on the server through an environment variable.
- If no API key is configured, the system still works in a fallback mode:
  - Chatbot replies use local store-policy logic
  - Review moderation uses basic rule-based checks
  - Review analysis uses a lightweight fallback summary

Current integration points:
- Customer-facing pages can load the floating AI chatbot
- Product pages can load AI review insights
- Existing review submission flows can call AI moderation before saving

Recommended next extensions:
- Order tracking lookup
- Live stock availability checks
- Search-aware product recommendations
- FAQ retrieval from a managed knowledge base
