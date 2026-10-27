
# Antigravity Task Specification: AI Content Freshness Monitor (WordPress Plugin)

## System Identity & Guardrails
- **Project Type:** Standard WordPress Plugin (Single or Multi-site compatible)
- **Plugin Slug:** `ai-content-freshness`
- **Target PHP Version:** 7.4+ to 8.3
- **Coding Standard:** WordPress Coding Standards (WPCS), strict escaping, nonces, and `check_admin_referer` on every action.
- **Rule for Antigravity:** Work strictly on the files designated in the active phase. Do not generate code or files for subsequent phases until prompted. Do not introduce Composer or NPM packages unless explicitly instructed.

---

## Target Project File Tree
```text
ai-content-freshness/
├── ai-content-freshness.php
├── includes/
│   ├── class-admin-settings.php
│   ├── class-post-meta.php
│   ├── class-api-client.php
│   └── class-ajax-handlers.php
└── assets/
    ├── css/admin-style.css
    └── js/admin-script.js

```

---

## Phase 1: Core Boilerplate & Admin Settings Panel

### Objective

Initialize the plugin, register settings in `wp_options`, register a settings page under **Tools > Content Freshness**, and implement a connection test AJAX handler.

### Files to Create/Modify

1. `ai-content-freshness.php`
2. `includes/class-admin-settings.php`
3. `includes/class-ajax-handlers.php`
4. `assets/css/admin-style.css`
5. `assets/js/admin-script.js`

### Implementation Directives

1. **`ai-content-freshness.php`**:
* Header with Plugin Name: `AI Content Freshness Monitor`, Version `1.0.0`, Text Domain `ai-content-freshness`.
* Prevent direct execution (`defined('ABSPATH') || exit;`).
* Define constants: `AICFM_VERSION`, `AICFM_PLUGIN_DIR`, `AICFM_PLUGIN_URL`, `AICFM_SETTINGS_KEY = 'aicfm_settings'`.
* On plugin activation: Register default option in `wp_options` for `aicfm_settings` containing:
`['engine' => 'gemini', 'gemini_key' => '', 'openai_key' => '', 'staleness_mo' => 6]`.
* Hook into `plugins_loaded` to instantiate `AICFM\Admin_Settings` and `AICFM\Ajax_Handlers`.


2. **`includes/class-admin-settings.php`**:
* Class `AICFM\Admin_Settings`.
* Register menu: `add_submenu_page('tools.php', 'Content Freshness', 'Content Freshness', 'manage_options', 'aicfm-settings', ...)`.
* Register setting `aicfm_settings_group` mapping to `aicfm_settings`.
* Settings form fields:
* Dropdown: `engine` (Options: `gemini`, `openai`).
* Password Input: `gemini_key`.
* Password Input: `openai_key`.
* Number Input: `staleness_mo` (default: 6).
* Beside each API key input, include a button: `<button type="button" class="button aicfm-test-key-btn" data-engine="...">Test Connection</button>` and a result container `<span class="aicfm-test-result"></span>`.


* Enqueue admin JS and CSS only on `tools_page_aicfm-settings`. Localize script with `ajax_url` and nonce `aicfm_admin_nonce`.


3. **`includes/class-ajax-handlers.php`**:
* Hook `wp_ajax_aicfm_test_connection`.
* Verify `aicfm_admin_nonce` and `current_user_can('manage_options')`.
* Accept `engine` and `api_key`.
* If `gemini`: Make a simple ping using `wp_remote_post` to `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=[KEY]`.
* If `openai`: Make a ping using `wp_remote_get` to `https://api.openai.com/v1/models` with Bearer auth.
* Return `wp_send_json_success()` or `wp_send_json_error()` with user-friendly messages.


4. **`assets/js/admin-script.js`**:
* Show/hide API key fields based on the selected dropdown value (`gemini` vs `openai`).
* Wire the click event on `.aicfm-test-key-btn` to submit the AJAX request to `aicfm_test_connection` and display success/failure badges without page reload.



