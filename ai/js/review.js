(function () {
    function parseConfig(element) {
        try {
            return JSON.parse(element.getAttribute('data-config') || '{}');
        } catch (error) {
            return {};
        }
    }

    function renderList(target, items, emptyText) {
        target.innerHTML = '';

        if (!Array.isArray(items) || items.length === 0) {
            const li = document.createElement('li');
            li.textContent = emptyText;
            target.appendChild(li);
            return;
        }

        items.forEach(function (item) {
            const li = document.createElement('li');
            li.textContent = item;
            target.appendChild(li);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ai-review-analysis]').forEach(function (panel) {
            const config = parseConfig(panel);
            if (!config.apiUrl || !config.productId) {
                return;
            }

            fetch(config.apiUrl + '?product_id=' + encodeURIComponent(config.productId), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success || !data.analysis) {
                        throw new Error('Analysis unavailable');
                    }

                    const analysis = data.analysis;
                    const summary = panel.querySelector('[data-ai-review-summary]');
                    const status = panel.querySelector('[data-ai-review-status]');
                    const pros = panel.querySelector('[data-ai-review-pros]');
                    const cons = panel.querySelector('[data-ai-review-cons]');
                    const sentiment = panel.querySelector('[data-ai-review-sentiment]');
                    const meta = panel.querySelector('[data-ai-review-meta]');

                    summary.textContent = analysis.summary;
                    status.textContent = analysis.source === 'openai' ? 'Live AI summary' : 'Smart fallback summary';
                    renderList(pros, analysis.pros, 'Not enough repeated positive signals yet.');
                    renderList(cons, analysis.cons, 'No repeated concerns detected yet.');
                    sentiment.textContent = (analysis.sentiment || 'mixed').replace('_', ' ');
                    sentiment.dataset.sentiment = analysis.sentiment || 'mixed';
                    meta.textContent = analysis.review_count + ' review' + (analysis.review_count === 1 ? '' : 's') +
                        ' analyzed • Avg ' + Number(analysis.average_rating || 0).toFixed(1) + '/5';
                })
                .catch(function () {
                    const summary = panel.querySelector('[data-ai-review-summary]');
                    const status = panel.querySelector('[data-ai-review-status]');
                    summary.textContent = 'AI review insights are temporarily unavailable, but customer reviews are still shown below.';
                    status.textContent = 'Unavailable';
                });
        });
    });
})();
