=== AI Content Freshness Monitor ===
Contributors: nacharcode
Donate link: https://www.patreon.com/nacharcode/membership
Tags: seo, content audit, content freshness, ai, gemini
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit published posts for content staleness and topical freshness against live search results using AI engines.

== Description ==

**AI Content Freshness Monitor** is a 100% free and open-source WordPress plugin designed to prevent content decay and maintain top search engine rankings.

Instead of relying purely on publication dates, the plugin connects directly to **Google Gemini** (with native Google Search grounding) or **OpenAI GPT-4o** (with web search) to compare your published articles against current top-ranking competitor pages in real time.

### Key Features

* **Stale Posts Inventory Dashboard**:
  * Automatically scans and displays all published posts that have not been modified within your configured staleness threshold (e.g. 6 months).
  * Oldest modified posts are prioritized at the top.
  * Automatic keyword detection fallback from **Rank Math SEO** (`rank_math_focus_keyword`) and **Yoast SEO** (`_yoast_wpseo_focuskw`).
  * Inline auto-saving keyword editor on blur or change.
  * Row-by-row live SERP audit trigger ("Scan Now" / "Re-Scan") with instant status and score badge updates.

* **Post Editor Sidebar Meta Box**:
  * Clean sidebar card in both **Gutenberg (Block Editor)** and **Classic Editor**.
  * Dynamically captures live, unsaved editor content and titles, ensuring the AI audits your freshest edits.
  * Generates an interactive **Report Card**:
    * **Freshness Score Badge**: Color-coded circular score pill (🟢 Green >= 70, 🟡 Orange 50-69, 🔴 Red < 50).
    * **Executive Summary**: Overview of how fresh and competitive your content is.
    * **Outdated Claims**: Specific obsolete points with recommended factual corrections.
    * **Missing Topics**: Crucial competitor topics you should cover to boost rankings.
    * **Action Checklist**: Interactive checkboxes with strike-through styling to guide your updates.

* **AI Search Grounding & Custom Models**:
  * Default Gemini model: **gemini-3.8-flash** with live Google Search grounding.
  * Manual model override: Type any Gemini or OpenAI model name, with automatic fallback.
  * Fast authentication test (< 300ms) without wasting token generation quotas.
  * Forced IPv4 resolution (`CURL_IPRESOLVE_V4`) to avoid common 15-second cURL timeouts on Windows and local environments.

* **100% Free & Open-Source**:
  * No license keys, no paywalls, no feature gating. Bring your own free or paid Gemini/OpenAI API key.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or upload the `AI Content Freshness Monitor.zip` file directly through **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Tools > Content Freshness > API & Engine Settings**.
4. Enter your Google Gemini or OpenAI API key and click **Test Connection**.
5. Adjust your staleness threshold in months (default: 6) and click **Save Settings**.

== Frequently Asked Questions ==

= Where do I get an API key? =
* For Google Gemini: Obtain a free API key from Google AI Studio at https://aistudio.google.com/
* For OpenAI: Obtain an API key from the OpenAI Platform dashboard at https://platform.openai.com/api-keys

= Which AI model does the plugin use? =
By default, the plugin uses `gemini-3.8-flash` for Google Gemini and `gpt-4o` for OpenAI. You can also specify any custom model in Tools > Content Freshness.

= How do I support this project? =
If this free plugin saves you time or boosts your SEO rankings, you can support future maintenance on Patreon at https://www.patreon.com/nacharcode/membership

= Are my audit reports saved? =
Yes. Every audit report, along with the timestamp, is saved to your WordPress database (`wp_postmeta`) so it is instantly visible whenever you reopen the post editor.

== Screenshots ==

1. Stale Posts Inventory Dashboard under Tools > Content Freshness.
2. AI Engine Settings and API Key Connection Test.
3. Content Freshness Audit meta box in the post editor with score pill and action checklist.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added Stale Posts Inventory table with inline keyword auto-saving.
* Added live search grounding for Google Gemini and OpenAI.
* Added Gutenberg live editor content capture.
* Added Patreon community supporter UI.

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI Content Freshness Monitor. Install to begin auditing content staleness and topical freshness against live search results.
