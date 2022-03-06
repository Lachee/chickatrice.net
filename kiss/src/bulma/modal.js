import { EventEmitter } from "events";

export class Modal extends EventEmitter {

    /** @type {Element} */
    #element;
    
    /** @type {Element} */
    #container;

    /** @type {Element} */
    #parent;

    #active;
    
    constructor(options) {
        super();
        this.class      = options.class ?? 'modal';         //Class for the modal 
        this.closable   = options.closable ?? true;         //Can we close it using a X button
        this.#parent    = options.parent ?? document.body;  //The document to put it in
        this.content    = options.content ?? null;          //The contents
        this.once       = options.once ?? true;             //Should we destroy it once we close?
        this.#active    = options.active ?? true;          //Is it enabled by default?
        this.#element   = null;

        //Setup default event handlers
        this.#registerDefaultEvents(options);

        //Create it because we are active by default
        if (this.#active) {
            setTimeout(() => this.create(), 1);
        }
    }

    get container() { return this.#container; }
    get element() { return this.#element; }
    get parent() { return this.#parent; }
    get active() { return this.#active; }
    set active(s) {
        if (s) {
            this.open();
        } else {
            this.close();
        }
    }


    /** Adds the element to the parent */
    create() {
        if (this.#element) {
            console.error("Cannot render because the element already exists");
            return false;
        }

        console.log("MODAL")
        const modal = this.#element = document.createElement('div');
        this.#parent.appendChild(modal);

        const closeButton = this.closable ? 
                                '<button class="modal-close is-large" aria-label="close"></button>' :
                                '';
        
        modal.classList.add(this.class);
        modal.innerHTML = `
        <div class="modal-background"></div>
        <div class="modal-content">
            <div class="box">
            </div>
            ${closeButton}
        </div>
        `;

        const container = this.#container = modal.querySelector('.box')
        const content = this.render();
        if (typeof content === 'string') {
            container.innerHTML = content;
        } else {
            if ($ !== undefined) { 
                $(container).append(content);
            } else {
                container.appendChild(content);
            }
        }

        if (this.closable)
            modal.querySelector('button.modal-close').addEventListener('click', () => this.close());

        this.active = this.#active;
        this.emit('create', { modal: this, target: this.#element });
        return true;
    }

    /** Renders the content out */
    render() {
        return this.content;
    }

    /** Opens the container */
    open() { 
        //Make sure we have the element
        if (this.#element == null) this.create();

        //Create the items
        this.#active = true;
        this.#element.classList.add('is-active');
        this.emit('open', { modal: this, target: this.#element });
    }

    /** Closes the container */
    close() { 
        this.emit('close', { modal: this, target: this.#element });
        
        //Destroy the items
        if (this.once) {
            //Destroy the items
            this.destroy();
        } else {
            //Close the items
            this.#active = false;
            this.#element.classList.remove('is-active');
        }
    }

    /** Destroys the modal */
    destroy() {
        this.emit('destroy',  { modal: this, target: this.#element });
        this.#element.remove();
        this.#element = null;
        this.#active = false;
    }

    
    #registerDefaultEvents(options) {
        for(let key in options) {
            if (typeof key === 'string' && key.length > 4 && key.startsWith('on') && key !== 'once') {
                const event = key.toLowerCase().slice(2,3) + key.slice(3);
                const obj = options[key];
                this.on(event, obj);
                //Function Test: https://stackoverflow.com/a/6000016/5010271
                //if (!!(obj && obj.constructor && obj.call && obj.apply))
            }
        }
    }
}