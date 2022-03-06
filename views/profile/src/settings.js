import './settings.scss';

$('.tag-selector').select2({
    width: '100%',
    placeholder: 'select tag...',
    ajax: {
        url: '/api/tags',
        data: (params) => new Object({ q: params.term, page: params.page || 1, select2: true })
    }
});


//Setup all the emote dropdowns. 
// They scan the emote data seperate since they are rich. Could probably do the same for tag but lazy
$('.emote-selector').each(async (i, elm) => {
    let data = [];

    //If we have a value selected, search for it and concatinate it to our list
    if (elm.value) {
        const response = await fetch('/api/emotes?select2=true&id=' + elm.value);
        const result = await response.json();
        data = data.concat(result.results);
    }

    //SEtup the item
    $(elm).select2({
        data: data,
        width: '100%',
        allowClear: true,
        placeholder: 'select emote...',
        ajax: {
            url: '/api/emotes',
            data: (params) => new Object({ q: params.term, page: params.page || 1, select2: true })
        },
        templateResult: emoteTemplate,
        templateSelection: emoteTemplate,
    });

});

function emoteTemplate(state) {
    if (!state.id) return state.text;
    var $state = $(`<span><img src="${state.url}" class="emote" /><span class="emote-tag">:${state.text || state.name}:</span></span>`);
    return $state;
}