### Verification Criteria for Phase 1

* Navigating to **Tools > Content Freshness** displays the settings page with clean WordPress admin styles.
* Switching between Gemini and OpenAI toggles the corresponding API key input field.
* Clicking "Test Connection" with an invalid key renders a red error notice; an empty input rejects immediately without firing a server request.

---

## Phase 2: Post Meta Box & Keyword Targeting

### Objective

Add a dedicated Content Freshness sidebar/meta box to the standard Post edit screen (`edit.php`), allowing authors to specify a target keyword and see previous scan metadata.

### Files to Create/Modify

1. `includes/class-post-meta.php`
2. Update `ai-content-freshness.php` to instantiate `AICFM\Post_Meta`.
3. Update `assets/css/admin-style.css` and `assets/js/admin-script.js`.

### Implementation Directives

1. **`includes/class-post-meta.php`**:
* Class `AICFM\Post_Meta`.
* Hook `add_meta_boxes` to add a meta box to `post`:
* ID: `aicfm_freshness_box`
* Title: `Content Freshness Audit`
* Context: `side`
* Priority: `high`


* Render fields:
* Target Keyword input: `_aicfm_target_keyword` (saved via `save_post` hook with nonces and `sanitize_text_field`).
* "Last Audited" label: Displays human-readable date from meta `_aicfm_last_audited` or "Never".
* Primary Action Button: `<button type="button" id="aicfm-run-audit-btn" class="button button-primary">Scan & Refresh</button>`.
* Container `<div id="aicfm-audit-results"></div>` to render the audit output.


* Enqueue admin JS/CSS on the `post.php` and `post-new.php` screens.



### Verification Criteria for Phase 2

* Editing any WordPress post shows the "Content Freshness Audit" meta box in the sidebar.
* Saving a post updates and retains the target keyword in `_aicfm_target_keyword`.

---

## Phase 3: AI Engine Client (Live Search Grounding)

### Objective

Create a lightweight API client that queries Gemini or OpenAI using native web search grounding, returning a clean, validated JSON audit payload.

### Files to Create/Modify

1. `includes/class-api-client.php`

### Implementation Directives

1. **`includes/class-api-client.php`**:
* Class `AICFM\API_Client`.
* Public method: `public function audit_content( $title, $content, $keyword )`.
* Strip HTML tags from `$content` and cap length at 2,500 words to conserve tokens and prevent PHP timeout.
* Construct prompt:
```text
You are a senior SEO content auditor.
Target Keyword: %s
Post Title: %s
Post Content: %s

Perform a live web search for the target keyword to analyze top current ranking results.
Compare the post content against current ranking results.
Return ONLY a valid JSON object matching this schema:
{
  "score": 75,
  "summary": "Short 2-sentence overview",
  "outdated_points": [
    {"claim": "string", "fix": "string"}
  ],
  "missing_topics": [
    {"topic": "string", "reason": "string"}
  ],
  "action_steps": ["string", "string"]
}
Do NOT return any markdown wrapping or text outside the JSON.

```


* **Gemini Engine Execution**:
* Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=[API_KEY]`.
* Enable tool: `'tools' => [ [ 'google_search' => new \stdClass() ] ]`.
* Extract text from `candidates[0]['content']['parts'][0]['text']`.


* **OpenAI Engine Execution**:
* Endpoint: `https://api.openai.com/v1/chat/completions`.
* Model: `gpt-4o`.
* Enable tool: `'tools' => [ [ 'type' => 'web_search' ] ]`.
* Extract content string.


* Sanitize and strip possible `json ... ` formatting fences using regex before passing to `json_decode( $text, true )`.
* Return clean PHP array on success, or `WP_Error` on failure. Timeout must be set to `60` seconds on `wp_remote_post`.



### Verification Criteria for Phase 3

* Unit/integration call executes with sample post content and keyword.
* The returned data validates as an array containing `score`, `summary`, `outdated_points`, `missing_topics`, and `action_steps`.

