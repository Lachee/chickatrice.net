import './kiss.scss';
import './utility/mobile';
import './utility/MD5';
import './bulma/document-modal';
import './bulma/collapsable';
import './bulma/corousels';
import { FontAwesome } from './font-awesome/FontAwesome';

export const FA = new FontAwesome();
export function get(name) { return this[name]; }
export function set(name, value) { 
    this[name] = value; 
    return this; 
}

/*
export function createSchemaEditor(editorbox, schema, startval = null) {
        
    let options = {
        theme: 'spectre',    
        iconlib: "fontawesome5",
        remove_button_labels: true,
        schema: schema,
    }

    if (startval != null) {
        options.startval = startval;
    }

    const jsoneditor = new JSONEditor(editorbox, options);

    jsoneditor.on('change', async () => {
        editorbox.querySelectorAll('.btn').forEach(function(item)  {
            item.classList.add('button');
            item.classList.add('is-small');
            item.classList.add('is-info');
            item.classList.add('is-outlined');
        });
        editorbox.querySelectorAll('input').forEach(function(item)  {
            item.classList.add('input');
            item.classList.add('is-small');
        });
        editorbox.querySelectorAll('.delete').forEach(function(item)  {
            item.classList.remove('delete');
        });
        editorbox.querySelectorAll('.selectize-input').forEach(function(item)  {
            item.classList.add('select');
            item.classList.add('is-small');
        });
        editorbox.querySelectorAll('[data-schematype=boolean]').forEach(function(item)  {

            let label = item.querySelector('label').textContent;
            let name = item.querySelector('select').name;
            let value = item.querySelector('select').value;
            let checked = value == 1 ? 'checked' : '';
            
            let html = `<br><input id="${name}" type="checkbox" name="${name}" class="switch" ${checked}><label for="${name}">${label}</label>`;
            item.innerHTML = html + item.innerHTML;

            item.querySelector('label.je-label').remove();
            item.querySelector('select').remove();

            //item.outerHTML = `<div class="field"><input id="switchColorDefault" type="checkbox" name="switchColorDefault" class="switch" checked="checked"><label for="switchColorDefault">Switch default</label></div>`;
        });
    });
    
    $(editorbox).find('.je-panel .container.je-noindent h4 > button.json-editor-btn-collapse[title=Collapse]').click();
    return jsoneditor;
}
*/

console.log("KISS LOADED");