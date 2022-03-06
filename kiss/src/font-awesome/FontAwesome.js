//import '@fortawesome/fontawesome-pro/js/all';
import '@fortawesome/fontawesome-pro/scss/fontawesome.scss';
import '@fortawesome/fontawesome-pro/scss/brands.scss';
import '@fortawesome/fontawesome-pro/scss/light.scss';
import '@fortawesome/fontawesome-pro/scss/solid.scss';

export class FontAwesome {
    #icons;
    constructor() { }
    
    async load() {
        // const fadata = await import(            
        //     /* webpackChunkName: `fadata` */ 
        //     "../../../vendor/lachee/fontawesome-5-cheatsheet/src/fontawesome.json"
        // );
        // this.#icons = {};
        // for(let key in fadata[1].data) {
        //     this.#icons[fadata[1].data[key].id] = fadata[1].data[key].attributes;
        // }
        // return this.#icons;
    }

    getIcon(name) {
        return this.#icons[name];
    }
}