---

## Phase 4: AJAX Audit Runner & Report Card Rendering

### Objective

Wire the post editor "Scan & Refresh" button to run the audit asynchronously, persist results to post meta, and render structured UI cards directly in the meta box.

### Files to Create/Modify

1. Update `includes/class-ajax-handlers.php`
2. Update `assets/js/admin-script.js`
3. Update `assets/css/admin-style.css`
4. Update `includes/class-post-meta.php`

### Implementation Directives

1. **`includes/class-ajax-handlers.php`**:
* Register hook `wp_ajax_aicfm_execute_post_audit`.
* Check nonce `aicfm_post_nonce` and capability `edit_post`.
* Fetch post by `post_id`. Read `_aicfm_target_keyword`. If empty, fail with "Please provide a target keyword."
* Instantiate `AICFM\API_Client` and execute `audit_content()`.
* On success:
* Store report in post meta: `update_post_meta($post_id, '_aicfm_audit_report', $result_data)`.
* Store timestamp: `update_post_meta($post_id, '_aicfm_last_audited', current_time('mysql'))`.
* Return `wp_send_json_success($result_data)`.


* On failure: Return `wp_send_json_error(['message' => $error_message])`.


2. **`includes/class-post-meta.php`**:
* In the meta box markup, check if `_aicfm_audit_report` already exists in post meta.
* If present, render the existing report on page load so previous audit history is immediately visible.


3. **`assets/js/admin-script.js` & `admin-style.css**`:
* On clicking `#aicfm-run-audit-btn`:
* Disable the button and display a loading indicator: `"Searching live web and auditing content..."`.
* Call `aicfm_execute_post_audit` via `jQuery.ajax`.
* On completion, clear the loader, re-enable the button, and render the results:
* **Score Pill:** Circular badge colored based on score (>=70 green, 50-69 orange, <50 red).
* **Summary paragraph.**
* **Outdated Points Card:** Collapsible list showing flagged claim and recommended fix.
* **Missing Topics List:** Tag badges highlighting missing subtopics found in competitor searches.
* **Action Checklist:** Styled checklist of immediate editing steps.







### Verification Criteria for Phase 4

* Clicking "Scan & Refresh" triggers the live search without a full page refresh.
* The meta box updates with the score badge, outdated claims, missing topics, and action checklist.
* Reloading the page retains and re-renders the last generated audit report from post meta.

```

---

### Step-by-Step Antigravity Prompts

Copy and paste these prompts one at a time into your Antigravity session:

#### Step 1
```text
Read the "Phase 1: Core Boilerplate & Admin Settings Panel" section in antigravity_mvp_spec.md. 
Generate the files exactly as specified for Phase 1 only:
- ai-content-freshness.php
- includes/class-admin-settings.php
- includes/class-ajax-handlers.php
- assets/css/admin-style.css
- assets/js/admin-script.js
Ensure full WordPress security practices (nonces, capability checks, input sanitization).

```

#### Step 2

```text
Read "Phase 2: Post Meta Box & Keyword Targeting" in antigravity_mvp_spec.md.
Create includes/class-post-meta.php and update ai-content-freshness.php and assets to register the sidebar meta box for post keywords and audit triggers.

```

#### Step 3

```text
Read "Phase 3: AI Engine Client (Live Search Grounding)" in antigravity_mvp_spec.md.
Implement includes/class-api-client.php supporting both Gemini with Google Search grounding and OpenAI with web search. Ensure strict JSON parsing and timeout handling.

```

#### Step 4

```text
Read "Phase 4: AJAX Audit Runner & Report Card Rendering" in antigravity_mvp_spec.md.
Wire the AJAX runner into includes/class-ajax-handlers.php, connect the frontend in assets/js/admin-script.js, add the card styles to assets/css/admin-style.css, and render saved audit reports on load in includes/class-post-meta.php.

```