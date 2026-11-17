/**
 * Live filter for the freezer item list: matches food name and label code,
 * ignoring case, accents and the Turkish dotted/dotless i.
 */
(() => {
    const searchBox = document.getElementById('item-search');
    const list = document.getElementById('item-list');
    const noMatches = document.getElementById('item-no-matches');
    if (!searchBox || !list) {
        return;
    }

    const cards = Array.from(list.children).map((element) => ({
        element,
        text: normalize(element.dataset.search || ''),
    }));

    function normalize(value) {
        return String(value)
            .replace(/İ/g, 'i')
            .replace(/ı/g, 'i')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function filter() {
        const terms = normalize(searchBox.value).split(/\s+/).filter(Boolean);
        let visible = 0;

        cards.forEach((card) => {
            const matches = terms.every((term) => card.text.includes(term));
            card.element.hidden = !matches;
            if (matches) {
                visible++;
            }
        });

        noMatches.hidden = visible > 0;
    }

    searchBox.addEventListener('input', filter);
})();
