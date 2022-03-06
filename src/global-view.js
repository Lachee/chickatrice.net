import {delegate} from 'tippy.js';

console.log('Initializing Global');

/**== Navigation Script
 * this script handles the fading of all .has-placeholder-transition objects.
 */
const PLACEHOLDER_TRANSITION_TIME = 1;
const PLACEHOLDER_TRANSITION_DELAY = PLACEHOLDER_TRANSITION_TIME * 3;
setInterval( () => transitionPlaceholders(), (PLACEHOLDER_TRANSITION_DELAY + (PLACEHOLDER_TRANSITION_TIME * 2)) * 1000);
transitionPlaceholders(0);
function transitionPlaceholders(transitionTime = PLACEHOLDER_TRANSITION_TIME) {
    const elements = document.querySelectorAll('.has-placeholder-transition');
    elements.forEach((doc) => {
        doc.classList.add('is-transitioning');
        setTimeout(() => {
            let availablePlaceholders = doc.getAttribute('data-placeholders').split('|');
            if (doc.placeholderIndex == undefined) doc.placeholderIndex = 0;
            doc.placeholder = availablePlaceholders[doc.placeholderIndex++ % availablePlaceholders.length];
            doc.classList.remove('is-transitioning');
        }, transitionTime * 1000);
    });
}


console.log('creating completer');
const completer = new autoComplete({
    data: { 
        src: async() => {
            const query = document.querySelector("#navbar-search").value;
            const source = await fetch('/api/tags?limit=5&q=' + encodeURIComponent(query));
            const json = await source.json();
            const tags = json.data.map(d => d.name);
            return tags;
        } 
    },
    selector: '#navbar-search',
    trigger: {
        event: ["input"],
    },
    searchEngine: (query, record) => {
        const parts = query.split(' ');
        let value = parts[parts.length - 1].toLowerCase();
        if (value.startsWith('-')) record = '-' + record;
        const compr = record.toLowerCase();
        if (value.includes(compr) || compr.includes(value))
            return record;
    },
    onSelection: (feedback) => {
        const parts = document.querySelector("#navbar-search").value.split(' ');
        let   match = feedback.selection.match;
        if (match.includes(' ') || match.includes(',')) match = `"${match}"`;

        parts[parts.length - 1] = match;
        document.querySelector("#navbar-search").value = parts.join(' ') + ' ';
        document.querySelector("#navbar-search").focus();
    },
});


/**== Search Box
 * this script handles the search box changing the "search" button to "post" when a URL is present
 */
document.getElementById('navbar-search').addEventListener('keyup', (event) => {
    // Number 13 is the "Enter" key on the keyboard
    if (event.keyCode === 13) {
        // Cancel the default action, if needed
        document.getElementById('navbar-search').form.submit();
        event.preventDefault();
        return;
    }
    
    const submitButton = document.querySelector('#navbar-submit span');
    const submitIcon = document.querySelector('#navbar-submit i');
    const value = event.target.value;
    if (validURL(value)) { 
        submitButton.innerText = 'Post';
        submitIcon.classList.remove('fa-search');
        submitIcon.classList.add('fa-plus');
    }
    else
    {
      submitButton.innerText = 'Search';
      submitIcon.classList.add('fa-search');
      submitIcon.classList.remove('fa-plus');
    }

    function validURL(str) {
        var pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
        '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
        '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
        '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
        return !!pattern.test(str);
    }        
});

if (!window.isMobile()) {
    /** Image ALT feature.
     * When a image fails to load, then its alt shall be used
     */
    document.body.addEventListener(
        'error',
        function(event){
            if(event.target.tagName == 'IMG'){
                const src = event.target.src;
                const alt = event.target.getAttribute('src-alt');
                if (alt != null) {
                    if (src != alt && !event.target.hasAttribute('no-retry')) { 
                        event.target.setAttribute('src-origin', event.target.src);
                        event.target.setAttribute('no-retry', true);

                        event.target.src = alt;
                        console.warn('Image failed to load, so attempting the alt', src, alt);
                    }
                }
            }
        },
        true // <-- useCapture
    );
}

/** Tooltips */
delegate('body', {
    target: '[data-tooltip], [title]',
    allowHTML: true,
    multiple: true,
    content: (reference) => {
        const content = reference.getAttribute('data-tooltip') || reference.getAttribute('title');
        return content;
    },
});