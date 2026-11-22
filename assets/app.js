import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// Bootstrap 5 popover & tooltip initialization (native API, no jQuery required)
// Note: Bootstrap 5 is loaded via CDN in templates, so bootstrap global is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined') {
        // Initialize all popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    var searchForm = document.querySelector('[data-product-search-form]');
    if (!searchForm) {
        return;
    }

    var searchInput = searchForm.querySelector('[data-product-search-input]');
    var suggestionsBox = document.querySelector('[data-product-search-suggestions]');
    var suggestUrl = searchForm.dataset.suggestUrl;
    var searchAction = searchForm.getAttribute('action');
    var debounceTimer = null;
    var abortController = null;

    var hideSuggestions = function() {
        if (!suggestionsBox) {
            return;
        }

        suggestionsBox.classList.add('d-none');
        suggestionsBox.innerHTML = '';
    };

    var renderSuggestions = function(items) {
        if (!suggestionsBox) {
            return;
        }

        if (!items.length) {
            hideSuggestions();
            return;
        }

        suggestionsBox.innerHTML = '';

        items.forEach(function(title) {
            var item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = searchAction + '?q=' + encodeURIComponent(title);
            item.textContent = title;

            item.addEventListener('mousedown', function(event) {
                event.preventDefault();
                searchInput.value = title;
                searchForm.submit();
            });

            suggestionsBox.appendChild(item);
        });

        suggestionsBox.classList.remove('d-none');
    };

    var loadSuggestions = function(query) {
        if (!suggestUrl) {
            return;
        }

        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();

        var url = new URL(suggestUrl, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', '8');

        fetch(url.toString(), {
            signal: abortController.signal,
            headers: {
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Suggestion request failed');
                }

                return response.json();
            })
            .then(function(data) {
                renderSuggestions(Array.isArray(data.suggestions) ? data.suggestions : []);
            })
            .catch(function(error) {
                if (error.name === 'AbortError') {
                    return;
                }

                hideSuggestions();
            });
    };

    searchInput.addEventListener('input', function() {
        var query = searchInput.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            hideSuggestions();
            return;
        }

        debounceTimer = setTimeout(function() {
            loadSuggestions(query);
        }, 200);
    });

    searchInput.addEventListener('focus', function() {
        if (suggestionsBox && suggestionsBox.childElementCount > 0) {
            suggestionsBox.classList.remove('d-none');
        }
    });

    searchInput.addEventListener('blur', function() {
        window.setTimeout(hideSuggestions, 150);
    });

    searchForm.addEventListener('submit', function() {
        hideSuggestions();
    });

    document.addEventListener('click', function(event) {
        if (!searchForm.contains(event.target) && !suggestionsBox.contains(event.target)) {
            hideSuggestions();
        }
    });
}); 
