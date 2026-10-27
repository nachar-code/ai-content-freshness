# AI Content Freshness Monitor (WordPress Plugin)

**AI Content Freshness Monitor** is a WordPress plugin that audits published posts for content staleness and topical freshness against real-time search results using Google Gemini or OpenAI with native live web search grounding.

## Features

- **Stale Posts Inventory Dashboard**:
  - Automatically queries published posts not updated within the configured threshold (e.g., 6 months).
  - Displays relative modified age, target keywords (with automatic fallback to Rank Math / Yoast SEO focus keywords), freshness scores, and last audited timestamps.
  - Inline auto-saving keyword editor on `blur`/`change`.
  - Row-by-row asynchronous **Scan Now** / **Re-Scan** trigger.
- **Post Editor Meta Box**:
  - Custom sidebar meta box on post edit screens (`post.php` / `post-new.php`).
  - Supports live unsaved content and title detection from both Gutenberg Block Editor and Classic Editor.
  - One-click **Scan & Refresh** with dynamic status loading.
  - Interactive **Report Card**:
    - **Freshness Score Pill** (0–100, color-coded green, orange, or red).
    - **Summary Overview** of content freshness against top SERP competitors.
    - **Outdated Points** with specific claims and recommended factual corrections.
    - **Missing Topics** with competitor keyword badges and reasoning.
    - **Action Checklist** with interactive strike-through checkboxes.
- **AI Engines with Live Web Search Grounding**:
  - **Google Gemini**: Live search grounding via `gemini-3.8-flash` with the `google_search` tool.
  - **OpenAI**: Live web search completion via `gpt-4o`.
  - Manual model override field with automatic fallback to `gemini-3.8-flash`.
  - Instant lightweight connection test without consuming generation tokens.
  - Forced IPv4 resolution to prevent 15-second IPv6 cURL timeouts on Windows/local environments.
- **WordPress Standards Compliant**:
  - Strict WPCS formatting, nonces (`check_ajax_referer`, `wp_verify_nonce`), and capabilities checks (`manage_options`, `edit_post`).
  - Strict input sanitization and output escaping.

## Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   wp-content/plugins/ai-content-freshness
   ```
2. Activate the plugin in **Plugins → Installed Plugins**.
3. Go to **Tools → Content Freshness → API & Engine Settings**.
4. Enter your Gemini or OpenAI API key, verify with **Test Connection**, and click **Save Settings**.

## Author

- **Author**: nachar-code
- **GitHub**: [https://github.com/nachar-code](https://github.com/nachar-code)
- **License**: GPL-2.0-or-later
