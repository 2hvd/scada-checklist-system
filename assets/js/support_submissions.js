/* support_submissions.js - Support item-level review handling */

const SupportSubmissions = {
    swoId: null,

    /**
     * Initialise the support review view for a given SWO.
     * Sets ChecklistPage into support mode, loads the checklist in read-only mode,
     * then populates any previously saved reviews and attaches auto-save listeners.
     */
    async init(swoId) {
        this.swoId = swoId;
        ChecklistPage.supportMode = true;
        await ChecklistPage.init(swoId, true);
        await this.loadAndPopulateReviews();
        this.bindSaveEvents();
    },

    /**
     * Fetch existing support reviews from the API and populate the
     * decision dropdowns and comment textareas in the rendered checklist.
     */
    async loadAndPopulateReviews() {
        const data = await API.get('/swo/get_support_reviews.php', {swo_id: this.swoId});
        if (!data || !data.success || !data.data) return;

        const reviews = data.data.reviews || [];
        reviews.forEach(review => {
            const key = review.item_key;

            const select = document.querySelector(`.support-decision[data-item-key="${CSS.escape(key)}"]`);
            if (select && review.support_decision) {
                select.value = review.support_decision;
            }

            const textarea = document.querySelector(`.support-comment[data-item-key="${CSS.escape(key)}"]`);
            if (textarea && review.support_comment) {
                textarea.value = review.support_comment;
            }
        });
    },

    /**
     * Attach event listeners to the checklist container:
     * - "change" on any .support-decision select triggers an auto-save
     * - "blur" (capture phase) on any .support-comment textarea triggers an auto-save
     */
    bindSaveEvents() {
        const container = document.getElementById('checklistContainer');
        if (!container) return;

        container.addEventListener('change', async (e) => {
            if (e.target.classList.contains('support-decision')) {
                await this.saveReview(e.target);
            }
        });

        container.addEventListener('blur', async (e) => {
            if (e.target.classList.contains('support-comment')) {
                await this.saveReview(e.target);
            }
        }, true /* capture phase so blur bubbles */);
    },

    /**
     * Collect the decision and comment for the row that contains `el` and
     * POST them to the save endpoint.
     */
    async saveItemReview(swoId, itemKey, decision, comment) {
        const data = await API.post('/swo/save_support_item_review.php', {
            swo_id: swoId,
            item_key: itemKey,
            support_decision: decision,
            support_comment: comment
        });
        if (data && data.success) {
            showSuccess('Review saved');
        } else {
            showError(data && data.message ? data.message : 'Failed to save review');
        }
    },

    async saveReview(el) {
        const itemKey = el.dataset.itemKey;
        const swoId   = parseInt(el.dataset.swoId, 10);

        const row = el.closest('.checklist-item');
        if (!row) {
            console.error('SupportSubmissions: .checklist-item not found for', itemKey);
            return;
        }

        const decisionEl = row.querySelector('.support-decision');
        const commentEl  = row.querySelector('.support-comment');
        const decision   = decisionEl ? decisionEl.value : '';
        const comment    = commentEl  ? commentEl.value  : '';

        await this.saveItemReview(swoId, itemKey, decision, comment);
    }
};
